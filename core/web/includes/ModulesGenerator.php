<?php
/**
 * (c) 2004-2006 Linbox / Free&ALter Soft, http://linbox.com
 *
 * $Id$
 *
 * This file is part of LMC.
 *
 * LMC is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * LMC is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LMC; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
?>
<?php






/**
 * Singleton objet, register all main lmc data
 */
class LMCApp {


   var $_modules;
   var $_styleobj;
   /**
    * Constructor
    * private
    */
   function LMCApp() {
        $this->_modules = array();
        $this->_styleobj = new StyleGenerator();
   }

   /**
    * getInstance (return uniq object)
    * fake static element (due to php4 bad object integration) with
    * a global value
    */
   function &getInstance() {

      if (!isset($GLOBALS["__INSTANCE_LMC_APP__"])) {
         $GLOBALS["__INSTANCE_LMC_APP__"] = new LMCApp();
      }

      return $GLOBALS["__INSTANCE_LMC_APP__"];

   }

   /**
    * @brief this function return uniq style object
    * this is used to add CSS in an uniq part of the page
    * instead of flooding fragmented css in whole page.
    * styleobject id display at the end of the page.
    * @return unique style object
    */
   function &getStyle() {
       return $this->_styleobj;
   }

   /**
    * add a module into LMCApp
    * @param mod Module Object to add
    */
   function addModule($mod) {
       $this->_modules[$mod->getName()]= $mod;
   }

   /**
    * @brief return all modules list
    * @return an associeted array like
            'base' => 'base object',
            'samba'=> 'samba object'
    */
   function &getModules() {
       return $this->_modules;
   }

   /**
    * @brief return a specific module
    * @param $modname name
    * @return Module Object
    * @warning if Module name do not exist... unknown value is return
    */
   function &getModule($modname) {
        return $this->_modules[$modname];
   }

   /**
    * function called at very end of each page (css creation, etc...)
    */
   function render() {
        $this->_styleobj->render();
   }

   /**
    * This function process all Modules
    * Create old array for retro compatibility etc...
    */
   function process() {
        foreach ($this->getModules() as $module) {
            $module->process();
        }
   }
}

/**
 * Object dedicated to style creation
 */
class StyleGenerator {
    var $_csslines;


    /**
     * Default constructor
     */
    function StyleGenerator() {
        $this->_csslines = array();
    }

    /**
     * add a css line into StyleGenerator
     * @param $css cssline to add
     * @warning "\n" value at end of line is not an obligation
     */
    function addCSS($css) {
        $this->_csslines[] = $css;
    }

    /**
     * display all css line on same part of code
     */
    function render() {
        print "<style type=\"text/css\">\n";
        foreach ($this->_csslines as $line) {
            print "$line\n";
        }
        print "</style>\n";
    }
}


class SubModule {
    var $_name; /**< submodule name */
    var $_desc; /**< submodule description */
    var $_pages; /**< pages array */
    var $_defaultpage; /**< default page. ex: 'base/user/index' */
    var $_visibility; /**< default is visible */
    var $_img; /**< img, help generation of css */
    var $_imgsize; /**< tab size */
    var $_parentname; /**< Module parent name */
    var $_alias; /**< alias icon if submod not appear */
    var $_priority; /**< specify order to show submod */


    function SubModule($name,$desc = "") {
        $this->_name = $name;
        $this->setDescription($desc);
        $this->_visibility = True;
        $this->_defaultpage = Null;
        $this->_img = Null;
        $this->_imgsize = 70;
        $this->_parentname = Null;
        $this->_alias = Null;
        $this->_priority = 50; //default priority
    }

    /**
     * provide alias to submod
     * ex: when you select "machines", shares is selected
     * @param $alias alias name. Correspond to an existing submodule name.
     */
    function setAlias($alias) {
        $this->_alias = $alias;
    }

    /**
     * @brief set image for navbar
     * @param $img image short path
     * if you specify 'img/foo/bar'
     * 3 files must exist
     * img/foo/bar.png for normal status
     * img/foo/bar_hl.png for highlight status
     * img/foo/bar_select.png for selected status
     */
    function setImg($img) {
        $this->_img =$img;
    }

