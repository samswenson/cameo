<?php
/**
 * Created by WpGetReady
 * User: Fernando Zorrilla de S.M.
 * Date: 20/04/12
 * Time: 05:59 PM
 * Version 0.1: this uninstall does not delete tables from the plugin, only options
 * 1.7.20: table uninstall and network tables uninstall
 * 1.7.30: delete subplugin options.
 */

// If uninstall not called from WordPress exit
if( !defined( 'WP_UNINSTALL_PLUGIN' ) )
    exit ();

require_once(dirname(__FILE__). '/classes/settings.php'); 		    //settings class based on Settings API
require_once(dirname(__FILE__). '/classes/mysql.php'); 			    //database interface class
require_once(dirname(__FILE__). '/classes/utilities.php'); 			    //database interface class

global $wpdb;
$canDeleteTable=true;
if (is_multisite()) {
    // Loop around all the site blogs, but first if we have to delete the statcomm tables.
    $options=get_site_option(settingsAPI::SC_ADVANCED_OPTIONS_KEY);
    $deleteTable=(!empty($options['chk_deltable']))?true:false;
    $blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));
    foreach ($blogids as $blog_id) {
        switch_to_blog($blog_id);
        $canDeleteTable= $canDeleteTable && statcomm_uninstall_site($deleteTable);
        utilities::fl ("blodid: $blog_id , $canDeleteTable");
    }
    delete_site_option (settingsAPI::SC_ADVANCED_OPTIONS_KEY);
    delete_site_option (settingsAPI::SC_SUBPLUGIN_MULTISITE);

    if (!$canDeleteTable)
    {
        $errors = __("StatComm was unable to delete some table(s) from the network. <b>%s</b> created.<br/>" .
            "Check if current database user has enough privileges to do so.<br/>" .
            "All others settings were deleted.<br/>" .
            "Click back in your browser to go the options settings, and try to remove the tables manually<br/>" .
            "or ask an administrator to do it.","statcomm")  ;
        wp_die($errors);
    }
    return; //end multisite uninstall
}
$options=get_option(settingsAPI::SC_ADVANCED_OPTIONS_KEY);
$deleteTable=(!empty($options['chk_deltable']))?true:false;
$canDeleteTable=statcomm_uninstall_site($deleteTable);
delete_option (settingsAPI::SC_ADVANCED_OPTIONS_KEY);
if (!$canDeleteTable)
{
    $errors = __("StatComm was unable to delete the statcomm table. <b>%s</b> created.<br/>" .
        "Check if current database user has enough privileges to do so.<br/>" .
        "All other settings were deleted.<br/>" .
        "Click back in your browser to go the options settings, and try to remove the tables manually<br/>" .
        "or ask an administrator to do it.","statcomm")  ;
    wp_die($errors);
}



/**
 * Uninstall options and data from one site
 * @return mixed
 */
function statcomm_uninstall_site($deleteTable=false)
{
    global $wpdb;
    $couldDelete=true;
    if($deleteTable)
    {
        $table = $wpdb->prefix."statcomm";
        $wpdb->query("DROP TABLE IF EXISTS $table");
        //Check if the table exists, if it does, the user has not privileges to delete the table

        if($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table)
        {
            utilities::fl("table:->$table");
            $couldDelete=false;
        }
        utilities::fl("DROP TABLE IF EXISTS $table");
        utilities::fl("couldDelete: $couldDelete");
    }

    delete_option('widget_statcomm_toppost_widget');
    delete_option('widget_statcomm_print_widget');
    delete_option(settingsAPI::SC_PLUGIN_VERSION);
    delete_option(settingsAPI::SC_MIGRATION_SETTINGS_KEY);
    delete_option(settingsAPI::SC_GENERAL_SETTINGS_KEY);
    delete_option(settingsAPI::SC_ADVANCED_OPTIONS_KEY);
    delete_option(settingsAPI::SC_SUBPLUGIN);

    mySql::resetSummaryTable();
    return $couldDelete;
}
?>