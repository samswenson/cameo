<?php
/*
20120614-v1.7.20: optionally delete statcomm table on uninstall.
20120618: converted from static to object to easier tab initialization
20120618: Recommend to uninstall the plugin and reinstall the new version (there are lot of changes)
20120618: We are assuming a lot of things and simplifications here:
-register_settings and add_settings_field uses the same string constant and this is not necessarily true. This is an
over simplification.
20120618: Tabbed interface.
20120620: Solved problem when not showing 'Settings saved.' message . Added settings_error to the form.

NOTES:
        -to select icon for screen_icon, see wp-admin/css/colors-classic.dev.css and icon-xxxx definition
        used in wp-admin/includes/template.php Example: screen_icon('options-general');
201200703: Starting subplugin subsystem draft
20120704: First step to subplugin handlers.
20120706: Simplified settings API. moved subplugins to a independent class. Cleaning out the code.
*/

class settingsAPI
{
    Const SC_PLUGIN_VERSION         = 'statcomm_plugin_version';
    Const SC_MIGRATION_SETTINGS_KEY = 'statcomm_migration_options';
    Const SC_GENERAL_SETTINGS_KEY   = 'statcomm_options';
    Const SC_ADVANCED_OPTIONS_KEY   = 'statcomm_options_advanced';
    const SC_SUBPLUGIN              = 'statcomm_active_subplugins';
    const SC_SUBPLUGIN_MULTISITE    = 'statcomm_active_subplugins_sitewide';
    const SC_OPTIONS_KEY            = 'statComm/options'; //This key has to match exactly with the page name

    private $general_settings = array();
    private $advanced_settings = array();
    private $subplugin_settings = array();
    //private $plugin_options_key = 'statComm/options'; //This key has to match exactly with the page name
    private $sc_settings_tabs = array();
    private $subplugin;  //subplugin handler list

    function __construct()
    {
        add_action( 'admin_init', array( &$this, 'statcomm_general_options' ) );
        add_action( 'admin_init', array( &$this, 'statcomm_advanced_options' ) );
        add_action( 'admin_init', array( &$this, 'statcomm_subplugin' ) );

        $this->general_settings   = (array) get_option(  self::SC_GENERAL_SETTINGS_KEY );
        $this->advanced_settings  = (array) get_option( self::SC_ADVANCED_OPTIONS_KEY );
        $this->subplugin_settings = (array) get_option( self::SC_SUBPLUGIN );
    }

    /**
     * Don't mess with options names, they are already defined.
     * @static
     * @return mixed
     */
    static function getOptions()
    {
        return get_option(self::SC_GENERAL_SETTINGS_KEY);
    }

    /**
     * Get advanced options
     * @static
     * @return mixed
     */
    static function getOptionsAdvanced()
    {
        return get_option(self::SC_ADVANCED_OPTIONS_KEY);
    }

    /**
     * Draw the option page
     * @static
     */
    function statcomm_option_page() {
        $tab = isset( $_GET['tab'] )?$_GET['tab']:self::SC_GENERAL_SETTINGS_KEY;
        utilities::fl("current tab value", $tab);
        ?>
    <div class="wrap">
        <?php $this->plugin_options_tabs(); ?>
        <form action="options.php" method="post">
            <?php wp_nonce_field( 'update-options' ); ?>
            <?php settings_errors(); ?>
            <?php settings_fields($tab);?>
            <?php do_settings_sections($tab);?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
    }

