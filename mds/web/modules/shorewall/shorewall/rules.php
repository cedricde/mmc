<?php

/**
 * (c) 2004-2007 Linbox / Free&ALter Soft, http://linbox.com
 * (c) 2007-2012 Mandriva, http://www.mandriva.com
 *
 * This file is part of Mandriva Management Console (MMC).
 *
 * MMC is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * MMC is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MMC.  If not, see <http://www.gnu.org/licenses/>.
 */

require("modules/shorewall/includes/functions.inc.php");
require("modules/shorewall/shorewall/localSidebar.php");
require("graph/navbar.inc.php");

global $errorStatus;

// Handle form return

if (isset($_POST['bpolicy'])) {
    foreach(getPolicies() as $policy) {
        if (isset($_POST[$policy[0] . "_" . $policy[1] . "_policy"])) {
            $new = $_POST[$policy[0] . "_" . $policy[1] . "_policy"];
            $old = $policy[2];
            if ($new != $old) {
                changePolicies($policy[0], $policy[1], $new, $policy[3]);
                if (!isXMLRPCError()) {
                    $n = new NotifyWidgetSuccess(_T("Policy changed."));
                    handleServicesModule($n, array("shorewall" => _T("Firewall")));
                }
                else {
                    new NotifyWidgetFailure(_T("Failed to change the policy."));
                }
                redirectTo(urlStrRedirect("shorewall/shorewall/" . $page));
            }
        }
    }
}

if (isset($_POST['brule'])) {
    if (isset($_POST['service'])) {
        $service = $_POST['service'];
        if ($service) {
            if ($service == "custom") {
                if (!$_POST['proto'] || !$_POST['port']) {
                    new NotifyWidgetFailure(_T("Protocol and port must be specified."));
                    redirectTo(urlStrRedirect("shorewall/shorewall/" . $page));
                }
                else {
                    $action = $_POST['decision'];
                    $proto = $_POST['proto'];
                    $port = $_POST['port'];
                }
            }
            else {
                $action = $service . "/" . $_POST['decision'];
                $proto = "";
                $port = "";
            }

            # Source
            $sources = array();
            if ($_POST['source'] == "all") {
                foreach(getShorewallZones($src) as $zone)
                    $sources[] = $zone;
            }
            else
                $sources[] = $_POST['source'];

            # Destination
            $destinations = array();
            if ($_POST['destination'] == "all") {
                foreach(getShorewallZones($dst) as $zone)
                    $destinations[] = $zone;
            }
            else
                $destinations[] = $_POST['destination'];

            # Add rules
            foreach($sources as $src) {
                foreach($destinations as $dst) {
                    addRule($action, $src, $dst, $proto, $port);
                }
            }

            if (!isXMLRPCError()) {
                $n = new NotifyWidgetSuccess(_T("Rule added."));
                handleServicesModule($n, array("shorewall" => _T("Firewall")));
                redirectTo(urlStrRedirect("shorewall/shorewall/" . $page));
            }
            else {
                $errorStatus = false;
                new NotifyWidgetFailure(_T("Failed to add the rule."));
            }
        }
    }
    else {
        new NotifyWidgetFailure(_T("Service must be specified."));
    }
}

if (isset($_POST['brestart'])) {
    redirectTo(urlStrRedirect("shorewall/shorewall/restart_service",
                              array("page" => $page)));
}

// Display policy form

$p = new PageGenerator(_T("Policy", "shorewall"));
$p->setSideMenu($sidemenu);
$p->display();

echo '<p>' . _T("The policy applies if no rule match the request.") . '</p>';

$f = new ValidatingForm(array("id" => "policy"));
$f->push(new Table());

foreach(getPolicies() as $policy) {
    if (startsWith($policy[0], $src) && startsWith($policy[1], $dst)) {
        $label = sprintf("%s (%s) → %s (%s)", getZoneType($policy[0]), $policy[0], getZoneType($policy[1]), $policy[1]);
        $decisionTpl = new SelectItem($policy[0] . "_" . $policy[1] . "_policy");
        $decisionTpl->setElements(array(_T("Accept"), _T("Drop")));
        $decisionTpl->setElementsVal(array("ACCEPT", "DROP"));
        $decisionTpl->setSelected($policy[2]);
        $f->add(new TrFormElement($label, $decisionTpl));
    }
}

