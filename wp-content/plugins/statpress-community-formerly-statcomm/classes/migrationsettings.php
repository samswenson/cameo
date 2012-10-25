<?php
/**
 * Created by WpGetReady n @2012
 * Author: Fernando Zorrilla de San Martin
 * Date: 5/22/12
 * Time: 9:42 PM
 * UPDATE: Begin creating a completely new page to avoid conflict with other options.
 * So far, there are things I should change. Not easy yet. I missed the initialization process
 * and it took me a while to figure it out (add_action('admin_init','settingsMigrationAPI::statcomm_migration_admin_init');
 * UPDATE 20120605 v17.10: check if we could set_time_limit . If is not that the case, disable migration.
 * 20120709 1.7.30: now checking Safe Mode, in those cases migration isn't possible.
 */


class settingsMigrationAPI
{
    static function getOptions()
    {
        return get_option("statcomm_migration_options");
    }

    // Draw the option page
    static function statcomm_migration_option_page() {
        $mySql= new mySql();
        $enabled= ($mySql->checkStatPressTable())?true:false;
        //if table exists, make an additional check
        if ($enabled)
        {
            $istle=self::isTimeLimitEnabled();
            $enabled=$istle[0]  && self::isSafeModeEnabled();
        }
        ?>
    <div class="wrap">
        <div id="icon-statcomm" class="icon32"><br/></div>
        <form action="options.php" method="post">
            <?php settings_fields('statcomm_migration_options'); ?>
            <?php do_settings_sections('statcomm_setting_migration_page'); ?><br/>
            <input class="button-primary" name ="Submit" type="submit" value="<?php _e("Save settings (does not start migration)",'statcomm'); ?>" />

            <div>
                <p></p>
                <?php
             echo "<strong>" . __("IMPORTANT:" ,"statcomm") . "</strong><p/>";
             echo "<strong>" . __("The migration will erase the data StatComm table before proceed","statcomm"). "</strong><p/>";
             echo __("1-We recommend you deactivate the older plugin before start migration","statcomm"). "<br/>";
             echo __("2-Select the options that fit your needs and press Save settings","statcomm"). "<br/>";
             echo __("3-To start migration press the button above (button enabled if Statpress table is present)","statcomm"). "<p/>";
             if ($enabled)
             {
             echo   '<span><a  class="button-primary" href="' . wp_nonce_url(admin_url( 'admin.php?page=statComm/statmigration' ),'statcomm'). '" >' .
                    __('START MIGRATION', 'statcomm'). '</a></span>';
             }
             else
             {
               echo  '<span><a  class="button-primary" href="#" >' .
                    __('MIGRATION DISABLED!', 'statcomm'). '</a></span>';
             }

            ?></div>
        </form>
    </div>
    <?php
    }


    /**
     * Checks if set_time_limit can be used
     * @static
     * @return array containing [0]=true if enabled, false otherwise and [1]=error msg if false
     */
    static function isTimeLimitEnabled()
    {
        ob_start();
        set_time_limit(0);
        $msg=ob_get_contents();
        ob_end_clean();
        $flag=(strlen($msg)!=0)?false:true;
        return array($flag,$msg);
    }

    /**
     * 1.7.30: sites using safe mode are unable of running migration procedures, since set_time_limit is ignored.
     * @static
     * @return bool
     */
    static function isSafeModeEnabled()
    {
        return (ini_get('safe_mode'))?true:false;
    }

    static function statcomm_migration_admin_init(){
        //register_setting(group_name,option_name,callback)
        register_setting('statcomm_migration_options',	'statcomm_migration_options',
                        'settingsMigrationAPI::statcomm_validate_migration_options');

        //add_settings_section(html_id_tag,title,callback,settings page)
        add_settings_section('statcomm_migration_main',"<h2>". __('StatComm Migration Settings Options','statcomm') . "</h2>",
                            'settingsMigrationAPI::statcomm_section_migration_text','statcomm_setting_migration_page');

        //add_settings_field(html_id_tag,text,callback,page,id_section)
        add_settings_field('statcomm_chk_migration_delete_spiders',__('Keep only user information (suppress spiders)','statcomm'),
                           'settingsMigrationAPI::statcomm_chk_mig_del_spiders',	'statcomm_setting_migration_page','statcomm_migration_main');

        add_settings_field('statcomm_input_migration_date_from',__('Suppress records before...(yyyymmdd format)','statcomm'),
                            'settingsMigrationAPI::statcomm_input_mig_date_from',	'statcomm_setting_migration_page','statcomm_migration_main');

    }

