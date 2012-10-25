<?php
/*
Plugin Name: Error report
Description: Adds a page on Statcomm menu where is listed all error links produced in one day.
Author: WPGetReady
Author: http://wpgetready.com
Version: 1.0
Particular documentation: This subplugins is activated from subplugins panel.
This is a simple example of making a contained subplugin
It is very basic and it will be evolving in the following versions to reuse the Statcomm framework

We recommend to use classes to encapsulate functionality, but is just a suggestion.
*/

//Instance report
$simplePage=new errorReport();

/**
 * Main class to create the report
 */
class errorReport
{

    /**
     * Called when the class is instanciaded
     */
    function __construct()
    {
        //Fire the action to hang the page from the WP admin menu
        add_action('admin_menu', 	        array(&$this,'errorReportPage'));       //add menu pages
    }

    /**
     * Called when WP is creating the menus
     */
    function errorReportPage()
    {
           $errorRP= add_submenu_page('statComm',
                            __('Error Report','statcomm'),
                            __('Error Report','statcomm'),
                            'activate_plugins',
                            'statComm/errorreport', array(&$this, 'makeReport'));
        //Add javascript and css style to this page.
        //I don't think this going to work: it depends on something that is not static on statcomm class.
        add_action( 'admin_print_styles-'  . $errorRP,  'statPressCommunity::statcommAdminStyle' );
        add_action( 'admin_print_scripts-' . $errorRP,  'statPressCommunity::jsScriptsPage' );
    }

    /**
     * We'll reuse the view system.
     * To do that we need: a xml file (error-report.xml) two folders (classes & templates) and some classes and
     * templates borrowed from Statcomm and modified.
     */
    function makeReport()
    {
        //We set directory right here, where the subplugin resides
        $rootPath= plugin_dir_path(__FILE__) ;

        //If we receive no data, we assume report is for today
        $day= isset($_GET['day'])?$_GET['day']:"";
        //Validation test:date should be numeric, and length exactly 8 characters.
        $validDate=migrationTable::dateValid($day);

        //If we got an empty day or the date is plain invalid, we take current day as default.
        if (empty($day)|| !$validDate)
        {
            $timestamp = current_time('timestamp');
            $day = gmdate("Ymd", $timestamp);
        }

        //To pass data to the module, we'll use transient variables. The module will use the same way to get it.
        set_transient( utilities::ERROR_REPORT_DAY,$day,60);
        //The view system needs a root where there is the xml files and folders and the xml file name without extension.
        $view= new viewSystem($rootPath,"error-report");
        $view->render();

        //That's it
        //We found a limitation from subplugins to be solved in next versions. Since the graphics are relative inside
        //the plugin and also the tooltip, we cannot take advantage of this features yet.
        //It means v1.7.30 cannot display flags icons and tooltips properly. We'll solve that for 1.7.40
    }
}






