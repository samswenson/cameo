<?php
/**
 * Created by WpGetReady n @2012
 * Author: Fernando Zorrilla de San Martin
 * Date: 15/06/12
 * Time: 07:33 PM
 */

/**
 * This class handles network settings for multisite environment.
 * The following items are set from network admin:
 * Maxmind database installation and check
 * UAParser database installation
 * Table deletion
 * Note: not settings API for network-wide settings?. WP is just beginning in this play area...
 * 1.7.30: bye bye static , since we need to build subplugin menu right under network settings.
 * These are independent.
 * -Added subplugin network control.
 */
class settingsNetwork
{
    private $subplugins;
    const NETWORK_PAGE ="statComm";
    function __construct()
    {
        $this->subplugins = new subPlugins();
        $this->subplugins->setSubpluginPage(self::NETWORK_PAGE);
    }

    function statcomm_network_page()
    {
        add_menu_page('SC Multisite',
            __('SC Multisite'       ,'statcomm'),
            'manage_network_options',  self::NETWORK_PAGE,
            array(&$this,'statcomm_network_menu'),
            plugins_url('images/statcomm-bw-16x16.png', dirname(__FILE__)));
        //This method has to be BEFORE statcomm_network_menu since the header will be sent in that method.
        $this->subplugins->subplugin_prevalidation();
    }

    function statcomm_network_menu(){

        //If no checked box in post set value to false
        if(isset($_POST['sc_submit'])){
            if(!isset($_POST['sc_setting']['chk_deltable'])) $_POST['sc_setting']['chk_deltable'] = 'false';
            $networkOptions=array();
            foreach((array)$_POST['sc_setting'] as $key => $value){//Add more sc_setting[FIELDNAME] to form for more fields
                $networkOptions[$key] = $value;
            }
            update_site_option(settingsAPI::SC_ADVANCED_OPTIONS_KEY,$networkOptions);
        }
        $options=get_site_option(settingsAPI::SC_ADVANCED_OPTIONS_KEY);
        $chk_deltable = $options['chk_deltable'];
        $settings = new settingsAPI();
        //prevalidation before rendering.
        ?>

    <div class="wrap">
        <h2>StatComm Network Settings</h2>
        <?php if(isset($_POST['sc_submit'])) : ?>
        <div id="message" class="updated fade">
            <p>
                <?php _e( 'Settings Saved', 'my' ) ?>
            </p>
        </div>
        <?php endif; ?>
        <form method="post" action="">
            <?php
            //add settings for enabling databases
            $settings->statcomm_setting_chk_uas();
            $settings->statcomm_setting_chk_glc();
            ?>
            <p style="margin-bottom:30px;">
                <input name="sc_setting[chk_deltable]" type="checkbox" <?php if($chk_deltable == 'true') echo 'checked'; ?> value="true" />
                <span class="checkbox_text">Delete Statcomm table(s) when uninstall</span><br />
            </p>
            <p>
                <input name="sc_submit" type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
            </p>
        </form>
    <?php

        $this->subplugins->subplugin_render();
    ?>
    </div><!-- /.wrap -->
    <?php
    }

}