    /**
     * specify tab size (usually used for long icons or
     * long text behind icons)
     * @param $int integer. Default size is 70
     */
    function setImgSize($int) {
        $this->_imgsize = $int;
    }

    function getName() {
        return $this->_name;
    }
    function addPage($page) {
        $this->_pages[$page->_action] = $page;
    }

    function getPage($action) {
        return $this->_pages[$action];
    }

    function getPages() {
        return $this->_pages;
    }

    function hasVisible() {
        foreach ($this->_pages as $page) {
            if ($page->isVisible())
                return true;
        }
        return false;
    }

    function setDefaultPage($page) {
        $this->_defaultpage = $page;
    }

    function setDescription($desc) {
        $this->_desc = $desc;
    }

    function getDescription() {
        return $this->_desc;
    }

    function setVisibility($bool) {
        $this->_visibility = $bool;
    }

    function getPriority() {
        return $this->_priority;
    }

    function setPriority($prio) {
         $this->_priority = $prio;
    }

    /**
     * this function provide compatibility with old ACL,
     * infoPackage.inc.php system
     * generate css for all subproc
     */
    function process($module) {
        foreach ($this->_pages as $page) {
            $page->process($module,$this->getName());
        }
        $LMC =&LMCApp::getInstance();

        $selected = ($_GET['submod'] == $this->getName());

        if ($this->_visibility!=True&&$selected) {
            if ($this->_alias!=Null) {

                $tmp = $_GET["submod"];
                $_GET["submod"] = $this->_alias; //fake url
                $parent = &$LMC->getModule($this->_parentname);
                $submod = &$parent->getSubmod($this->_alias);
                $submod->process($module);

                $_GET["submod"] = $tmp;
                return;
            }
        }
        if ($this->_img!=Null) {


            if (!$selected) {
                $css = '#navbar ul li#'.$this->getName().' { 				width: '.$this->_imgsize.'px; }
                #navbar ul li#'.$this->getName().' a {         background: url("'.$this->_img.'.png") no-repeat transparent;
                                        background-position: 50% 10px;}
                #navbar ul li#'.$this->getName().' a:hover {   background: url("'.$this->_img.'_hl.png") no-repeat transparent;
                                        background-position: 50% 10px	}';
            } else {
                $css = '#navbar ul li#'.$this->getName().' { 				width: '.$this->_imgsize.'px; }
                #navbar ul li#'.$this->getName().' a {         background: url("'.$this->_img.'_select.png") no-repeat transparent;
                border-top: 2px solid #D8D8D7;
                border-left: 1px solid #B2B2B2;
                border-right: 1px solid #B2B2B2;
                border-bottom: 3px solid #FF0000;
                background-color: #F2F2F2;
                color: #EE5010;
                                        background-position: 50% 8px;}
                #navbar ul li#'.$this->getName().' a:hover {   background: url("'.$this->_img.'_select.png") no-repeat transparent;
                background-color: #F2F2F2;
                color: #EE5010;

                                        background-position: 50% 8px	}';
            }

            $style = &$LMC->getStyle();
            $style->addCSS($css);
        }

    }

    function generateNavBar() {
        if (($this->_visibility == False)||(!hasCorrectModuleAcl($this->_parentname))) {
            return;
        }
        global $root;
        list($module,$submod,$action) = split('/',$this->_defaultpage,3);
        print "<li id=\"".$this->getName()."\"><a href=\"".$root."main.php?module=$module&submod=$submod&action=$action\">\n";
        print $this->_desc."</a>\n";
        //var_dump($this->_defaultpage);
    }
}

class ExpertSubModule extends SubModule {
    function generateNavBar() {
        if ($_SESSION["expert_mode_var"]) {
            parent::generateNavBar();
        }

    }




}

/**
 * define Modules
 */
class Module {
    var $_name; /**< module name */
    var $_version;
    var $_apiversion;
    var $_revision;
    var $_submod;
    var $_acl;
    var $_priority;


    function Module($name) {
        $this->_name = $name;
        $this->_pages = array();
        $this->_submod = array();
        $this->_priority = 50;
    }

    function __toString() {
        return "default module";
    }


    /**
     * global setter/getter section
     */
    /**
     * get module name
     */
    function getName() {
        return $this->_name;
    }
    /**
     * set revision
     */
    function setRevision($rev) {
        // STAY FOR COMPATIBILITY REASON
        global $__revision;
        $__revision[$this->getName()]=$rev;

        $this->_revision = $rev;
    }

