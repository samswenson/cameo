<?php
/**
 * Subplugins core. This code is based in a simplification from the code found in wp-admin/plugins.php
 * We working with the barebones functionality to keep things as simple as possible
In this current stage:
 -A subplugin cannot be edited or be deleted, only activated and/or deactivated.
 -If a subplugin is deleted manually, automatically is deactivated  (it could happen in a update)
 -Subplugin can't be searched (at least this functionality is discarded in first versions)
 -Subplugin list is not paginated  (idem)
 -Not possible to filter by activated/deactivated
 -There is not a recent list of activated/deactivated
 - There is not automatically updates and no updates at all, only when Statcomm plugin updates.
 - There is not actions/filters usually found in the WP core unless strictly necessary.
 * -It is initialized Statcomm plugin
 */


/**
 * The process is divided in two parts:
 * Prevalidation: validates incoming data and decision.
 * Render: draw the table
 * This division has a reason: validation could cause redirections. Redirections are not handled if we
 * send data to the browser. Redirections also are not handled in settingsAPI section so be warned.
 * After starting rendering, we cannot redirect.
 * 20120710: Dynamic link generation to call subplugins table from network and from tabs. That implied pass two
 * additional parameters to the constructor, but in the end it worth it.
 */

class subPlugins
{
    //Custom definitions for subplugin handling
    private $subplugin_page;
    private $subplugin_folder;

    private $wp_list_table;
    private $plugin; //auxiliar variable to pass data from two splitted methods.
    
    function __construct()
    {
        //Build
        //$this->subplugin_page  ="admin.php?page=" . settingsAPI::SC_OPTIONS_KEY . "&tab=" . settingsAPI::SC_SUBPLUGIN ;

        //Build the link which where we redirect for most commands. That will be used for ALL redirections and
        //for making the subplugin table
        $this->subplugin_folder= plugin_dir_path(dirname(__FILE__)) . "subplugins" ;
    }

    /**
     * Defines page where we do the redirection.
     * this is used to reuse the table in settings and network
     * @param $pageName
     * @param string $tabName
     */
    function setSubpluginPage($pageName,$tabName="")
    {
        $this->subplugin_page  ="admin.php?page=" . $pageName;
        if(!empty($tabName))
        {
            $this->subplugin_page .= "&tab=" . $tabName;
        }
    }