    /*
      * Renders our tabs in the plugin options page,  walks through the object's tabs array and prints
      * them one by one. Provides the heading for the  plugin_options_page method.
      */
    function plugin_options_tabs() {
        $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : self::SC_GENERAL_SETTINGS_KEY;

        screen_icon();
        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $this->sc_settings_tabs as $tab_key => $tab_caption ) {
            $active = $current_tab == $tab_key ? 'nav-tab-active' : '';
            echo '<a class="nav-tab ' . $active . '" href="?page=' . self::SC_OPTIONS_KEY . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';
        }
        echo '</h2>';
    }

    /**
     * Initialize options settings
     * v1.7.20: When WP is single-mode, the administrator has complete control of the option Panel,
     *          When is multisite, the following options are exclusively under Network Admin control:
     *          - UAS Database Updating
     *          - Maxmind Updating
     *          - Statcomm table(s) deletion.
     * Those settings are moved to network-wide settings (to be true, only Statcomm table deletion, the other are only links)
     * 20120618:Converted to object, for easier tab initialization.
     * v1.7.20: in multisite mode , only Network Admin is capable of handling UAS and Maxmind database.
     */
    function statcomm_general_options(){

        //register_setting(group_name,option_name,callback)
        $this->sc_settings_tabs[self::SC_GENERAL_SETTINGS_KEY] = 'General';

        register_setting(self::SC_GENERAL_SETTINGS_KEY,
            self::SC_GENERAL_SETTINGS_KEY,
            array( &$this, 'sc_validate_general_options'));

        //add_settings_section(html_id_tag,title,callback,settings page)
        add_settings_section('statcomm_general_options',
            '<h2>' . __('StatComm Settings Options' ,'statcomm')  . '</h2>',
            array( &$this, 'statcomm_section_text'), self::SC_GENERAL_SETTINGS_KEY);

        //add_settings_field(html_id_tag,text,callback,page,id_section)
        add_settings_field('statcomm_chk_log',
            __('Also collect data about logged users','statcomm'),
            array( &$this, 'statcomm_setting_chk_log'),
            self::SC_GENERAL_SETTINGS_KEY, 'statcomm_general_options');

        add_settings_field('statcomm_chk_no_spiders',
            __('Do not collect spiders visits','statcomm'),
            array( &$this, 'statcomm_setting_chk_no_spiders'),
            self::SC_GENERAL_SETTINGS_KEY, 'statcomm_general_options');

        add_settings_field('statcomm_delete_visitors',
            __('Automatically delete visits older than','statcomm'),
            array( &$this, 'statcomm_setting_delete_visitors'),
            self::SC_GENERAL_SETTINGS_KEY, 'statcomm_general_options');

        add_settings_field('statcomm_delete_spiders',
            __('Automatically delete spiders older than','statcomm'),
            array( &$this, 'statcomm_setting_delete_spiders'),
            self::SC_GENERAL_SETTINGS_KEY, 'statcomm_general_options');

        add_settings_field('statcomm_overview_graph',
            __('Days in Overview graph','statcomm'),
            array( &$this, 'statcomm_overview_graph'),
            self::SC_GENERAL_SETTINGS_KEY, 'statcomm_general_options');

        add_settings_field('statcomm_capability',
            __('Min. allowed access','statcomm'),
            array( &$this, 'statcomm_capability'),
            self::SC_GENERAL_SETTINGS_KEY, 'statcomm_general_options');

        add_settings_field('statcomm_spy_days',
            __('How many days to spy back','statcomm'),
            array( &$this, 'statcomm_setting_spy_back') ,
            self::SC_GENERAL_SETTINGS_KEY, 'statcomm_general_options');

        add_settings_field('statcomm_spy_results',
            __('How many result in the spy list','statcomm'),
            array( &$this, 'statcomm_setting_spy_results'),
            self::SC_GENERAL_SETTINGS_KEY, 'statcomm_general_options');

        add_settings_field('statcomm_spy_chk_errors',
            __('Display error counter in graphic view(in red)','statcomm'),
            array( &$this, 'statcomm_setting_chk_errors'),
            self::SC_GENERAL_SETTINGS_KEY, 'statcomm_general_options');
    }

    /**
     * v1.7.20: in multisite mode, only Network Admin is capable of deleting table, and also control and update additional info databases.
     */
    function statcomm_advanced_options()
    {
        $this->sc_settings_tabs[self::SC_ADVANCED_OPTIONS_KEY] = 'Advanced';

        register_setting(self::SC_ADVANCED_OPTIONS_KEY,
            self::SC_ADVANCED_OPTIONS_KEY,
            array( &$this, 'sc_validate_advanced_options'));

        add_settings_section('statcomm_advanced_options',
            __('<h2>StatComm Settings Options</h2>','statcomm'),
            array( &$this, 'statcomm_advanced_section'),  self::SC_ADVANCED_OPTIONS_KEY);

        if (!is_multisite())  //At the same time, a warning is issued to the user(s)
        {

            add_settings_field('statcomm_chk_uas',
                __('User Agent String Database','statcomm'),
                array( &$this, 'statcomm_setting_chk_uas'),
                self::SC_ADVANCED_OPTIONS_KEY, 'statcomm_advanced_options');

            add_settings_field('statcomm_chk_gcl',
                __('Check to enable Maxmind Geolocation service','statcomm'),
                array( &$this, 'statcomm_setting_chk_glc'),
                self::SC_ADVANCED_OPTIONS_KEY,  'statcomm_advanced_options');

            add_settings_field('statcomm_chk_deltable',
                __('Also delete Statcomm table when uninstall','statcomm'),
                array( &$this, 'statcomm_setting_chk_deltable'),
                self::SC_ADVANCED_OPTIONS_KEY,  'statcomm_advanced_options');
        }
    }

    /**
     * This is the first draft to make the subplugin functionality.
     */
    function statcomm_subplugin()
    {

        //Set tab name
        $this->sc_settings_tabs[self::SC_SUBPLUGIN] = 'Sub-Plugins';

        //in multisite mode there is no management of subplugins.
        //So in this case, we skip initialization. After that we announce the user can't control the site
        //in section option
        if (!is_multisite())
        {
            //Create subplugin table, passing the page and tab we'll for redirection.
            $this->subplugin = new subPlugins();
            //Define what would be the page we would use as base redirection
            $this->subplugin->setSubpluginPage(settingsAPI::SC_OPTIONS_KEY,settingsAPI::SC_SUBPLUGIN);
            //Validate data before rendering the subplugin table
            //Can't be done later than section, after that headers will be sent and redirection can't be done.
            $this->subplugin->subplugin_prevalidation();
        }

        add_settings_section('statcomm_subplugin_options',
            '<h2>' . __('StatComm Subplugins Options' ,'statcomm')  . '</h2>',
            array( &$this, 'statcomm_subplugin_section'), self::SC_SUBPLUGIN);
    }


    function statcomm_advanced_section()
    {
        if (is_multisite())
        {
            if (current_user_can("manage_network"))
            {
                echo __("Network Administrator should control Advanced Settings from the Network console","statcomm");
                //TODO: Add a link to the network menu here.
            }
            else
            {
                echo __('This is a Multisite Wordpress installation.','statcomm');
                echo __('Advanced options is only available for Network Administrators on Multisite','statcomm');
            }
        }
        else
        {
            if (!current_user_can("activate_plugins"))
            {
                echo __('This is a Multisite Wordpress installation.','statcomm');
                echo __('Advanced Options is only available for Administrators','statcomm');
            }
        }
    }

    // Draw the section header
    //v1.6.5: Check panel
    //v1.6.80: Updated for manual
    function statcomm_section_text() {
        //echo '<p>' . __('Current settings for StatComm','statcomm') . '</p>';
        $green = "<div style='color:GREEN;'>OK</div>";
        $red   = "<div style='color:RED;'>ERROR</div>";
        //$enabled ="<div style='color:GREEN;'>ENABLED</div>";
        //$disabled="<div style='color:GRAY;'>DISABLED</div>";

        print "<div class='wrap'>";
        print "<table class='widefat'><thead><tr>";
        print "<th scope='col'>" . __('Component Checking', 'statcomm') . "</th>";
        print "<th scope='col'>" . __('Status', 'statcomm') . "</th>";
        print "</tr></thead>";
        print "</div>";

        //ZipArchive checking
        $flagZip = class_exists('ZipArchive')?$green:$red;

        //Folder writable
        $cache_dir= plugin_dir_path(dirname(__FILE__)) . 'def';
        $PARSER_CACHE = $cache_dir . '/cache.ini';
        $cacheIni = @parse_ini_file($PARSER_CACHE);
        $lastupdate = isset($cacheIni['lastupdate'])?$cacheIni['lastupdate']:0;
        $localversion= isset($cacheIni['localversion'])?$cacheIni['localversion']:0;

        //$options=self::getOptions();

        utilities::fl("time:", time());

        $flagCache = is_writable($cache_dir)?$green:$red;

        print "<tbody id='the-list'>";
        print "<tr><td>" . __('Checking if ZipArchive class is available (used for unpackage database)','statcomm') . "</td>";
        print "<td>" . $flagZip . "</td></tr>";

        print "<tr><td>" . __('Checking if folder def is writable (used for save UAS database)','statcomm') . "</td>";
        print "<td>" . $flagCache . "</td></tr>";


        print "<tr><td>" . __('Current User Agent String DB version','statcomm') . "</td>";
        if ($lastupdate==0)
        {
            print "<td><b>" . $red . __("Cache.ini not found or not readable","statcomm") . "</td></tr>";
        }
        else
        {
            print "<td><b>" . $localversion . "</b> dowloaded in date " . gmdate("Y-m-d h:i:s",$lastupdate) .   "</td></tr>";
        }

        print "</tr>\n";
        print "</table></div>";

    }

    /**
     * 1.7.30: This NEEDS TO BE FIxED or at least decided how it will work.
     * One thing is for sure: if in multisite I delete a plugin, this plugin is deleted in all subsites.
     * Is this telling you anything?
     * Subplugin functionality section
     * @return mixed
     */
    function statcomm_subplugin_section()
    {
        if (is_multisite())

        {
            if (current_user_can("manage_network"))
            {
                echo __("Network Administrator should control Subplugins from the Network console","statcomm");
                //TODO: Add a link to the network menu here.
                return;
            }
            else
            {
                echo __('This is a Multisite Wordpress installation.','statcomm');
                echo __('Advanced options is only available for Network Administrators on Multisite','statcomm');
                return;
            }
        }
        else
        {
            if (!current_user_can("activate_plugins"))
            {
                echo __('This is a Multisite Wordpress installation.','statcomm');
                echo __('Subplugins are only available for Administrators','statcomm');
                return;
             }
        }

        //Render subplugins options table
        $this->subplugin->subplugin_render();
    }

    /*
     // Display and fill the form field
     // It's worth mentioning that ID settings in the add_settings_field DOES NOT HAVE TO MATCH ID input field HTML Tag
     // in this callback function. Just remember avoid conflicting names
     */
    function statcomm_setting_chk_log() {
        // get option 'text_string' value from the database
        $options = self::getOptions();
        $chk =isset($options['chk_logged'])?$options['chk_logged']:'';
        $checked='';
        if($chk) { $checked = ' checked="checked" '; }
        echo "<input id='chk_logged' name='" . self::SC_GENERAL_SETTINGS_KEY. "[chk_logged]' type='checkbox' $checked />";
    }

    function statcomm_setting_chk_no_spiders() {
        // get option 'text_string' value from the database
        $options = self::getOptions();
        $chk = isset($options['chk_no_spiders'])?$options['chk_no_spiders']:'';
        $checked='';
        if($chk) { $checked = ' checked="checked" '; }
        echo "<input id='chk_no_spiders' name='" . self::SC_GENERAL_SETTINGS_KEY. "[chk_no_spiders]' type='checkbox' $checked />";

    }

    function statcomm_setting_delete_visitors() {
        $options = self::getOptions();
        $items = array("Never delete!", "1 month", "3 months", "6 months", "1 year");
        echo "<select id='cmb_delete_visitors' name='" . self::SC_GENERAL_SETTINGS_KEY. "[cmb_delete_visitors]'>";
        foreach($items as $item) {
            $selected = ($options['cmb_delete_visitors']==$item) ? 'selected="selected"' : '';
            echo "<option value='$item' " . $selected . ">$item</option>";
        }
        echo "</select>";
    }

    function statcomm_setting_delete_spiders() {
        $options = self::getOptions();
        $items = array("Never delete!", "1 Day", "1 Week", "1 Month", "1 Year");
        echo "<select id='cmb_delete_spiders' name='" . self::SC_GENERAL_SETTINGS_KEY. "[cmb_delete_spiders]'>";
        foreach($items as $item) {
            $selected = ($options['cmb_delete_spiders']==$item) ? 'selected="selected"' : '';
            echo "<option value='$item' " . $selected . ">$item</option>";
        }
        echo "</select>";
    }

    function statcomm_overview_graph() {
        $options = self::getOptions();
        $items = array("7", "10", "20", "30", "50");
        echo "<select id='cmb_overview_graph' name='" . self::SC_GENERAL_SETTINGS_KEY. "[cmb_overview_graph]'>";
        foreach($items as $item) {
            $selected = ($options['cmb_overview_graph']==$item) ? 'selected="selected"' : '';
            echo "<option value='$item' " . $selected . ">$item</option>";
        }
        echo "</select>";
    }

    //Improved: no more indescifrable minimum capability.
    function statcomm_capability() {
        $options = self::getOptions();
        $items = array( array ("Super Admin","manage_network")
        ,   array ("Administrator", "activate_plugins")
        ,   array ("Editor", "moderate_comments")
        ,   array ("Author", "edit_published_posts")
        ,   array ("Contributor", "edit_posts")
        ,   array ("Suscriber","read") );

        echo "<select id='cmb_capability' name='" . self::SC_GENERAL_SETTINGS_KEY. "[cmb_capability]'>";
        foreach($items as $item) {
            //v1.6.5:capability control
            if (current_user_can($item[1])) //Only show what you can
            {
                $selected = ($options['cmb_capability']==$item[1]) ? 'selected="selected"' : '';
                echo "<option value='{$item[1]}' " . $selected . ">{$item[0]}</option>";
            }
        }
        echo "</select>";
        echo "<div> " . _e("Need help about roles?","statcomm") . "<a target='_blank' href='http://codex.wordpress.org/Roles_and_Capabilities#Summary_of_Roles'>Go to Wordpress Codex Help: Roles & Capabilities</a></div>";
    }

    /**
     * v1.6.80 Changed to manual procedure. Much more manageable than previous version(s)
     * @static
     *
     */
    function statcomm_setting_chk_uas() {

        if (utilities::uaParserEnabled()){
            $isUpdated=utilities::UASIsUpdated(); //return array(boolean,string);
            $msg   = isset($isUpdated['msg'])?$isUpdated['msg']:"";
            if (!$isUpdated['status'])
            {
                //Check the msg, if the msg <>'' then there is an error
                if (!empty($msg))
                {
                    echo __('There was an error when checking if updated:', $msg);
                }
                else
                {
                    echo                '<a href="' . wp_nonce_url(admin_url( 'admin.php?page=statComm/uasupdate' ),'statcomm'). '" >' .
                        __('A new version of UAS database is available. Click here to start', 'statcomm'). '</a></span>';
                }
            }
            else
            {
                echo "<span style='color:GREEN;'>" .__('  UAS database installed and ready to go. ','statcomm') . "</span>" ;
            }
        }
        else
        {
            echo "<span style='color:RED;'>" . __('  You need to install UAS database to get started.', 'statcomm') .
                '<a href="' . wp_nonce_url(admin_url( 'admin.php?page=statComm/uasupdate' ),'statcomm'). '" >' .
                __('Click to proceed (it will start automatically)', 'statcomm'). '</a></span>';
        }
        echo "<p/>";
    }

    /**
     * link to Maxmind update
     * 1.7.20 - check disabled since it has no purpose.
     * 1.7.40 - Additional validation for Maxmind corrupted database
     * @static
     *
     */
    function statcomm_setting_chk_glc() {

        $geoLocation= utilities::geoLocationEnabled();
        if ($geoLocation==utilities::ERROR_NONE)
        {
            echo "<span style='color:GREEN;'>" .__('  Maxmind database installed and ready to go. ','statcomm') .
                '<a href="' . wp_nonce_url(admin_url( 'admin.php?page=statComm/glcupdate' ),'statcomm'). '" >'.
                __('Check if you need to update (it will start automatically)', 'statcomm'). '</a></span>';
        }
        else
        {
            //Possibly corrupted, recheck if we could open the file.
            if ($geoLocation !=utilities::ERROR_FILE_NOT_FOUND) {
                echo "<span style='color:RED;'>" . __(' Unable to open Maxmind database(corrupted?).', 'statcomm') .
                    '<a href="' . wp_nonce_url(admin_url( 'admin.php?page=statComm/glcupdate' ),'statcomm'). '" >'.
                    __('Click to proceed (it will start automatically)', 'statcomm'). '</a></span>';

            }
            else{
                echo "<span style='color:RED;'>" . __('  You need to install Maxmind database to get started.', 'statcomm') .
                    '<a href="' . wp_nonce_url(admin_url( 'admin.php?page=statComm/glcupdate' ),'statcomm'). '" >'.
                    __('Click to proceed (it will start automatically)', 'statcomm'). '</a></span>';
            }

        }
        echo "<p/>";
    }

    /**
     * Decide how many days back the spy tool has to search
     * @static
     */
    function statcomm_setting_spy_back()
    {
        $options = self::getOptions();
        $items = array (array (__("2 days (default)","statcomm"), "2" )
        ,       array (__("1 week","statcomm"), "7")
        ,       array (__("1 month","statcomm"),"31")
        ,       array (__("1 year (are you sure?)","statcomm"), "365")
        );

        echo "<select id='cmb_spy_back' name='" . self::SC_GENERAL_SETTINGS_KEY. "[cmb_spy_back]'>";
        foreach($items as $item) {
            $uar=isset($options['cmb_spy_back'])?$options['cmb_spy_back']:"";
            $selected = ($uar==$item[1]) ? 'selected="selected"' : '';
            echo "<option value='$item[1]' " . $selected . ">{$item[0]}</option>";
        }
        echo "</select>";
        echo __(" Spy Tool:This setting defines how many days go back in the past looking for activity user.","statcomm");
    }

    function statcomm_setting_spy_results()
    {
        $options = self::getOptions();
        $items = array (array (__("5 results (default)","statcomm"), "5" )
        ,       array (__("10 results","statcomm"), "10")
        ,       array (__("15 results","statcomm"),"15")
        ,       array (__("20 results","statcomm"),"20")
        );

        echo "<select id='cmb_spy_results' name='" . self::SC_GENERAL_SETTINGS_KEY. "[cmb_spy_results]'>";
        foreach($items as $item) {
            $uar=isset($options['cmb_spy_results'])?$options['cmb_spy_results']:"";
            $selected = ($uar==$item[1]) ? 'selected="selected"' : '';
            echo "<option value='$item[1]' " . $selected . ">{$item[0]}</option>";
        }
        echo "</select>";
        echo __(" Spy Tool:How many activity results when looking back for a user.","statcomm");
    }

    function statcomm_setting_chk_errors() {
        // get option 'text_string' value from the database
        $options = self::getOptions();
        $chk =isset($options['chk_errors'])?$options['chk_errors']:'';
        $checked='';
        if($chk) { $checked = ' checked="checked" '; }
        echo "<input id='chk_errors' name='" . self::SC_GENERAL_SETTINGS_KEY. "[chk_errors]' type='checkbox' $checked/>";
    }

    /**
     * v1.1.7.20: moved delete table to advance tab.
     * current availability:
     * If not multisite:
     *      -Only available for administrators
     * If multisite:
     *      -Only available for network administrators.
     */
    function statcomm_setting_chk_deltable() {
        // get option 'text_string' value from the database
        $options = self::getOptionsAdvanced();
        $chk =isset($options['chk_deltable'])?$options['chk_deltable']:'';
        $checked='';
        if($chk) { $checked = ' checked="checked" '; }
        echo "<input id='chk_deltable' name='" . self::SC_ADVANCED_OPTIONS_KEY. "[chk_deltable]' type='checkbox' $checked/>";
    }


    // Validate user input (we want text only)
    // IMPORTANT: this function gets an array in ($input) and array out $valid. EVERY FIELD has to be copied
    // from on array to another previous validation. If not, data of fields won't be saved.

    /**
     * Validate options
     * v1.7.10_ every time we save options we reset lazy cache.
     * v1.7.20  removed check option on Maxmind database, since there was no point to enable/disable maxmind db.
     * @static
     * @param $input
     * @return array
     */
    function sc_validate_general_options( $input ) {
        utilities::fl("validate general options fired!");
        $valid['chk_logged']          = isset($input['chk_logged'])?$input['chk_logged']:"";
        $valid['chk_no_spiders']      = isset($input['chk_no_spiders'])?$input['chk_no_spiders']:"";
        $valid['cmb_delete_visitors'] = isset($input['cmb_delete_visitors'])?$input['cmb_delete_visitors']:"";
        $valid['cmb_delete_spiders']  = isset($input['cmb_delete_spiders'])?$input['cmb_delete_spiders']:"";
        $valid['cmb_overview_graph']  = isset($input['cmb_overview_graph'])?$input['cmb_overview_graph']:"";
        $valid['cmb_capability']      = isset($input['cmb_capability'])?$input['cmb_capability']:"";
        $valid['cmb_spy_back']        = isset($input['cmb_spy_back'])?$input['cmb_spy_back']:"";
        $valid['cmb_spy_results']     = isset($input['cmb_spy_results'])?$input['cmb_spy_results']:"";
        $valid['chk_errors']          = isset($input['chk_errors'])?$input['chk_errors']:"";
        mysql::resetSummaryTable();
        return $valid;
    }

    /**
     * Advanced settings validation (currently one parameter)
     * @param $input
     * @return array
     */
    function sc_validate_advanced_options($input)
    {
        utilities::fl("validate advanced options fired!");
        $valid['chk_deltable']        = isset($input['chk_deltable'])?$input['chk_deltable']:"";
        return $valid;
    }

    /**
     * Defines default values when initializing the plugin
     * @static
     * v1.7.00: updated to include version control. Used for upgrades.
     * Updated: new statcomm_plugin_version independent of statcomm options
     * v1.7.20: removed check option on Maxmind database.
     *          added advanced options.
     */
    static function defaultValues()
    {
        //Default values. Values not present usually are chk_xxx values (boolean) default to false.
        $options= array(
            'chk_logged' => false
        ,     'chk_no_spiders' => false
        ,     'cmb_delete_visitors' => 'Never delete!'
        ,     'cmb_delete_spiders' => 'Never delete!'
        ,     'cmb_overview_graph' => '10'
        ,     'cmb_capability' => 'activate_plugins'
        ,     'cmb_spy_back'   =>'2'
        ,     'cmb_spy_results'=>'5'
        ,     'chk_errors' => false
        );
        update_option(self::SC_GENERAL_SETTINGS_KEY, $options);

        //save plugin version, used when upgradin the plugin.
        update_option(self::SC_PLUGIN_VERSION , utilities::PLUGIN_VERSION);

        //Migration settings
        $migration_options= array(
            'chk_delspiders' => ''
        ,   'cutting_date'   => ''
        );
        update_option(self::SC_MIGRATION_SETTINGS_KEY, $migration_options);

        //Advanced settings. Set local if not multisite, global otherwise
        $options_advanced=  array(
            'chk_deltable' => false
        );
        if (is_multisite())
        {
            update_site_option (self::SC_ADVANCED_OPTIONS_KEY,$options_advanced);
        }
        else
        {
            update_option (self::SC_ADVANCED_OPTIONS_KEY,$options_advanced);
        }

    }
} //620