    /**
     * Chance to make preprocessing data
     * @static
     *
     */
    static function statcomm_section_migration_text() {
        //$green = "<div style='color:GREEN;'>OK</div>";
        //$red   = "<div style='color:RED;'>ERROR</div>";
        //$enabled ="<div style='color:GREEN;'>ENABLED</div>";
        //$disabled="<div style='color:GRAY;'>DISABLED</div>";

        //check if the table exists
        $mySql= new mySql();
        if ($mySql->checkStatPressTable())
        {
            echo "<p/>";
            echo "<span style='color:GREEN;'>" . __("StatPress table present: ","statcomm") . "</span>";
            //find out more about the table.
            echo "<span><strong>";
            utilities::statpressTableSize(true);
            echo "</strong></span>";
        }
        else
        {
            echo "<div style='color:RED'>" . __("No StatPress table (!!!)","statcomm") . "</div>";
        }
        $enabled=self::isTimeLimitEnabled();
        if (!$enabled[0])
        {
            echo "<div style='color:RED'>" . __("This server has set_time_limit disabled or we found an error","statcomm") . "</div>";
            echo "<div>" . __("Please check this problem with the administrator. ","statcomm") . "</div>";
            echo "<div>" . __("The migration will be disabled until the problem is cleared. ","statcomm") . "</div>";
            echo "<br/>";
            echo $enabled[1];
        }
        if (self::isSafeModeEnabled())
        {
            echo "<div style='color:RED'>" . __("This server has Safe Mode settings enabled","statcomm") . "</div>";
            echo "<div>" . __("Please check this problem with the administrator. ","statcomm") . "</div>";
            echo "<div>" . __("The migration is unable to continue since it can't set time limit for the process. ","statcomm") . "</div>";
            echo "<div>" . __("Please check  http://www.php.net/manual/en/function.set-time-limit.php for more information. ","statcomm") . "</div>";
            echo "<br/>";
        }

    }



    // Display and fill the form field
    // It's worth mentioning that ID settings in the add_settings_field DOES NOT HAVE TO MATCH ID input field HTML Tag
    // in this callback function. Just remember avoid conflicting names
    static function statcomm_chk_mig_del_spiders() {
        // get option 'text_string' value from the database
        $options = get_option('statcomm_migration_options');
        $chk =isset($options['chk_delspiders'])?$options['chk_delspiders']:'';
        $checked='';
        if($chk) { $checked = ' checked="checked" '; }
        echo "<input id='chk_delspiders' name='statcomm_migration_options[chk_delspiders]' type='checkbox' " . $checked . " />";
    }

    /**
     * In some way, should be a date selection
     * @static
     *
     */
    static function statcomm_input_mig_date_from() {
        // get option 'text_string' value from the database
        $options = get_option( 'statcomm_migration_options' );

        $cutting_date = isset($options['cutting_date'])?$options['cutting_date']:'';
        // echo the field
        echo "<input id='cutting_date' name='statcomm_migration_options[cutting_date]' type='text' value='$cutting_date' />";
    }

    // Validate user input (we want text only)
    // IMPORTANT: this function gets an array in ($input) and array out $valid. EVERY FIELD has to be copied
    // from on array to another previous validation. If not, data of fields won't be saved.
    static function statcomm_validate_migration_options( $input ) {


            if (strlen($input['cutting_date'])==8)
            {
              if (checkdate(substr($input['cutting_date'],4,2),
                  substr($input['cutting_date'],6,2),
                  substr($input['cutting_date'],0,4 )))
              {
                  $valid['cutting_date'] = $input['cutting_date']  ;
              }
              else
              {
                //  utilities::msg("INVALID DATE LONG");
                  add_settings_error('statcomm_migration_options',
                                     'statcomm_input_migrationerror',
                                     'Invalid date long (8 characters needed)',
                                     'error');
              }

            }
            else
            {
                if(empty($input['cutting_date']))
                {
                    $valid['cutting_date'] = $input['cutting_date']  ;
                }
                else
                {
                    //utilities::msg("INVALID DATE ");
                    add_settings_error('statcomm_migration_options',
                                       'statcomm_input_migrationerror',
                                       'Invalid date',
                                       'error');
                }
            }




        $valid['chk_delspiders'] =isset($input['chk_delspiders'])?$input['chk_delspiders']:"";;


         /*validation sample
        if( $valid['text_string'] != $input['text_string'] ) {
            //add_settings_error(title_setting,html_id_tag,error_msg,error/update
            add_settings_error('statcomm_text_migration_string','statcomm_migration_texterror','Incorrect value entered!','error');
        }
         */

        return $valid;
    }
}