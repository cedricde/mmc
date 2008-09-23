import re
import os
import logging

import xmlrpclib
from twisted.web.xmlrpc import Proxy

import mmc.plugins.msc
from mmc.plugins.msc.config import MscConfig, makeURL
from mmc.support.mmctools import Singleton

from mmc.client import XmlrpcSslProxy, makeSSLContext

from twisted.internet import defer

class SchedulerApi(Singleton):
    def __init__(self):
        self.logger = logging.getLogger()
        self.config = mmc.plugins.msc.MscConfig("msc")
        
        if self.config.sa_enable:
            if self.config.sa_enablessl:
                self.server_addr = 'https://'
            else:
                self.server_addr = 'http://'
    
            if self.config.sa_username != '':
                self.server_addr += self.config.sa_username
                if self.config.sa_password != '':
                    self.server_addr += ":"+self.config.sa_password 
                self.server_addr += "@"
    
            self.server_addr += self.config.sa_server+':'+str(self.config.sa_port) + self.config.sa_mountpoint
            self.logger.debug('SchedulerApi will connect to %s' % (self.server_addr))
    
            if self.config.sa_verifypeer:
                self.saserver = XmlrpcSslProxy(self.server_addr)
                self.sslctx = makeSSLContext(self.config.sa_verifypeer, self.config.sa_cacert, self.config.sa_localcert, False)
                self.saserver.setSSLClientContext(self.sslctx)
            else:
                self.saserver = Proxy(self.server_addr)
    
    def onError(self, error, funcname, args):
        self.logger.warn("%s %s has failed: %s" % (funcname, args, error))
        return error

    def convert2id(self, scheduler):
        self.logger.debug("Looking up scheduler id using: " + str(scheduler))
        ret = None
        if type(scheduler) == dict:
            if "mountpoint" in scheduler and scheduler["mountpoint"]:
                ret = scheduler["mountpoint"]
            elif "server" in scheduler and "port" in scheduler and scheduler["server"] and scheduler["port"]:
                scheduler = makeURL(scheduler)
        elif type(scheduler) in (str, unicode):
            ret = scheduler
        if not ret:
            if self.config.scheduler_url2id.has_key(scheduler):
                self.logger.debug("Found scheduler id from MSC config file using this key %s" % scheduler)
                ret = self.config.scheduler_url2id[scheduler]
        if not ret:
            self.logger.debug("Using default scheduler")
            ret = self.config.default_scheduler
        self.logger.debug("Using scheduler '%s'" % ret)
        return scheduler
    
    def cb_convert2id(self, result):
        if type(result) == list:
            return map(lambda s: self.convert2id(s), result)
        else:
            return self.convert2id(result)

    def getScheduler(self, machine):
        if self.config.sa_enable:
            machine = self.convertMachineIntoH(machine)
            d = self.saserver.callRemote("getScheduler", machine)
            d.addErrback(self.onError, "SchedulerApi:getScheduler", machine)
            d.addCallback(self.cb_convert2id)
            return d
        else:
            return defer.succeed(MscConfig("msc").default_scheduler)
        
    def getSchedulers(self, machines):
        if self.config.sa_enable:
            machines = map(lambda m: self.convertMachineIntoH(m), machines)
            d = self.saserver.callRemote("getSchedulers", machines)
            d.addErrback(self.onError, "SchedulerApi:getSchedulers", machines)
            d.addCallback(self.cb_convert2id)
            return d
        else:
            return defer.succeed(map(lambda m: MscConfig("msc").default_scheduler, machines))

    def convertMachineIntoH(self, machine):
        if type(machine) != dict:
            machine = {'uuid':machine}
        return machine

