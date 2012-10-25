<?php
/**
 * Sub-Plugins List Table class.
 *20120703: First attempt to make a subplugin class for Statcomm.
 * This class is a simplification of WP_Plugins_List_Table original WP class.
 * Subplugins main details:
 * - A subplugin uses the same header as an usual plugin
 * - Conceptually a subplugin can do anything , just like normal plugin.Why a subplugin then?:
 *      - a subplugin is an additional programming layer to use core functionality.
 *      - a subplugin shouldn't add core functionality unless specified.
 *      - some subplugins weren't built as plugins to be published into Wordpress repository. As such,
 *        a subplugin doesn't has a way to be updated (like a normal plugin). Initially this limitation was intended
 *        to avoid loading plugin as subplugins. This could be reinforced using classes and interfaces.
 *      - subplugin could be easily build for any developer who already makes plugins.
 *      - a subplugin does not have recently activated option.
 *20120710: Big oops using plugin_status instead alternative subplugin_status
 * 201200710: plugin and subplugin should use different action command to avoid conflicts. Corrected with constants
 * Cleaning the class , it needs many reviews.
 */

if (!class_exists('WP_List_Table'))
{
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WP_Subplugins_List_Table extends WP_List_Table {

    const ACTION_COMMAND = 'subplugin_action';

    private $subplugin_page;

    function __construct($redirectPage) {
        global $status, $page;
        $status = 'all';

        if ( isset( $_REQUEST['subplugin_status'] ) && in_array( $_REQUEST['subplugin_status'],  array( 'active', 'inactive' ) ) )
        $status = $_REQUEST['subplugin_status'];
        $this->subplugin_page = $redirectPage;
        utilities::fl("PAGE TO REDIRECT", $redirectPage);

        global $current_screen;
        $current_screen= WP_Screen::get('plugins');
        parent::__construct( array(
            'plural' => 'subplugins',
        ) );
    }


    function get_table_classes() {
        return array( 'widefat', $this->_args['plural'] );
    }

    function prepare_items() {
        global $status, $plugins, $totals, $page, $orderby, $order, $screen;

        wp_reset_vars( array( 'orderby', 'order', 's' ) );

        /*WPGReady*/
        $subplugin_folder= plugin_dir_path(dirname(__FILE__)) ;

        //Get the plugin but from the the subplugin folder
        //            'all' => apply_filters( 'all_subplugins', subPlugins::get_subplugins('subplugins',$subplugin_folder )),
        $plugins = array(
            'all' => apply_filters( 'all_subplugins', subPlugins::get_subplugins('subplugins',$subplugin_folder )),
            'active' => array(),
            'inactive' => array(),
        );

        $totals = array();
        foreach ( $plugins as $type => $list )
            $totals[ $type ] = count( $list );

        if ( empty( $plugins[ $status ] ) && !in_array( $status, array( 'all' ) ) )
            $status = 'all';

        $this->items = array();
        foreach ( $plugins[ $status ] as $plugin_file => $plugin_data ) {
            // Translate, Don't Apply Markup, Sanitize HTML
            $this->items[$plugin_file] = _get_plugin_data_markup_translate( $plugin_file, $plugin_data, false, true );
        }
    }

    function _order_callback( $plugin_a, $plugin_b ) {
        global $orderby, $order;

        $a = $plugin_a[$orderby];
        $b = $plugin_b[$orderby];

        if ( $a == $b )
            return 0;

        if ( 'DESC' == $order )
            return ( $a < $b ) ? 1 : -1;
        else
            return ( $a < $b ) ? -1 : 1;
    }

    function no_items() {
        global $plugins;

        if ( !empty( $plugins['all'] ) )
            _e( 'No plugins found.','statcomm');
        else
            _e( 'You do not appear to have any plugins available at this time.','statcomm' );
    }

    function get_columns() {
        global $status;

        return array(
            'cb'          =>'<input type="checkbox" />' ,
            'name'        => __( 'Subplugin','statcomm' ),
            'description' => __( 'Description','statcomm' ),
        );
    }

    function get_sortable_columns() {
        return array();
    }

    function get_views() {
        global $totals;

        $status_links = array();
        foreach ( $totals as $type => $count ) {
            if ( !$count )
                continue;

            switch ( $type ) {
                case 'all':
                    $text = _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $count, 'plugins' );
                    break;
                case 'active':
                    $text = _n( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', $count );
                    break;
                case 'recently_activated':
                    $text = _n( 'Recently Active <span class="count">(%s)</span>', 'Recently Active <span class="count">(%s)</span>', $count );
                    break;
                case 'inactive':
                    $text = _n( 'Inactive <span class="count">(%s)</span>', 'Inactive <span class="count">(%s)</span>', $count );
                    break;
            }
        }

        return $status_links;
    }

    function get_bulk_actions() {
        global $status;

        $actions = array();

        $screen = get_current_screen();

        if ( 'active' != $status )
            $actions['activate-selected'] = $screen->is_network ? __( 'Network Activate' ) : __( 'Activate' );

        if ( 'inactive' != $status && 'recent' != $status )
            $actions['deactivate-selected'] = $screen->is_network ? __( 'Network Deactivate' ) : __( 'Deactivate' );

        return $actions;
    }

    function bulk_actions( $which ) {
        parent::bulk_actions( $which );
    }


    /**
     * Returns current action command
     * 20120710: action command should be different between plugins and subplugins
     * @return bool
     */
    function current_action()
    {
        if ( isset( $_REQUEST[self::ACTION_COMMAND] ) && -1 != $_REQUEST[self::ACTION_COMMAND] )
            return $_REQUEST[self::ACTION_COMMAND];
        return false;
    }


    function display_rows() {
        global $status;

        $screen = get_current_screen();

        if ( is_multisite() && !$screen->is_network && in_array( $status, array( 'mustuse', 'dropins' ) ) )
            return;

        foreach ( $this->items as $plugin_file => $plugin_data )
            $this->single_row( $plugin_file, $plugin_data );
    }

    /**
     * To improve:
     * First version shouldn't need to delete nor edit
     * Why I can't see the table header and footer?
     * Correct activation/deactivation link and also it should work(!)
     * Add main procedure to make this plugins work (this is in another part of the plugin)
     * @param $plugin_file
     * @param $plugin_data
     */
    function single_row( $plugin_file, $plugin_data ) {
        global $status, $page, $s, $totals;

        $context = $status;
        $screen = get_current_screen();

      $actions = array( 'deactivate' => '','activate' => '' );
        //Is active has to be overwritten for new subplugin system
        $is_active=subPlugins::is_subplugin_active($plugin_file);
        if ( $is_active ) {
            $actions['deactivate'] = '<a href="' .
                wp_nonce_url( $this->subplugin_page . '&' .  self::ACTION_COMMAND . '=deactivate&amp;plugin=' . $plugin_file . '&amp;subplugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'deactivate-plugin_' . $plugin_file) . '" title="' . esc_attr__('Deactivate this plugin') . '">' . __('Deactivate') . '</a>';
        }
         else  {
            $actions['activate'] = '<a href="' . wp_nonce_url( $this->subplugin_page . '&' . self::ACTION_COMMAND . '=activate&amp;plugin=' . $plugin_file . '&amp;subplugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'activate-plugin_' . $plugin_file) . '" title="' . esc_attr__('Activate this plugin') . '" class="edit">' . __('Activate') . '</a>';
        }

         //subplugins should simplify running as best as possible.
        $prefix = $screen->is_network ? 'network_admin_' : '';

        $actions = apply_filters( $prefix . 'subplugin_action_links', array_filter( $actions ), $plugin_file, $plugin_data, $context );
        $actions = apply_filters( $prefix . "subplugin_action_links_$plugin_file", $actions, $plugin_file, $plugin_data, $context );


        $class = $is_active ? 'active' : 'inactive';
        $checkbox_id =  "checkbox_" . md5($plugin_data['Name']);
        $checkbox = in_array( $status, array( 'mustuse', 'dropins' ) ) ? '' : "<input type='checkbox' name='checked[]' value='" . esc_attr( $plugin_file ) . "' id='" . $checkbox_id . "' /><label class='screen-reader-text' for='" . $checkbox_id . "' >" . __('Select') . " " . $plugin_data['Name'] . "</label>";
        if ( 'dropins' != $context ) {
            $description = '<p>' . ( $plugin_data['Description'] ? $plugin_data['Description'] : '&nbsp;' ) . '</p>';
            $plugin_name = $plugin_data['Name'];
        }

        $id = sanitize_title( $plugin_name );
        if ( ! empty( $totals['upgrade'] ) && ! empty( $plugin_data['update'] ) )
            $class .= ' update';

        echo "<tr id='$id' class='$class'>";

        //overrided behavior, since it won't work in this different scenario
        list( $columns, $hidden ) = $this->get_column_info(); //overrided since get_colum_info uses WP_Screen
        $columns = $this->get_columns();

        foreach ( $columns as $column_name => $column_display_name ) {
            $style = '';

            switch ( $column_name ) {
                case 'cb':
                    echo "<th scope='row' class='check-column'>$checkbox</th>";
                    break;
                case 'name':
                    echo "<td class='plugin-title'$style><strong>$plugin_name</strong>";
                    echo $this->row_actions( $actions, true );
                    echo "</td>";
                    break;
                case 'description':
                    echo "<td class='column-description desc'$style>
						<div class='plugin-description'>$description</div>
						<div class='$class second plugin-version-author-uri'>";

                    $plugin_meta = array();
                    if ( !empty( $plugin_data['Version'] ) )
                        $plugin_meta[] = sprintf( __( 'Version %s' ), $plugin_data['Version'] );
                    if ( !empty( $plugin_data['Author'] ) ) {
                        $author = $plugin_data['Author'];
                        if ( !empty( $plugin_data['AuthorURI'] ) )
                            $author = '<a href="' . $plugin_data['AuthorURI'] . '" title="' . esc_attr__( 'Visit author homepage' ) . '">' . $plugin_data['Author'] . '</a>';
                        $plugin_meta[] = sprintf( __( 'By %s' ), $author );
                    }
                    if ( ! empty( $plugin_data['PluginURI'] ) )
                        $plugin_meta[] = '<a href="' . $plugin_data['PluginURI'] . '" title="' . esc_attr__( 'Visit plugin site' ) . '">' . __( 'Visit plugin site' ) . '</a>';

                    $plugin_meta = apply_filters( 'subplugin_row_meta', $plugin_meta, $plugin_file, $plugin_data, $status );
                    echo implode( ' | ', $plugin_meta );

                    echo "</div></td>";
                    break;
                default:
                    echo "<td class='$column_name column-$column_name'$style>";
                    do_action( 'manage_subplugins_custom_column', $column_name, $plugin_file, $plugin_data );
                    echo "</td>";
            }
        }

        echo "</tr>";

        do_action( 'after_subplugin_row', $plugin_file, $plugin_data, $status );
        do_action( "after_subplugin_row_$plugin_file", $plugin_file, $plugin_data, $status );
    }



    //The setting should work in single and multisite scenario, so we have to test it out first.
    //Overrides is_plugin_active
    function is_plugin_active( $plugin ) {
        return in_array( $plugin, (array) get_option( settingsAPI::SC_SUBPLUGIN, array() ) ) || subPlugins::is_subplugin_active_for_network( $plugin );
    }

      /**
     * Overrided original method since it depends on current screen.
     * Maybe the problem is find a way to manage the screen.
     * @param bool $with_id
     */
    function print_column_headers( $with_id = true ) {

        $screen = get_current_screen();

        //list( $columns, $hidden, $sortable ) = $this->get_column_info();
        //sortable disabled until we find a way to get the data without using screen object
        $columns =$this->get_columns();


        $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $current_url = remove_query_arg( 'paged', $current_url );

        foreach ( $columns as $column_key => $column_display_name ) {
            $class = array( 'manage-column', "column-$column_key" );

            $style = '';

            $style = ' style="' . $style . '"';

            if ( 'cb' == $column_key )
                $class[] = 'check-column';
            elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ) ) )
                $class[] = 'num';


            $id = $with_id ? "id='$column_key'" : '';

            if ( !empty( $class ) )
                $class = "class='" . join( ' ', $class ) . "'";

            echo "<th scope='col' $id $class $style>$column_display_name</th>";
        }
    }
}