    /**
     * Data validation before rendering the table.
     * NO html can be echoed in this method.
     * @return WP_Subplugins_List_Table
     */
    function subplugin_prevalidation()
    {
        /* This code would imply different user level could run the plugin.
        There is a security concern that will be addressed n 1.7.50 version.
        if ( !current_user_can('activate_plugins') )
            wp_die( __( 'You do not have sufficient permissions to manage plugins for this site.','statcomm' ) );
        */
        $this->wp_list_table =new WP_Subplugins_List_Table($this->subplugin_page);
        $action = $this->wp_list_table->current_action();
        //no action, nothing to process
        if (!$action) return;

        $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';

        // Clean up request URI from temporary args for screen options/paging uri's to work as expected.
        //TODO: clean and simplify
        $_SERVER['REQUEST_URI'] = remove_query_arg(array('error', 'deleted', 'activate', 'activate-multi', 'deactivate', 'deactivate-multi', '_error_nonce'), $_SERVER['REQUEST_URI']);

        //Process action

        switch ( $action ) {
            case 'activate':
                //Check if invoke is from admin
                check_admin_referer('activate-plugin_' . $plugin);
                //Try to activate, return error if there was a problem
                $result = $this->activate_subplugin($plugin, self_admin_url( $this->subplugin_page . '&error=true&plugin=' . $plugin), is_network_admin() );
                if ( is_wp_error( $result ) ) {
                    //Handle error if there was one
                    if ( 'unexpected_output' == $result->get_error_code() ) {
                        $redirect = self_admin_url($this->subplugin_page .  '&error=true&charsout=' . strlen($result->get_error_data()) . '&plugin=' . $plugin);
                        wp_redirect(add_query_arg('_error_nonce', wp_create_nonce('plugin-activation-error_' . $plugin), $redirect));
                        exit;
                    } else {
                        wp_die($result);
                    }
                }
                //No error, go to admin page and flag activation successful.
                wp_redirect( self_admin_url($this->subplugin_page . "&activate=true") );
                exit;break;
            case 'activate-selected':
                check_admin_referer('bulk-plugins');
                $plugins = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : array();

                // Only activate plugins which are not already active.
                //Important to take a decision: the subplugin mean to be activated in normal and multisite environment.
                // But in what scenarios the code should or shouldn't be network activated?

                foreach ( $plugins as $i => $plugin )
                {
                    if(is_network_admin())
                    {
                        if ( subPlugins::is_subplugin_active_for_network( $plugin ) )
                            unset( $plugins[ $i ] );
                    }
                    else
                    {
                        if ( self::is_subplugin_active( $plugin ) )
                            unset( $plugins[ $i ] );
                    }
                }

                if ( empty($plugins) ) {
                    wp_redirect( self_admin_url($this->subplugin_page ) );
                    exit;
                }

                $this->activate_subplugins($plugins, self_admin_url($this->subplugin_page . '&error=true'), is_network_admin() );
                wp_redirect( self_admin_url( $this->subplugin_page .  "&activate-multi=true") );
                exit;break;
            case 'error_scrape': //TODO: in whichf condition this option is fired?
                check_admin_referer('plugin-activation-error_' . $plugin);
                $valid =$this->validate_subplugin($plugin);
                if ( is_wp_error($valid) )
                    wp_die($valid);

                if ( ! WP_DEBUG ) {
                    error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );
                }

                @ini_set('display_errors', true); //Ensure that Fatal errors are displayed.
                // Go back to "sandbox" scope so we get the same errors as before

                define ("WP_SUBPLUGIN_DIR",$this->subplugin_folder);
                function subplugin_sandbox_scrape( $plugin ) {
                    include( WP_SUBPLUGIN_DIR . '/' . $plugin );
                }
                subplugin_sandbox_scrape( $plugin );
                exit; break;
            case 'deactivate':
                check_admin_referer('deactivate-plugin_' . $plugin);
                if ( ! is_network_admin() && subPlugins::is_subplugin_active_for_network( $plugin ) ) {
                    wp_redirect( self_admin_url($this->subplugin_page ) );
                    exit;
                }

                $this->deactivate_subplugins( $plugin, false, is_network_admin() );
                wp_redirect( self_admin_url( $this->subplugin_page . "&deactivate=true") );
                exit;break;
            case 'deactivate-selected':
                check_admin_referer('bulk-plugins');
                $plugins = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : array();
                //If not plugins selected, return
                if ( empty($plugins) ) {
                    wp_redirect( self_admin_url( $this->subplugin_page) );
                    exit;
                }
                $this->deactivate_subplugins( $plugins, false, is_network_admin() );;

                wp_redirect( self_admin_url( $this->subplugin_page .  "&deactivate-multi=true") );
                exit; break;
        }

    }



    /**
     * Render the table
     * @param $wp_list_table
     */

    /**
     *
     */
    function subplugin_render()
    {
        $wp_list_table = $this->wp_list_table;
        $wp_list_table->prepare_items();

        require_once(ABSPATH . 'wp-admin/admin-header.php');

        //$invalid = validate_active_plugins();
        $invalid = $this->validate_active_subplugins();
        if ( !empty($invalid) )
            foreach ( $invalid as $plugin_file => $error )
                echo '<div id="message" class="error"><p>' . sprintf(__('The plugin <code>%s</code> has been <strong>deactivated</strong> due to an error: %s'), esc_html($plugin_file), $error->get_error_message()) . '</p></div>';
        ?>

    <?php if ( isset($_GET['error']) ) :

        if ( isset( $_GET['main'] ) )
            $errmsg = __( 'You cannot delete a plugin while it is active on the main site.' );
        elseif ( isset($_GET['charsout']) )
            $errmsg = sprintf(__('The plugin generated %d characters of <strong>unexpected output</strong> during activation. If you notice &#8220;headers already sent&#8221; messages, problems with syndication feeds or other issues, try deactivating or removing this plugin.'), $_GET['charsout']);
        else
            $errmsg = __('Plugin could not be activated because it triggered a <strong>fatal error</strong>.');
        ?>
    <div id="message" class="updated"><p><?php echo $errmsg; ?></p>
        <?php
        if ( !isset( $_GET['main'] ) && !isset($_GET['charsout']) && wp_verify_nonce($_GET['_error_nonce'], 'plugin-activation-error_' . $this->plugin) ) { ?>
            <iframe style="border:0" width="100%" height="70px" src="<?php echo $this->subplugin_page . '&action=error_scrape&amp;plugin=' . esc_attr($this->plugin) . '&amp;_wpnonce=' . esc_attr($_GET['_error_nonce']); ?>"></iframe>
            <?php
        }
        ?>
    </div>
    <?php elseif ( isset($_GET['deleted']) ) :

        $user_ID=""; //TODO:To inspect where it comes from
        $delete_result = get_transient('plugins_delete_result_'. $user_ID);
        //delete_transient('plugins_delete_result'); //Delete it once we're done.

        if ( is_wp_error($delete_result) ) : ?>
        <div id="message" class="updated"><p><?php printf( __('Subplugin could not be deleted due to an error: %s','statcomm'), $delete_result->get_error_message() ); ?></p></div>
        <?php else : ?>
        <div id="message" class="updated"><p><?php _e('The selected subplugins have been <strong>deleted</strong>.','statcomm'); ?></p></div>
        <?php endif; ?>
    <?php elseif ( isset($_GET['activate']) ) : ?>
    <div id="message" class="updated"><p><?php _e('Subplugin <strong>activated</strong>.','statcomm') ?></p></div>
    <?php elseif (isset($_GET['activate-multi'])) : ?>
    <div id="message" class="updated"><p><?php _e('Selected subplugins <strong>activated</strong>.','statcomm'); ?></p></div>
    <?php elseif ( isset($_GET['deactivate']) ) : ?>
    <div id="message" class="updated"><p><?php _e('Subplugin <strong>deactivated</strong>.','statcomm') ?></p></div>
    <?php elseif (isset($_GET['deactivate-multi'])) : ?>
    <div id="message" class="updated"><p><?php _e('Selected subplugins <strong>deactivated</strong>.','statcomm'); ?></p></div>
    <?php endif; ?>

    <div class="wrap">
        <?php $wp_list_table->views(); ?>
        <form method="post" action="">
            <?php $wp_list_table->display(); ?>
        </form>
    </div>

    <?php
    }

    /**
     * Mimics activate_plugin
     * @param $subplugin
     * @param string $redirect
     * @param bool $network_wide
     * @param bool $silent
     * @return int|null|WP_Error
     */
    function activate_subplugin( $subplugin, $redirect = '', $network_wide = false) //, $silent = false )
    {
        $subplugin = $this->subplugin_basename( trim( $subplugin ) );

        if ( is_multisite() && ( $network_wide || $this->is_network_only_subplugin($subplugin) ) ) {
            $network_wide = true;
            $current = get_site_option( settingsAPI::SC_SUBPLUGIN_MULTISITE, array() );
        } else {
            $current = get_option( settingsAPI::SC_SUBPLUGIN, array() );
        }

        $valid = $this->validate_subplugin($subplugin);
        if ( is_wp_error($valid) )
            return $valid;
        if ( !in_array($subplugin, $current) )
        {
            if ( !empty($redirect) )
            {
                wp_redirect(add_query_arg('_error_nonce', wp_create_nonce('plugin-activation-error_' . $subplugin), $redirect)); // we'll override this later if the plugin can be included without fatal error
            }
            ob_start();
            include_once($this->subplugin_folder . '/' . $subplugin);
            /*
                        if ( ! $silent ) {
                            do_action( 'activate_subplugin', $subplugin, $network_wide );
                            do_action( 'activate_' . $subplugin, $network_wide );
                        }
            */

            if ( $network_wide ) {
                $current[$subplugin] = time();
                update_site_option( settingsAPI::SC_SUBPLUGIN_MULTISITE, $current );
            } else {
                $current[] = $subplugin;
                sort($current);
                update_option(settingsAPI::SC_SUBPLUGIN, $current);
            }

            /*
            if ( ! $silent ) {
                do_action( 'activated_subplugin', $subplugin, $network_wide );
            }
            */
            if ( ob_get_length() > 0 ) {
                $output = ob_get_clean();
                return new WP_Error('unexpected_output', __('The plugin generated unexpected output.'), $output);
            }
            ob_end_clean();
        }
        return null;
    }

    /**
     * Detects if a subplugin is newtork only.
     * To decide if this would be useful for subplugins.
     * @param $plugin
     * @return bool
     */
    function is_network_only_subplugin( $plugin ) {
        $plugin_data = get_plugin_data( $this->subplugin_folder . '/' . $plugin );
        if ( $plugin_data )
            return $plugin_data['Network'];
        return false;
    }

    function activate_subplugins( $subplugins, $redirect = '', $network_wide = false, $silent = false ) {
        if ( !is_array($subplugins) )
            $subplugins = array($subplugins);

        $errors = array();
        foreach ( $subplugins as $subplugin ) {
            if ( !empty($redirect) )
                $redirect = add_query_arg('plugin', $subplugin, $redirect);
            $result = $this->activate_subplugin($subplugin, $redirect, $network_wide, $silent);
            if ( is_wp_error($result) )
                $errors[$subplugin] = $result;
        }

        if ( !empty($errors) )
            return new WP_Error('plugins_invalid', __('One of the subplugins is invalid.','statcomm'), $errors);
        return true;
    }

    /**
     * Mimics plugin_basename
     * @param $file
     * @return mixed|string
     */
    function subplugin_basename($file) {
        $file = str_replace('\\','/',$file); // sanitize for Win32 installs
        $file = preg_replace('|/+|','/', $file); // remove any duplicate slash
        //$plugin_dir = str_replace('\\','/',WP_PLUGIN_DIR); // sanitize for Win32 installs
        $plugin_dir = str_replace('\\','/',$this->subplugin_folder); // sanitize for Win32 installs
        $plugin_dir = preg_replace('|/+|','/', $plugin_dir); // remove any duplicate slash
        $mu_plugin_dir = str_replace('\\','/',WPMU_PLUGIN_DIR); // sanitize for Win32 installs
        $mu_plugin_dir = preg_replace('|/+|','/', $mu_plugin_dir); // remove any duplicate slash
        $file = preg_replace('#^' . preg_quote($plugin_dir, '#') . '/|^' . preg_quote($mu_plugin_dir, '#') . '/#','',$file); // get relative path from plugins dir
        $file = trim($file, '/');
        return $file;
    }

    /**
     * Mimics validate_plugin
     * @param $plugin
     * @return int|WP_Error
     */
    function validate_subplugin($plugin) {
        if ( validate_file($plugin) )
            return new WP_Error('plugin_invalid', __('Invalid plugin path.'));
        if ( ! file_exists($this->subplugin_folder . '/' . $plugin) )
            return new WP_Error('plugin_not_found', __('Subplugin file does not exist.','statcomm'));

        $installed_plugins = self::get_subplugins('subplugins', plugin_dir_path(dirname(__FILE__))) ;
        if ( ! isset($installed_plugins[$plugin]) )
            return new WP_Error('no_plugin_header', __('The subplugin does not have a valid header.','statcomm'));
        return 0;
    }

    static function is_subplugin_active( $plugin ) {
        return in_array( $plugin, (array) get_option( settingsAPI::SC_SUBPLUGIN, array() ) ) || self::is_subplugin_active_for_network( $plugin );
    }

    function is_subplugin_inactive( $plugin ) {
        return self::is_subplugin_active( $plugin );
    }

    static function is_subplugin_active_for_network( $plugin ) {
        if ( !is_multisite() )
            return false;

        $plugins = get_site_option( settingsAPI::SC_SUBPLUGIN_MULTISITE);
        if ( isset($plugins[$plugin]) )
            return true;

        return false;
    }

    /**
     * Deactivate one or more plugins.
     * @param $plugins
     * @param bool $silent
     * @param null $network_wide
     */
    function deactivate_subplugins( $plugins, $silent = false, $network_wide = null ) {
        if ( is_multisite() )
        {
            $network_current = get_site_option( settingsAPI::SC_SUBPLUGIN_MULTISITE, array() );
        }
        $current = get_option( settingsAPI::SC_SUBPLUGIN, array() );
        $do_blog = $do_network = false;

        foreach ( (array) $plugins as $plugin ) {
            $plugin =$this->subplugin_basename( trim( $plugin ) );
            if ( !  self::is_subplugin_active($plugin) )
                continue;
            // $network_deactivating = false !== $network_wide && $this->is_subplugin_active_for_network( $plugin );

            /*
                        if ( ! $silent )
                            do_action( 'deactivate_subplugin', $plugin, $network_deactivating );
            */
            //Whoever did this procedure, it should have its reasons... Why in the hell I would use
            //false !== and later true !==  ?? crazy

            if ( false !== $network_wide ) {
                utilities::fl("Plugin is network");
                if ( subPlugins::is_subplugin_active_for_network( $plugin ) ) {
                    $do_network = true;
                    unset( $network_current[ $plugin ] );
                    utilities::fl("Plugin networkwide unset");
                } elseif ( $network_wide ) {
                    continue;
                }
            }

            if ( true !== $network_wide ) {
                utilities::fl("Plugin is site-only,search in the list");
                $key = array_search( $plugin, $current );
                utilities::fl("Key returned: ", $key);
                if ( false !== $key ) {
                    $do_blog = true;
                    array_splice( $current, $key, 1 );
                }
            }
            /*
                        if ( ! $silent ) {
                            do_action( 'deactivate_' . $plugin, $network_deactivating );
                            do_action( 'deactivated_subplugin', $plugin, $network_deactivating );
                        }
            */
        }

        if ( $do_blog )
            update_option(settingsAPI::SC_SUBPLUGIN, $current);
        if ( $do_network )
            update_site_option( settingsAPI::SC_SUBPLUGIN_MULTISITE, $network_current );
    }

    // An extended get_plugins version, almost identical to the one you'll find on wp-admin/includes/plugin.php
    //with a simple tweak to allow using a different plugin_root

    /**
     * Return an array with subplugin list in the format filename.php or folder/filename.php
     * 20120710: Little correction to cache variables at the end.
     * @static
     * @param string $subplugin_folder
     * @param $root_path
     * @return array
     */
    static function get_subplugins($subplugin_folder = '',$root_path) {

        if ( ! $cache_subplugins = wp_cache_get('subplugins', 'subplugins') )
            $cache_subplugins = array();

        if ( isset($cache_subplugins[ $subplugin_folder ]) )
            return $cache_subplugins[ $subplugin_folder ];

        $wp_plugins = array ();
        $plugin_root = $root_path;
        if ( !empty($subplugin_folder) )
            $plugin_root .= $subplugin_folder;

        // Files in wp-content/plugins directory
        $subplugins_dir = @ opendir( $plugin_root);
        $subplugin_files = array();
        if ( $subplugins_dir ) {
            while (($file = readdir( $subplugins_dir ) ) !== false ) {
                if ( substr($file, 0, 1) == '.' )
                    continue;
                if ( is_dir( $plugin_root.'/'.$file ) ) {
                    $plugins_subdir = @ opendir( $plugin_root.'/'.$file );
                    if ( $plugins_subdir ) {
                        while (($subfile = readdir( $plugins_subdir ) ) !== false ) {
                            if ( substr($subfile, 0, 1) == '.' )
                                continue;
                            if ( substr($subfile, -4) == '.php' )
                                $subplugin_files[] = "$file/$subfile";
                        }
                        closedir( $plugins_subdir );
                    }
                } else {
                    if ( substr($file, -4) == '.php' )
                        $subplugin_files[] = $file;
                }
            }
            closedir( $subplugins_dir );
        }

        if ( empty($subplugin_files) )
            return $wp_plugins;

        foreach ( $subplugin_files as $subplugin_file ) {
            if ( !is_readable( "$plugin_root/$subplugin_file" ) )
                continue;

            $subplugin_data = get_plugin_data( "$plugin_root/$subplugin_file", false, false ); //Do not apply markup/translate as it'll be cached.

            if ( empty ( $subplugin_data['Name'] ) )
                continue;

            $wp_plugins[plugin_basename( $subplugin_file )] = $subplugin_data;
        }

        uasort( $wp_plugins, '_sort_uname_callback' );

        $cache_subplugins[ $subplugin_folder ] = $wp_plugins;
        wp_cache_set('subplugins', $cache_subplugins, 'subplugins');

        return $wp_plugins;
    }

    /**
     * 20120710:Refactored for subplugins.
     * @return array
     */
    function validate_active_subplugins() {
        $subplugins = get_option( settingsAPI::SC_SUBPLUGIN, array() );
        // validate vartype: array
        if ( ! is_array( $subplugins ) ) {
            update_option(settingsAPI::SC_SUBPLUGIN, array() );
            $subplugins = array();
        }

        if ( is_multisite() && is_super_admin() ) {
            $network_subplugins = (array) get_site_option( settingsAPI::SC_SUBPLUGIN_MULTISITE, array() );
            $subplugins = array_merge( $subplugins, array_keys( $network_subplugins ) );
        }

        if ( empty( $subplugins ) )
            return;

        $invalid = array();

        // invalid plugins get deactivated
        foreach ( $subplugins as $subplugin ) {
            $result = $this->validate_subplugin( $subplugin );
            if ( is_wp_error( $result ) ) {
                $invalid[$subplugin] = $result;
                $this->deactivate_subplugins( $subplugin, true );
            }
        }
        return $invalid;
    }

    /**
     * Includes all active subplugins.
     */
    function subplugin_loader()
    {
        foreach ( $this->wp_get_active_and_valid_subplugins(settingsAPI::SC_SUBPLUGIN,
                                                            settingsAPI::SC_SUBPLUGIN_MULTISITE,
                                                            $this->subplugin_folder,
                                                            $this->subplugin_folder)
                  as $subplugin )
        {
            utilities::fl("subplugin loaded:",$subplugin);
            include_once( $subplugin );
        }
        unset( $subplugin );
    }

    /**
     * Resembles function wp_get_active_and_valid_plugins in wp-includes/load.php
     * with more flexibility and coding simplification
     * 20120712: A subplugin will work under BOTH environments. therefore the only big
     * difference is the activation list is single site (normal option) or multisite (site_wide options)
     * Subplugins only have to check where the settings are saved, nothing else
     * @return array
     */
    function wp_get_active_and_valid_subplugins($subplugin_option,$ms_subplugin_option,
                                                $subplugin_dir,$ms_subplugin_dir) {
        $subplugins = array();

        /*the problem: both methods returns different formats, watchout then*/
        if (is_multisite()){
            $active_subplugins=$this->wp_get_active_network_subplugins($ms_subplugin_option,$ms_subplugin_dir);
        }
        else{
            $active_subplugins=(array) get_option( $subplugin_option, array() );
        }

        foreach ( $active_subplugins as $subplugin ) {

            if ( ! validate_file( $subplugin ) // $plugin must validate as file
                && '.php' == substr( $subplugin, -4 ) // $plugin must end with '.php'
                && file_exists( $subplugin_dir . '/' . $subplugin ) // $plugin must exist
            )
            {
                $subplugins[] = $subplugin_dir . '/' . $subplugin;
            }
            else
            {
                utilities::fl("plugin WAS NOT INCLUDED:",$subplugin);
            }
        }
        utilities::fl("PLUGINS RETURNED:", $subplugins);
        return $subplugins;
    }

    /**
     * Resembles wp_get_active_network_plugins with more flexibility
     * @param $ms_subplugin_option
     * @param $subplugin_dir
     * @return array
     */
    function wp_get_active_network_subplugins($ms_subplugin_option,$subplugin_dir) {
        //$ms_subplugin_option = 'active_sitewide_plugins';
        //$subplugin_dir = WP_PLUGIN_DIR;

        $active_subplugins = (array) get_site_option( $ms_subplugin_option, array() );
        if ( empty( $active_subplugins ) )
        {
            return array();
        }

        $subplugins = array();
        $active_subplugins = array_keys( $active_subplugins );
        sort( $active_subplugins );

        foreach ( $active_subplugins as $subplugin ) {
            if ( ! validate_file( $subplugin ) // $plugin must validate as file
                && '.php' == substr( $subplugin, -4 ) // $plugin must end with '.php'
                && file_exists( $subplugin_dir . '/' . $subplugin ) // $plugin must exist
            )
            {
                //$subplugins[] = $subplugin_dir . '/' .  $subplugin;
                $subplugins[] = $subplugin;
            }
        }
        return $subplugins;
    }

}//621