$f->pop();
$f->addButton("bpolicy", _T("Save"));
$f->display();

print '<br />';

// Rules list display

$ajax = new AjaxFilter(urlStrRedirect("shorewall/shorewall/ajax_" . $page));
$ajax->display();

$t = new TitleElement(_T("Rules"), 2);
$t->display();

$ajax->displayDivToUpdate();

// Add rule form

print '<script type="text/javascript" src="modules/shorewall/includes/functions.js"></script><br />';

$t = new TitleElement(_T("Add rule"), 2);
$t->display();

$f = new ValidatingForm(array("id" => "rule"));
$f->push(new Table());

$decisionTpl = new SelectItem("decision");
$decisionTpl->setElements(array(_T("Accept"), _T("Drop")));
$decisionTpl->setElementsVal(array("ACCEPT", "DROP"));

$f->add(new TrFormElement(_T("Decision"), $decisionTpl));

$src_zones = getZonesInterfaces($src);
if (count($src_zones) > 1) {
    $sources = array("All");
    $sourcesVals = array("all");
    foreach($src_zones as $zone) {
        $sources[] = sprintf("%s (%s)", $zone[0], $zone[1]);
        $sourcesVals[] = $zone[0];
    }
    $sourcesTpl = new SelectItem("source");
    $sourcesTpl->setElements($sources);
    $sourcesTpl->setElementsVal($sourcesVals);

    $f->add(new TrFormElement(_T("Source"), $sourcesTpl));
}
else {
    $tr = new TrFormElement(_T("Source"), new HiddenTpl("source"));
    $tr->setStyle("display: none");
    $f->add($tr, array("value" => "all"));
}

$dst_zones = getZonesInterfaces($dst);
if (count($dst_zones) > 1) {
    $destinations = array("All");
    $destinationsVals = array("all");
    foreach($dst_zones as $zone) {
        $destinations[] = sprintf("%s (%s)", $zone[0], $zone[1]);
        $destinationsVals[] = $zone[0];
    }
    $destinationsTpl = new SelectItem("destination");
    $destinationsTpl->setElements($destinations);
    $destinationsTpl->setElementsVal($destinationsVals);

    $f->add(new TrFormElement(_T("Destination"), $destinationsTpl));
}
else {
    $tr = new TrFormElement(_T("Destination"), new HiddenTpl("destination"));
    $tr->setStyle("display: none");
    $f->add($tr, array("value" => "all"));
}

$macros = getServices();
$services = array("", "Custom rule...") + $macros;
$servicesVals = array("", "custom") + $macros;
$serviceTpl = new SelectItem("service", "toggleCustom");
$serviceTpl->setElements($services);
$serviceTpl->setElementsVal($servicesVals);

$f->add(new TrFormElement(_T("Service"), $serviceTpl));
$f->pop();

$customDiv = new Div(array("id" => "custom"));
$customDiv->setVisibility(false);
$f->push($customDiv);
$f->push(new Table());

$protoTpl = new SelectItem("proto");
$protoTpl->setElements(array("", "TCP", "UDP"));
$protoTpl->setElementsVal(array("", "tcp", "udp"));

$f->add(new TrFormElement(_T("Protocol"), $protoTpl));
$f->add(
        new TrFormElement(_T("Port(s)"), new InputTpl("port", "/^([0-9:,]{1,4}|[1-5][0-9:,]{4}|6[0-4][0-9:,]{3}|65[0-4][0-9:,]{2}|655[0-2][0-9:,]|6553[0-5:,])+$/"),
                          array("tooltip" => _T("You can specify multiple ports using ',' as separator (eg: 22,34,56). Port ranges can be defined with ':' (eg: 3400:3500 - from port 3400 to port 3500)."))),
        array("value" => "")
);

$f->pop();
$f->pop();
$f->addButton("brule", _T("Add rule"));
$f->display();

if (!servicesModuleEnabled()) {
    echo '<br/>';
    $f = new ValidatingForm(array("id" => "service"));
    $f->addButton("brestart", _T("Restart service"));
    $f->display();
}
?>