    /**
     * set version
     */
    function setVersion($ver) {
        // STAY FOR COMPATIBILITY REASON
        global $__version;
        $__version[$this->getName()]=$ver;

        $this->_version = $ver;
    }

    /**
     * set version api
     */
    function setAPIVersion($ver) {
        // STAY FOR COMPATIBILITY REASON
        global $__apiversion;
        $__apiversion[$this->getName()]=$ver;

        $this->_apiversion = $ver;
    }

    /**
     * get revision number
     */
    function getRevision() {
        return $this->_revision;
    }

    /**
     * get version number
     */
    function getVersion() {
        return $this->_version;
    }

    /**
     * get api version number
     */
    function getAPIVersion() {
        return $this->_apiversion;
    }

    function addACL($aclname,$description) {
        //for compatibility
        global $aclArray;
        $aclArray[$this->getName()][$aclname] = $description;

        $this->_acl[$aclname] = $description;
    }

    function &addSubmod($sub) {
        $sub->_parentname = $this->getName();
        $this->_submod[$sub->getName()] = &$sub;
    }

    function &getSubmod($subname) {
        return $this->_submod[$subname];
    }

    function &getSubmodules() {
        return $this->_submod;
    }

    function hasVisible() {
        foreach ($this->_submod as $submod) {
            if ($submod->hasVisible())
                return true;
        }
        return false;
    }

    function getPriority() {
        return $this->_priority;
    }

    function setPriority($prio) {
         $this->_priority = $prio;
    }


   function process() {
        foreach ($this->_submod as $submod) {
            $submod->process($this->getName());
        }
   }

   function setDescription($desc) {
       $this->_desc = $desc;
   }

   function getDescription() {
     $desc = $this->_desc;
     if (!$desc) {
        return $this->getName();
     }
     return $desc;
   }

}

/**
 * define Modules
 */
class Page {
    var $_action;

    var $_desc;
    var $_options;
    var $_file;

    function Page($action,$desc = "") {
        $this->_action = $action;
        $this->_noheader = 0;

        $this->setDescription($desc);
        $this->setFile();
    }

    function setDescription($desc) {
        $this->_desc = $desc;
    }

    function getDescription() {
        return $this->_desc;
    }

    function getOptions() {
        return $this->_options;
    }

    function isVisible() {
        if (!isset($this->_options['visible'])) { //Default value
            return true;
        }
        return $this->_options['visible'];
    }

    function getAction() {
        return $this->_action;
    }

    /**
     *  @param options: array can contain "noHeader", "AJAX", "noACL"
     * ex  $options = array("noHeader" => True, "noACL" => True)
     * AJAX implicititely define  noHeader => True and noACL => true (AJAX reply cannot contain header)
     *
     * default: all options set to "False"
     */
    function setFile($file = False,$options = array()) {
        $this->_file = $file;
        $this->_options = $options;
    }

    /**
     * @see setFile
     * @param $options same as describe in setFile member
     */
    function setOptions($options = array()) {
        $this->_options = $options;
    }

    //function for compatibility
    function process($module,$submod) {
        global $descArray;
        $descArray[$module][$submod][$this->_action] = $this->_desc;

        $file = $this->_file;
        $options = $this->_options;


        if ($file == False) { //if we not set a file
            $file = 'modules/'.$module.'/'.$submod.'/'.$this->_action.'.php';
        }

        if ($options["noHeader"] == True) {
            global $noheaderArray;
            $noheaderArray[$module][$submod][$this->_action] = 1;

        }

        if ($options["AJAX"] == True) {
            global $noheaderArray;
            $noheaderArray[$module][$submod][$this->_action] = 1;

            global $redirAjaxArray;
            global $redirArray;
            $redirAjaxArray[$module][$submod][$this->_action] = $file;
            unset($redirArray[$module][$submod][$this->_action]);
        } else {
            global $redirArray;
            $redirArray[$module][$submod][$this->_action] = $file;
        }


        if ($options["noACL"] == True || $options["AJAX"] == True) {

            global $noAclArray;
            $noAclArray[$module][$submod][$this->_action] = 1;
        }
    }

}
?>