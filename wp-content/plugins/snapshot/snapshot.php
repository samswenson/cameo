<?php
/*
Plugin Name: Snapshot
Plugin URI: http://premium.wpmudev.org/project/snapshot
Description: This plugin allows you to take quick on-demand backup snapshots of your working WordPress database. You can select from the default WordPress tables as well as custom plugin tables within the database structure. All snapshots are logged, and you can restore the snapshot as needed. 
Author: Paul Menard (Incsub)
Version: 1.0.1
Author URI: http://premium.wpmudev.org/
WDP ID: 257
Text Domain: snapshot
Domain Path: languages

Copyright 2012 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
///////////////////////////////////////////////////////////////////////////

if (!defined('SNAPSHOT_I18N_DOMAIN'))
	define('SNAPSHOT_I18N_DOMAIN', 'snapshot');

/* Load the Database library. This contains all the logic to export/import the tables */
require_once( dirname(__FILE__) . '/lib/class_database_tools.php');

require_once(ABSPATH . '/wp-admin/includes/class-pclzip.php');



class DBSnapshot {
		
	private $_pagehooks = array();	// A list of our various nav items. Used when hooking into the page load actions.
	private $_messages 	= array();	// Message set during the form processing steps for add, edit, udate, delete, restore actions
	private $_settings	= array();	// These are global dynamic settings NOT stores as part of the config options
	
	private $_admin_header_error;	// Set during processing will contain processing errors to display back to the user
	
	
	/**
	 * The old-style PHP Class constructor. Used when an instance of this class 
 	 * is needed. If used (PHP4) this function calls the PHP5 version of the constructor.
	 *
	 * @since 1.0.0
	 * @param none
	 * @return self
	 */
    function DBSnapshot() {
        __construct();
    }


	/**
	 * The PHP5 Class constructor. Used when an instance of this class is needed.
	 * Sets up the initial object environment and hooks into the various WordPress 
	 * actions and filters.
	 *
	 * @since 1.0.0
	 * @uses $this->_settings array of our settings
	 * @uses $this->_messages array of admin header message texts.
	 * @param none
	 * @return self
	 */
	function __construct() {
		
		$this->_settings['SNAPSHOT_VERSION'] 			= '1.0';
		$this->_settings['SNAPSHOT_MENU_URL'] 			= get_admin_url() . 'admin.php?page=';
		$this->_settings['SNAPSHOT_PLUGIN_URL']			= WP_CONTENT_URL . "/plugins/". basename( dirname(__FILE__) );
		$this->_settings['SNAPSHOT_PLUGIN_BASE_DIR']	= dirname(__FILE__);
		$this->_settings['admin_menu_label']			= __( "Snapshots", SNAPSHOT_I18N_DOMAIN );
		$this->_settings['options_key']					= "snapshot_". $this->_settings['SNAPSHOT_VERSION']; // Used as the 'option_name' for wp_options table
		$this->_settings['backupFolderFull']			= ""; // Will be set during page load in $this->set_backup_folder();
		
		$this->_admin_header_error 						= "" ;		
		
		add_action('admin_notices', array(&$this, 'admin_notices_proc') );
		
		/* Setup the tetdomain for i18n language handling see http://codex.wordpress.org/Function_Reference/load_plugin_textdomain */
        load_plugin_textdomain( SNAPSHOT_I18N_DOMAIN, false, $this->_settings['SNAPSHOT_PLUGIN_BASE_DIR'] . '/languages' );

		/* Standard activation hook for all WordPress plugins see http://codex.wordpress.org/Function_Reference/register_activation_hook */
        register_activation_hook( __FILE__, array( &$this, 'plugin_activation_proc' ) );

		/* Standard uninstall hook NOT IMPLEMENTED */
        //register_activation_hook( __FILE__, array( &$this, 'plugin_activation_proc' ) );

		/* Register admin actions */
		add_action( 'admin_init', array(&$this,'admin_init_proc') );
		add_action( 'admin_menu', array(&$this,'admin_menu_proc') );
		
		/* Hook into the WordPress AJAX systems. */
		add_action('wp_ajax_snapshot_backup_ajax', array(&$this, 'snapshot_ajax_backup_proc') );		
	}	
	
	
	/**
	 * Called from WordPress when the admin page init process is invoked.
	 * Sets up other action and filter needed within the admin area for
	 * our page display.
	 * @since 1.0.0
	 *
	 * @param none
	 * @return unknown
	 */
	function admin_init_proc() {
		
		if (!is_super_admin()) return;

		/* Hook into the Plugin listing display logic. This will call the function which adds the 'Settings' link on the row for our plugin. */
		add_filter( 'plugin_action_links_'. basename( dirname( __FILE__ ) ) .'/'. basename( __FILE__ ), array(&$this,'plugin_settings_link_proc') );

		/* Hook into the admin bar display logic. So we can add our plugin to the admin bar menu */
		add_action( 'wp_before_admin_bar_render', array(&$this, 'admin_bar_proc') );
	}
	
	
	/**
	 * Hook to add the Snapshots menu option to the new WordPress admin 
	 * bar. This function will our a menu option to the admin menu 
	 * named 'Snapshots' which will link to the Tools > Snapshots page.
	 *
	 * @since 1.0.0
	 * @uses $wp_admin_bar
	 * @uses $this->_settings
	 *
	 * @param none
	 * @return none
	 */
	function admin_bar_proc() {

		global $wp_admin_bar;
		
		$wp_admin_bar->add_menu( 
			array(
				'parent' 	=> 'new-content',
				'id' 		=> 'snapshot-admin-menubar',
				'title' 	=> $this->_settings['admin_menu_label'],
				'href' 		=> 'admin.php?page=snapshots_new_panel',
				'meta' 		=> false
			)
		);
	}
	
	
	/**
	 * Called when when our plugin is activated. Sets up the initial settings 
	 * and creates the initial Snapshot instance. 
	 *
	 * @since 1.0.0
	 * @uses $this->config_data Our class-level config data
	 * @see $this->__construct() when the action is setup to reference this function
	 *
	 * @param none
	 * @return none
	 */
	function plugin_activation_proc() {

		return;
		
		/* No longer performing the initial snapshot on activation. */
/*		
		global $wpdb;
		
		if (!is_super_admin()) return;
		
		$this->load_config();

		// If this is the first time activation and not prior snapshot items create one for the user.
		if (!count($this->config_data['items']))
		{
			$this->set_backup_folder();

			$_REQUEST['name'] 	= __("Initial", SNAPSHOT_I18N_DOMAIN);
			$_REQUEST['notes'] 	= __("Created automatically on plugin activation.", SNAPSHOT_I18N_DOMAIN);
			$_REQUEST['tables']	= array($wpdb->prefix .'options');
			$this->snapshot_add(false);
		}
*/
	}
			
	/**
	 * Display our message on the Snapshot page(s) header for actions taken 
	 *
	 * @since 1.0.0
	 * @uses $this->_messages Set in form processing functions
	 *
	 * @param none
	 * @return none
	 */
	function admin_notices_proc() {
		
		// IF set during the processing logic setsp for add, edit, restore
		if ( (isset($_REQUEST['message'])) && (isset($this->_messages[$_REQUEST['message']])) ) {
			?><div id='snapshot-warning' class='updated fade'><p><?php echo $this->_messages[$_REQUEST['message']]; ?></p></div><?php
		}
		
		// IF we set an error display in red box
		if (strlen($this->_admin_header_error))
		{
			?><div id='snapshot-error' class='error'><p><?php echo $this->_admin_header_error; ?></p></div><?php
		}
	}


	/**
	 * Adds a 'settings' link on the plugin row
	 *
	 * @since 1.0.0
	 * @see $this->admin_init_proc where this function is referenced
	 *
	 * @param array links The default links for this plugin.
	 * @return array the same links array as was passed into function but with possible changes.
	 */
	function plugin_settings_link_proc( $links ) {

		$settings_link = '<a href="'. $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_settings_panel">'
			. __( 'Settings', SNAPSHOT_I18N_DOMAIN ) .'</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}
	

	/**
	 * Add the new Menu to the Tools section in the WordPress main nav
	 *
	 * @since 1.0.0
	 * @uses $this->_pagehooks 
	 * @see $this->__construct where this function is referenced
	 *
	 * @param none
	 * @return none
	 */
	function admin_menu_proc() {

		if (!is_super_admin()) return;
		
		add_menu_page( 	_x("Snapshots", 'page label', SNAPSHOT_I18N_DOMAIN), 
						_x("Snapshots", 'menu label', SNAPSHOT_I18N_DOMAIN), 
						'export',
						'snapshots_new_panel', 
						array(&$this, 'snapshot_show_new_panel'),
						plugin_dir_url( __FILE__ ) .'images/icon/greyscale-16.png'
		);

		$this->_pagehooks['snapshots-new'] 	= add_submenu_page( 'snapshots_new_panel', 
			_x('Add New Snapshot', 'page label', SNAPSHOT_I18N_DOMAIN), 
			_x('Add New', 'menu label', 'menu label', SNAPSHOT_I18N_DOMAIN), 
			'export',
			'snapshots_new_panel', 
			array(&$this, 'snapshot_show_new_panel')
		);

		$this->_pagehooks['snapshots-edit'] = add_submenu_page( 'snapshots_new_panel', 
			_x('All Snapshots','page label', SNAPSHOT_I18N_DOMAIN), 
			_x('All Snapshots', 'menu label', SNAPSHOT_I18N_DOMAIN), 
			'export',
			'snapshots_edit_panel', 
			array(&$this, 'snapshot_show_edit_panel')
		);

		$this->_pagehooks['snapshots-activity'] = add_submenu_page('snapshots_new_panel', 
			_x('Snapshots Activity log','page label', SNAPSHOT_I18N_DOMAIN), 
			_x('Activity log', 'menu label', SNAPSHOT_I18N_DOMAIN), 
			'export',
			'snapshots_activity_panel', 
			array(&$this, 'snapshot_show_activity_panel')
		);

		$this->_pagehooks['snapshots-settings'] = add_submenu_page('snapshots_new_panel', 
			_x('Snapshots Settings', 'page label', SNAPSHOT_I18N_DOMAIN), 
			_x('Settings', 'menu label', SNAPSHOT_I18N_DOMAIN), 
			'export',
			'snapshots_settings_panel', 
			array(&$this, 'snapshot_show_settings_panel')
		);

		// Hook into the WordPress load page action for our new nav items. This is better then checking page query_str values.
		add_action('load-'. $this->_pagehooks['snapshots-new'], 		array(&$this, 'on_load_snapshot_panels'));
		add_action('load-'. $this->_pagehooks['snapshots-edit'], 		array(&$this, 'on_load_snapshot_panels'));
		add_action('load-'. $this->_pagehooks['snapshots-activity'], 	array(&$this, 'on_load_snapshot_panels'));
		add_action('load-'. $this->_pagehooks['snapshots-settings'], 	array(&$this, 'on_load_snapshot_panels_settings'));
	}


	/**
	 * Set up the common items used on all Snapshot pages.
	 *
	 * @since 1.0.0
	 * @uses none
	 *
	 * @param none
	 * @return none
	 */
	function on_load_snapshot_panels()
	{
		if ( ! current_user_can( 'export' ) )
			wp_die( __( 'Cheatin&#8217; uh?' ) );

		if (!is_super_admin()) return;
		
		/* These messages are displayed as part of the admin header message see 'admin_notices' WordPress action */
		$this->_messages['success-update'] 				= __( "The Snapshot has been updated.", SNAPSHOT_I18N_DOMAIN );
		$this->_messages['success-add'] 				= __( "The Snapshot has been created.", SNAPSHOT_I18N_DOMAIN );
		$this->_messages['success-delete'] 				= __( "The Snapshot has been deleted.", SNAPSHOT_I18N_DOMAIN );
		$this->_messages['success-restore'] 			= __( "The Snapshot has been restored.", SNAPSHOT_I18N_DOMAIN );
		$this->_messages['success-settings'] 			= __( "Settings have been update.", SNAPSHOT_I18N_DOMAIN );

		$this->load_config();
		$this->set_backup_folder();
		//$this->set_timezone();		
		$this->process_snapshot_actions();
		$this->admin_plugin_help();
		
		//wp_enqueue_script('jquery-ui-progressbar');
		
		/* enqueue our plugin styles */
		wp_enqueue_style( 'snapshots-admin-stylesheet', $this->_settings['SNAPSHOT_PLUGIN_URL'] .'/css/snapshots-admin-styles.css', 
			false, $this->_settings['SNAPSHOT_VERSION']);	

		wp_enqueue_script('snapshot-admin', $this->_settings['SNAPSHOT_PLUGIN_URL'] .'/js/snapshot-admin.js',
			array('jquery'), $this->_settings['SNAPSHOT_VERSION']);			
	}
	
	
	/**
	 * Set up the page with needed items for the Settings metaboxes. 
	 *
	 * @since 1.0.0
	 * @uses none
	 *
	 * @param none
	 * @return none
	 */
	function on_load_snapshot_panels_settings() {
	
		// Load common items first. 
		$this->on_load_snapshot_panels();
		
		// For the Settings panel/pagew we want to use the WordPres metabox concept. This will allow for multiple
		// sections of content which are small. Plus the user can hide/close items as needed. 

		// These script files are required by WordPress to enable the metaboxes to be dragged, closed, opened, etc.
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');
		
		// Now add our metaboxes
		add_meta_box('snapshot-display-settings-panel-general', 
			__('Folder Location', SNAPSHOT_I18N_DOMAIN), 
			array(&$this, 'snapshot_show_settings_panel_folder_location'), 
			$this->_pagehooks['snapshots-settings'], 
			'normal', 'core');
	}
	
	
	/**
	 * Plugin main action processing function. Will filter the action called then
	 * pass on to other sub-functions
	 *
	 * @since 1.0.0
	 * @uses $_REQUEST global PHP object
	 *
	 * @param none
	 * @return none
	 */
	function process_snapshot_actions() {

		if (!is_super_admin()) return;
		
		if (isset($_REQUEST['action'])) {			

			switch($_REQUEST['action']) {

				case 'add':
					if ( empty($_POST) || !wp_verify_nonce($_POST['snapshot-noonce-field'], 'snapshot-add') )
				   		return;
					else
						$this->snapshot_add();
					
					break;
				
				case 'delete':
					if ( empty($_POST) || !wp_verify_nonce($_POST['snapshot-noonce-field'],'snapshot-delete') )
				   		return;
					else
						$this->snapshot_delete();

					break;
				
				case 'update':				
					if ( empty($_POST) || !wp_verify_nonce($_POST['snapshot-noonce-field'],'snapshot-update') )
				   		return;
					else 
						$this->snapshot_update();

					break;
					
				case 'restore-request':				
					if ( empty($_POST) || !wp_verify_nonce($_POST['snapshot-noonce-field'],'snapshot-restore') )
				   		return;
					else
						$this->snapshot_restore();
					
					break;

				case 'settings-update':				
					if ( empty($_POST) || !wp_verify_nonce($_POST['snapshot-noonce-field'],'snapshot-settings') )
				   		return;
					else
						$this->settings_config_update();

					break;
					
				default:
					break;
			}
		}		
	}
	
	
	/**
	 * Panel showing form for adding new Snapshots.
	 *
	 * @since 1.0.0
	 * @uses setup in $this->admin_menu_proc()
	 * @uses $wpdb
	 *
	 * @param none
	 * @return none
	 */		
	function snapshot_show_new_panel() {

		$tables = $this->get_database_tables();
		?>
		<div id="snapshot-new-panel" class="wrap snapshot-wrap">
			<?php screen_icon('snapshot'); ?>
			<h2><?php _ex("Add New Snapshot", "Snapshot New Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>
			<p><?php _ex("Use this form to create a new snapshot of your site. Fill in the optional Name and Notes fields. Select the tables to be included in this snapshot.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>
			
			<div id="snapshot-warning" style="display:none" class="updated fade"></div>

			<div id="snapshot-progress-bar-container" style="display: none" class="hide-if-no-js">
				<div class="snapshot-item">
					<div class="progress">
						<div class="percent">0%</div>
						<div class="bar" style="width: 1px;"></div>
					</div>
					<div class="snapshot-text"></div>
				</div>
			</div>

			<div id="poststuff" class="metabox-holder">			
				<form id="snapshot-add-new" action="<?php echo $this->_settings['SNAPSHOT_MENU_URL']; ?>snapshots_new_panel" method="post">
					<input type="hidden" name="action" value="add" />
					<?php wp_nonce_field('snapshot-add', 'snapshot-noonce-field'); ?>

					<div class="postbox">
						<h3 class="hndle"><span><?php _e('Snapshot Information', SNAPSHOT_I18N_DOMAIN); ?></span></h3>
						<div class="inside">

							<table class="form-table">
							<tr class="form-field">
								<th scope="row">
									<label for="snapshot-name"><?php _e('Name', SNAPSHOT_I18N_DOMAIN); ?></label>
								</th>
								<td>
									<input type="text" name="name" id="snapshot-name" value="<?php 
										if (isset($_REQUEST['name'])) { echo $_REQUEST['name']; } else { echo 'snapshot'; } ?>" />
									<p class="description"><?php _e('Give this snapshot a name', SNAPSHOT_I18N_DOMAIN); ?></p>
								</td>
							</tr>
							<tr class="form-field">
								<th scope="row">
									<label for="snapshot-notes"><?php _e('Notes', SNAPSHOT_I18N_DOMAIN); ?></label>
								</th>
								<td>
									<textarea id="snapshot-notes" name="notes" cols="20" rows="5"><?php
										if (isset($_REQUEST['notes'])) { echo $_REQUEST['notes']; } ?></textarea>
									<p class="description"><?php _e('Description about the configuration before the snapshot.', SNAPSHOT_I18N_DOMAIN); ?></p>
								</td>
							</tr>
							</table>
						</div><!-- end inside -->
					</div> <!-- end postbox -->
					
					<?php	
						if ((isset($tables['wp'])) && (count($tables['wp']))) {
							?>
							<div class="postbox">
								<h3 class="hndle"><span><?php _e('Tables &ndash; WordPress core', SNAPSHOT_I18N_DOMAIN); ?></span></h3>
								<div class="inside">						
									<table class="form-table">									
									<tr class="">
										<td>
											<p><a class="button-link snapshot-table-select-all" href="#" id="snapshot-table-wp-select-all">Select all</a></p>
											<ul class="snapshot-table-list">
											<?php
												foreach ($tables['wp'] as $table_key => $table_name) {

													$is_checked = '';
													if (isset($_REQUEST['backup_tables'])) {
														if (isset($_REQUEST['backup_tables'][$table_key]))
														{ $is_checked = ' checked="checked" '; }
													} else if (isset($this->config_data['config']['tables'])) {
														if ( array_search( $table_key, $this->config_data['config']['tables'] ) !== false )
														{ $is_checked = ' checked="checked" '; }
													}
													
													?><li><input type="checkbox" <?php echo $is_checked; ?> class="snapshot-table-item"
														id="snapshot-tables-<?php echo $table_key; ?>" value="<?php echo $table_key; ?>"
														name="backup_tables[<?php echo $table_key; ?>]"> <label 
														for="snapshot-tables-<?php echo $table_key; ?>"><?php 
														echo $table_name; ?></label></li><?php
												}
											?>
											</ul>
										</td>
									</tr>
									</table>
								</div><!-- end inside -->
							</div><!-- end postbox -->
							<?php
						}
					?>
					<?php	
						if ((isset($tables['non'])) && (count($tables['non']))) {
							?>
							<div class="postbox">
								<h3 class="hndle"><span><?php _e('Tables &ndash; other', SNAPSHOT_I18N_DOMAIN); ?></span></h3>
								<div class="inside">
									<table class="form-table">																			
									<tr class="">
										<td>
											<p><a class="button-link snapshot-table-select-all" href="#" id="snapshot-table-other-select-all">Select all</a></p>
											<ul class="snapshot-table-list">
											<?php
												foreach ($tables['non'] as $table_key => $table_name) {

													$is_checked = '';

													if (isset($_REQUEST['backup_tables'])) {
														if (isset($_REQUEST['backup_tables'][$table_key]))
														{ $is_checked = ' checked="checked" '; }
													} else if (isset($this->config_data['config']['tables'])) {
														if ( array_search( $table_key, $this->config_data['config']['tables'] ) !== false )
														{ $is_checked = ' checked="checked" '; }
													}

													?><li><input type="checkbox" <?php echo $is_checked; ?> class="snapshot-table-item"
														id="snapshot-tables-<?php echo $table_key; ?>" value="<?php echo $table_key; ?>"
														name="backup_tables[<?php echo $table_key; ?>]"> <label
														for="snapshot-tables-<?php echo $table_key; ?>"><?php 
														echo $table_name; ?></label></li><?php
												}
											?>
											</ul>
										</td>
									</tr>
									</table>					
								</div><!-- end inside -->
							</div> <!-- end postbox -->
							<?php
						}
					?>
					<input class="button-primary" type="submit" value="<?php _e('Create Snapshot', SNAPSHOT_I18N_DOMAIN); ?>" />

				</form>
			</div>
		</div>
		<?php
	}


	/**
	 * Panel showing the table listing of all Snapshots.
	 *
	 * @since 1.0.0
	 * @uses setup in $this->admin_menu_proc()
	 * @uses $this->config_data['items'] to build output
	 *
	 * @param none
	 * @return none
	 */
	function snapshot_show_edit_panel() {

		// If the user has clicked the link to edit a snapshot item show the edit form...
		$item = $this->get_edit_item();
		if (($item) && (isset($_REQUEST['action'])) && ($_REQUEST['action'] == 'edit'))
		{
			$this->snapshot_show_edit_panel_form();
		}
		// ...or if the user clicked the button to show the restore form. Show it.
		else if (($item) && (isset($_REQUEST['action'])) && ($_REQUEST['action'] == 'restore-panel'))
		{
			$this->snapshot_show_restore_panel_form();
		}
		else
		{
			?>
			<div id="snapshot-edit-listing-panel" class="wrap snapshot-wrap">
				<?php screen_icon('snapshot'); ?>
				<h2><?php _ex("All Snapshots", "Snapshot New Page Title", SNAPSHOT_I18N_DOMAIN); ?> <a class="add-new-h2" href="<?php echo $this->_settings['SNAPSHOT_MENU_URL']; ?>snapshots_new_panel">Add New</a></h2>
				<p><?php _ex("This is a listing of all Snapshots created within your site. To delete a snapshot set the checkbox then click the 'Delete Snapshots' button below the listing. To restore a snapshot click the 'Restore' button for that snapshot. To edit a snapshot click the name of the snapshot", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>
				
				<?php
					$site_tables = $this->get_database_tables();
				?>
				
				<form action="<?php echo $this->_settings['SNAPSHOT_MENU_URL']; ?>snapshots_edit_panel" method="post">
					<input type="hidden" name="action" value="delete" />
					<?php wp_nonce_field('snapshot-delete', 'snapshot-noonce-field'); ?>
		
					<table class="widefat">
					<thead>
					<tr class="form-field">
						<th class="snapshot-col-delete"><?php _e('Delete', 	SNAPSHOT_I18N_DOMAIN); ?></th>
						<th class="snapshot-col-restore"><?php _e('Restore', SNAPSHOT_I18N_DOMAIN); ?></th>
						<th class="snapshot-col-name"><?php _e('Name', SNAPSHOT_I18N_DOMAIN); ?></th>
						<th class="snapshot-col-date"><?php _e('Date', SNAPSHOT_I18N_DOMAIN); ?></th>
						<th class="snapshot-col-notes"><?php _e('Notes', SNAPSHOT_I18N_DOMAIN); ?></th>
						<th class="snapshot-col-user"><?php _e('User', SNAPSHOT_I18N_DOMAIN); ?></th>
						<th class="snapshot-col-file"><?php _e('Download', SNAPSHOT_I18N_DOMAIN); ?></th>						
					</tr>
					<thead>
					<tbody>
					<?php
					if ((isset($this->config_data['items'])) && (count($this->config_data['items']))) {

						foreach($this->config_data['items'] as $idx => $item) {

							$user_name = '';
							if (isset($item['user']))
								$user_name = $this->get_user_name(intval($item['user']));
								
							if (!isset($row_class)) { $row_class = ""; }
							$row_class = ( $row_class == '' ? 'alternate' : '' );
								
							?>
							<tr class="form-field <?php echo $row_class; ?>">
								<td class="snapshot-col-delete"><input type="checkbox" 
									name="delete[<?php echo $idx; ?>]" id="snapshot-delete-<?php echo $idx; ?>"></td>
								<td class="snapshot-col-restore"><?php
									if (file_exists(trailingslashit($this->_settings['backupFolderFull']) . $item['file'])) {
										?><a class="button-secondary" href="<?php 
										echo $this->_settings['SNAPSHOT_MENU_URL'] ?>snapshots_edit_panel&amp;action=restore-panel&amp;item=<?php echo $idx; ?>"><?php 
										_e('Restore', SNAPSHOT_I18N_DOMAIN); ?></a><?php
									}
									?>
								</td>
								<td class="snapshot-col-name"><a href="<?php 
									echo $this->_settings['SNAPSHOT_MENU_URL'] ?>snapshots_edit_panel&amp;action=edit&amp;item=<?php echo $idx; ?>"><?php 
										echo stripslashes($item['name']) ?></a></td>
								<td class="snapshot-col-date"><?php $this->snapshot_show_date_time($item['timestamp']); ?></td>
								<td class="snapshot-col-notes"><?php 
									$content = apply_filters('the_content', stripslashes($item['notes']));
									$content = str_replace(']]>', ']]&gt;', $content);
									echo $content; 

									if (isset($item['tables'])) {
										
										$wp_tables = array_keys($site_tables['wp']);
										$wp_tables = array_intersect($wp_tables, $item['tables']);

										$non_tables = array_keys($site_tables['non']);
										$non_tables = array_intersect($non_tables, $item['tables']);

										if ((count($wp_tables)) || (count($non_tables)))
										{
											?><p><?php 
											
											if (count($wp_tables)) {
												?><a class="snapshot-list-table-wp-show" href="#"><?php printf(__('show %d core', SNAPSHOT_I18N_DOMAIN), 
													count($wp_tables)) ?></a><?php
											}
												
											if (count($non_tables)) {
												if (count($wp_tables)) { echo ", "; } 
												?><a class="snapshot-list-table-non-show" href="#"><?php printf(__('show %d non-core', SNAPSHOT_I18N_DOMAIN), 
													count($non_tables)) ?></a><?php
											}
											?></p><?php
											
											if (count($wp_tables)) {
												?><p style="display: none" class="snapshot-list-table-wp-container"><?php 
													echo implode(', ', $wp_tables); ?></p><?php
											}

											if (count($non_tables)) {
												?><p style="display: none" class="snapshot-list-table-non-container"><?php 
													echo implode(', ', $non_tables); ?></p><?php
											}
										}
									} 
								?>
								</td>
								<td class="snapshot-col-user"><?php echo $user_name; ?></td>
								<td class="snapshot-col-file">
									<?php
										if (!file_exists(trailingslashit($this->_settings['backupFolderFull']) . $item['file']))
										{
											$SNAPSHOT_FILE_MISSING = true;
											/* ?><div id='snapshot-error' class='error'><p><?php
											printf(__('Snapshot file %s is missing! The file cannot be found in the snapshots backup folder.', 
												SNAPSHOT_I18N_DOMAIN), '<strong>'. $item['file'] .'</strong>'); 
											?></p></div><?php */

											?><p class="snapshot-error"><?php 
											printf(__('Snapshot file %s is missing! The file cannot be found in the snapshots backup folder.', 
												SNAPSHOT_I18N_DOMAIN), '<strong>'. $item['file'] .'</strong>'); ?></p><?php
										}
										else
										{
											?><a href="<?php echo trailingslashit($this->_settings['backupURLFull']) . $item['file']; ?>"><?php 
												echo $item['file'] ?></a><?php
										}
									?>
									
								</td>
								
							</tr>
							<?php
						}
					} else {
						?><tr class="form-field"><td colspan="4"><?php _e('No Snapshots', SNAPSHOT_I18N_DOMAIN); ?></td></tr><?php
					}
					?>
					</tbody>
					</table>
					<input class="button-primary" type="submit" value="<?php _e('Delete Snapshots', SNAPSHOT_I18N_DOMAIN); ?>" />			
				</form>
			</div>
			<?php
		}
	}
	
	
	/**
	 * Panel showing activity log for all snapshot actions (add, edit, restore, delete).
	 *
	 * @since 1.0.0
	 * @uses setup in $this->admin_menu_proc()
	 * @uses $this->config_data['activity']
	 *
	 * @param none
	 * @return none
	 */			
	function snapshot_show_activity_panel() {

		?>
		<div id="snapshot-activity-panel" class="wrap snapshot-wrap">
			<?php screen_icon('snapshot'); ?>
			<h2><?php _ex("Snapshots Activity Log", "Snapshot Activity Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>
			<p><?php _ex("This page shows the latest actions taken against any snapshot for this site. You will see a record for each action: add, update, restore or delete.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>
		
			<?php
				if ((isset($this->config_data['activity'])) && (count($this->config_data['activity']))) {
					$activity_log = $this->config_data['activity'];

					if (count($activity_log) > 30) {
						$activity_log = array_slice($this->config_data['activity'], 0, 30);
					}
					?>
					<?php
						$site_tables = $this->get_database_tables();
					?>

					<table class="widefat">
					<thead>
					<tr class="form-field">
						<th class="snapshot-col-details"><?php _e('Action', SNAPSHOT_I18N_DOMAIN); ?></th>
						<th class="snapshot-col-name"><?php _e('Name', SNAPSHOT_I18N_DOMAIN); ?></th>
						<th class="snapshot-col-date"><?php _e('Date', SNAPSHOT_I18N_DOMAIN); ?></th>
						<th class="snapshot-col-notes"><?php _e('Notes', SNAPSHOT_I18N_DOMAIN); ?></th>
						<th class="snapshot-col-user"><?php _e('User', SNAPSHOT_I18N_DOMAIN); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php
						foreach($activity_log as $activity_item) {

							if (!isset($row_class)) { $row_class = ""; }
							$row_class = ( $row_class == '' ? 'alternate' : '' );

							$item = $activity_item['item'];
							
							$user_name = '';
							if (isset($activity_item['user']))
								$user_name = $this->get_user_name(intval($activity_item['user']));
							?>
							<tr class="form-field <?php echo $row_class; ?>">
								<td class="snapshot-col-details"><?php echo stripslashes($activity_item['message']); ?></td>
								<td class="snapshot-col-action"><a href="<?php 
									echo $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_edit_panel&amp;action=edit&amp;item='. $item['timestamp']; ?>"><?php 
										echo stripslashes($activity_item['item']['name']) ?></a></td>
								<td class="snapshot-col-date"><?php $this->snapshot_show_date_time($activity_item['timestamp']); ?></td>
								<td class="snapshot-col-notes"><?php 
									$content = apply_filters('the_content', stripslashes($item['notes']));
									$content = str_replace(']]>', ']]&gt;', $content);
									echo $content; 

									if (isset($item['tables'])) {
										
										$wp_tables = array_keys($site_tables['wp']);
										$wp_tables = array_intersect($wp_tables, $item['tables']);

										$non_tables = array_keys($site_tables['non']);
										$non_tables = array_intersect($non_tables, $item['tables']);

										if ((count($wp_tables)) || (count($non_tables)))
										{
											?><p><?php 
											
											if (count($wp_tables)) {
												?><a class="snapshot-list-table-wp-show" href="#"><?php printf(__('show %d core', SNAPSHOT_I18N_DOMAIN), 
													count($wp_tables)) ?></a><?php
											}
												
											if (count($non_tables)) {
												if (count($wp_tables)) { echo ", "; } 
												?><a class="snapshot-list-table-non-show" href="#"><?php printf(__('show %d non-core', SNAPSHOT_I18N_DOMAIN), 
													count($non_tables)) ?></a><?php
											}
											?></p><?php
											
											if (count($wp_tables)) {
												?><p style="display: none" class="snapshot-list-table-wp-container"><?php 
													echo implode(', ', $wp_tables); ?></p><?php
											}

											if (count($non_tables)) {
												?><p style="display: none" class="snapshot-list-table-non-container"><?php 
													echo implode(', ', $non_tables); ?></p><?php
											}
										}
									} 
								?>
								</td>
								<td class="snapshot-col-user"><?php echo $user_name; ?></td>
							</tr>
							<?php
						}
					?>
					</tbody>
					</table>
					<?php
				} else {
						echo "<p>" . _e('No Snapshot activity yet. Now is a good time to create your first snapshot.', SNAPSHOT_I18N_DOMAIN) . '</p>';
				}
			?>
		</div>
		<?php
	}
	
	
	/**
	 * Metabox showing form for Settings.
	 *
	 * @since 1.0.0
	 * @uses metaboxes setup in $this->admin_menu_proc()
	 * @uses $_REQUEST['item']
	 * @uses $this->config_data['items']
	 *
	 * @param none
	 * @return none
	 */		
	function snapshot_show_settings_panel() {

		?>
		<div id="snapshot-settings-metaboxes-general" class="wrap">
			<?php screen_icon('snapshot'); ?>
			<h2><?php _ex("Snapshots Settings", "Snapshot Plugin Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>
			<p><?php _ex("The Settings panel provides access to a number of configuration options you can customize to meet you site needs.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>
			
			<div id="poststuff" class="metabox-holder">
				<div id="post-body" class="">
					<div id="post-body-content" class="snapshot-metabox-holder-main">
						<?php do_meta_boxes($this->_pagehooks['snapshots-settings'], 'normal', ''); ?>
					</div>
				</div>
			</div>	
		</div>
		<script type="text/javascript">
			//<![CDATA[
			jQuery(document).ready( function($) {
				// close postboxes that should be closed
				$('.if-js-closed').removeClass('if-js-closed').addClass('closed');

				// postboxes setup
				postboxes.add_postbox_toggles('<?php echo $this->_pagehooks['snapshots-settings']; ?>');
			});
			//]]>
		</script>
		<?php
	}
	
	
	/**
	 * Metabox Content for Snapshot Folder 
	 *
	 * @since 1.0.0
	 * @uses metaboxes setup in $this->admin_menu_proc()
	 * @uses $this->config_data['items']
	 *
	 * @param none
	 * @return none
	 */		
	function snapshot_show_settings_panel_folder_location() {
		?>
		<form action="<?php echo $this->_settings['SNAPSHOT_MENU_URL']; ?>snapshots_settings_panel" method="post">
			<input type="hidden" name="action" value="settings-update" />
			<?php wp_nonce_field('snapshot-settings', 'snapshot-noonce-field'); ?>
			<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
			<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>

			<p><?php _ex("Set a destination folder for your snapshots. This folder will be created inside your site's media upload folder.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>

			<table class="form-table">
			<tr class="form-field" >
				<th scope="row">
					<label for="snapshot-settings-backupFolder"><?php _e('Backup Folder', SNAPSHOT_I18N_DOMAIN); ?></label>
				</th>
				<td>
					<input type="text" name="backupFolder" id="snapshot-settings-backupFolder" value="<?php echo $this->config_data['config']['backupFolder']; ?>" />
					<p class="description"><?php 
						printf(__('Default folder is %s. If you change the folder name the previous snapshot files will be moved to the new folder.', SNAPSHOT_I18N_DOMAIN),
							'<code>snapshot</code>'); ?></p>

					<p class="description"><?php _e('Current folder', SNAPSHOT_I18N_DOMAIN); ?> <code><?php 
						echo trailingslashit($this->_settings['backupFolderFull']); ?></code></p>
				</td>
			</tr>
			<tr>
				<td>&nbsp;</td>
				<td><input class="button-primary" type="submit" value="<?php _e('Save Settings', SNAPSHOT_I18N_DOMAIN); ?>" /></td>
			</table>
		</form>
		<?php
		
//		if ((function_exists('crypt')) && (function_exists('base64_encode'))) {

			// Password to be encrypted for a .htpasswd file
			//$clearTextPassword = 'some password';

			// Encrypt password
			//$password = crypt($clearTextPassword, base64_encode($clearTextPassword));

			// Print encrypted password
			//echo "password=[". $password ."]<br />";
//		}
	}
	

	/**
	 * Metabox showing form for editing previous Snapshots.
	 *
	 * @since 1.0.0
	 * @uses metaboxes setup in $this->admin_menu_proc()
	 * @uses $_REQUEST['item']
	 * @uses $this->config_data['items']
	 *
	 * @param none
	 * @return none
	 */
	function snapshot_show_edit_panel_form() {

		?>
		<div id="snapshot-settings-metaboxes-general" class="wrap">
			<?php screen_icon('snapshot'); ?>
			<h2><?php _ex("Edit Snapshot", "Snapshot Plugin Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>
			<p><?php _ex("Use this form to update the Name or Notes for a previous snapshot. Also, provided is a link you can use to download the snapshot for sharing or archiving.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>
			<?php
			
				$SNAPSHOT_FILE_MISSING = false;
		
				if (isset($_REQUEST['item'])) {

					if (array_key_exists($_REQUEST['item'], $this->config_data['items'])) {

						$item = $this->config_data['items'][$_REQUEST['item']];
						?>
						<div id="poststuff" class="metabox-holder">			
						
							<form action="<?php echo $this->_settings['SNAPSHOT_MENU_URL']; ?>snapshots_edit_panel" method="post">
								<input type="hidden" name="action" value="update" />
								<input type="hidden" name="item" value="<?php echo $item['timestamp']; ?>" />
								<?php wp_nonce_field('snapshot-update', 'snapshot-noonce-field'); ?>

								<div class="postbox">
									<h3 class="hndle"><span><?php _e('Snapshot Information', SNAPSHOT_I18N_DOMAIN); ?></span></h3>
									<div class="inside">

										<table class="form-table">
										<tr class="form-field">
											<th scope="row">
												<label for="snapshot-name"><?php _e('Name', SNAPSHOT_I18N_DOMAIN); ?></label>
											</th>
											<td>
												<input type="text" name="name" id="snapshot-name" value="<?php echo stripslashes($item['name']); ?>" />
												<p class="description"><?php _e('Give this snapshot a name', SNAPSHOT_I18N_DOMAIN); ?></p>
											</td>
										</tr>
										<tr class="form-field">
											<th scope="row">
												<label for="snapshot-notes"><?php _e('Notes', SNAPSHOT_I18N_DOMAIN); ?></label>
											</th>
											<td>
												<textarea id="snapshot-notes" name="notes" cols="20" rows="5"><?php echo stripslashes($item['notes']); ?></textarea>
												<p class="description"><?php _e('Description about the configuration before the snapshot.', 
													SNAPSHOT_I18N_DOMAIN); ?></p>
											</td>
										</tr>
										<tr class="form-field">
											<th scope="row">
												<label for="snapshot-created-on"><?php _e('Created on', SNAPSHOT_I18N_DOMAIN); ?></label>
											</th>
											<td>
												<?php $this->snapshot_show_date_time($item['timestamp']); ?>
											</td>
										</tr>
										<tr class="form-field">
											<th scope="row">
												<label for="snapshot-created-by"><?php _e('Created by', SNAPSHOT_I18N_DOMAIN); ?></label>
											</th>
											<td>
												<?php
													$user_name = '';
													if (isset($item['user']))
														$user_name = $this->get_user_name(intval($item['user']));
													echo $user_name;
												?>
											</td>
										</tr>										
										</table>
										
									</div>
								</div>
								
								<div class="postbox">
									<h3 class="hndle"><span><?php _e('Tables', SNAPSHOT_I18N_DOMAIN); ?></span></h3>
									<div class="inside">

										<table class="form-table">
										<tr class="form-field">
											<td>
											<?php if (isset($item['tables'])) { ?>
												<p class="description"><?php _e('Tables included in this snapshot:', SNAPSHOT_I18N_DOMAIN); ?></p>
												<p><em><?php echo implode(', ', $item['tables']) ?></p>
											<?php } ?>							
											</td>
										</tr>
										</table>

									</div>
								</div>
								<div class="postbox">
									<h3 class="hndle"><span><?php _e('File Link', SNAPSHOT_I18N_DOMAIN); ?></span></h3>
									<div class="inside">

										<table class="form-table">
										<tr class="form-field">
											<td>
												<?php
													if (!file_exists(trailingslashit($this->_settings['backupFolderFull']) . $item['file']))
													{
														$SNAPSHOT_FILE_MISSING = true;
														 ?><div id='snapshot-error' class='error'><p><?php
														printf(__('Snapshot file %s is missing! The file cannot be found in the snapshots backup folder.', 
															SNAPSHOT_I18N_DOMAIN), '<strong>'. $item['file'] .'</strong>'); 
														?></p></div><?php

														printf(__('Snapshot file %s is missing! The file cannot be found in the snapshots backup folder.', 
															SNAPSHOT_I18N_DOMAIN), '<strong>'. $item['file'] .'</strong>'); 
													}
													else
													{
														?><p class="description"><?php 
															_e('Below is the Link to the Snapshot file in case you need to download or share.', 
															SNAPSHOT_I18N_DOMAIN); ?></p><?php
											
														$filesize = filesize($this->_settings['backupFolderFull'] ."/". $item['file']); 
														$file_kb = round(($filesize / 1024), 2) ."kb"; 
														?><a href="<?php echo trailingslashit($this->_settings['backupURLFull']) . $item['file']; ?>"><?php 
															echo trailingslashit($this->_settings['backupURLFull']) . $item['file'] ?></a> <em>(<?php 
																echo $file_kb; ?>)</em><?php
													}
												?>
											</td>
										</tr>
										</table>
										
									</div>
								</div>

								<input class="button-primary" type="submit" value="<?php _e('Update Snapshot', SNAPSHOT_I18N_DOMAIN); ?>" />
								<a class="button-secondary" href="<?php echo $this->_settings['SNAPSHOT_MENU_URL'] ?>snapshots_edit_panel">Cancel</a>
					
							</form>
							
						</div>
						<?php
					}				
				}
			?>
		</div>
		<?php
	}
	

	/**
	 * Panel showing form to restore previous Snapshot.
	 *
	 * @since 1.0.0
	 * @uses metaboxes setup in $this->admin_menu_proc()
	 * @uses $_REQUEST['item']
	 * @uses $this->config_data['items']
	 *
	 * @param none
	 * @return none
	 */	
	function snapshot_show_restore_panel_form() {

		?>
		<div id="snapshot-settings-metaboxes-general" class="wrap">
			<?php screen_icon('snapshot'); ?>
			<h2><?php _ex("Restore Snapshot", "Snapshot Plugin Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>
			
			<div id='snapshot-warning' class='updated fade'><p><?php _e('You are about to restore a previous version of your WordPress database. This will remove any new information added since the snapshot backup.', SNAPSHOT_I18N_DOMAIN); ?></p></div>
			<p><?php _ex("On this page you can restore a previous snapshot. Using the 'Restore Options' section below you can also opt to turn off all plugins as well as switch to a different theme as part of the restore.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>
			
			<?php

				if (isset($_REQUEST['item'])) {

					$item_key = esc_attr($_REQUEST['item']);

					if (array_key_exists($item_key, $this->config_data['items'])) {
						$item = $this->config_data['items'][$item_key];
						?>
						<div id="poststuff" class="metabox-holder">			
						
							<form action="<?php echo $this->_settings['SNAPSHOT_MENU_URL']; ?>snapshots_edit_panel" method="post">				
								<input type="hidden" name="action" value="restore-request" />
								<input type="hidden" name="item" value="<?php echo $item['timestamp']; ?>" />
								<?php wp_nonce_field('snapshot-restore', 'snapshot-noonce-field'); ?>

								<div class="postbox">
									<h3 class="hndle"><span><?php _e('Snapshot Information', SNAPSHOT_I18N_DOMAIN); ?></span></h3>
									<div class="inside">

										<table class="form-table">
										<tr class="form-field">
											<th scope="row">
												<label for="snapshot-name"><?php _e('Name', SNAPSHOT_I18N_DOMAIN); ?></label>
											</th>
											<td>
												<?php echo $item['name']; ?>
											</td>
										</tr>
										<tr class="form-field">
											<th scope="row">
												<label for="snapshot-notes"><?php _e('Notes', SNAPSHOT_I18N_DOMAIN); ?></label>
											</th>
											<td>
												<?php echo $item['notes']; ?>
											</td>
										</tr>
										<tr class="form-field">
											<th scope="row">
												<label for="snapshot-created-on"><?php _e('Created on', SNAPSHOT_I18N_DOMAIN); ?></label>
											</th>
											<td>
												<?php $this->snapshot_show_date_time($item['timestamp']); ?>
											</td>
										</tr>
										<tr class="form-field">
											<th scope="row">
												<label for="snapshot-created-by"><?php _e('Created by', SNAPSHOT_I18N_DOMAIN); ?></label>
											</th>
											<td>
												<?php
													$user_name = '';
													if (isset($item['user']))
														$user_name = $this->get_user_name(intval($item['user']));
													echo $user_name;
												?>
											</td>
										</tr>
										</table>

									</div>
								</div>
								<div class="postbox">
									<h3 class="hndle"><span><?php _e('Tables', SNAPSHOT_I18N_DOMAIN); ?></span></h3>
									<div class="inside">
									
										<table class="form-table">
										<tr class="form-field">
											<td>
											<?php if (isset($item['tables'])) { ?>
												<p class="description"><?php _e('Tables contained in this snapshot:', SNAPSHOT_I18N_DOMAIN); ?></p>
												<p><em><?php echo implode(', ', $item['tables']) ?></p>
											<?php } ?>							
											</td>
										</tr>
										</table>

									</div>
								</div>
								<div class="postbox">
									<h3 class="hndle"><span><?php _e('Restore Options', SNAPSHOT_I18N_DOMAIN); ?></span></h3>
									<div class="inside">

										<table class="form-table">
										<tr class="">	
											<th scope="row">
												<label><?php _e('Plugins', SNAPSHOT_I18N_DOMAIN); ?></label>
											</th>
											<td>
												<input type="checkbox" id="snapshot-restore-option-plugins" name="restore-option-plugins" value="yes" /> <label
														 for="snapshot-restore-option-plugins"><?php _e('Turn off all plugins', SNAPSHOT_I18N_DOMAIN); ?></label>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label><?php _e('Set a theme to active', SNAPSHOT_I18N_DOMAIN); ?></label>
											</th>
											<td>
												<?php
													$current_theme = get_current_theme();
													$themes = get_allowed_themes();

													if ($themes) {
														?><ul><?php
														foreach($themes as $theme_key => $theme) {

															?>
															<li><input type="radio" id="snapshot-restore-option-theme-<?php echo $theme['Template']; ?>"
															<?php 
																if ($theme_key == $current_theme) { echo ' checked="checked" '; } 
																?> name="restore-option-theme" value="<?php echo $theme_key; ?>" /> 
																<?php if ($theme_key == $current_theme) { echo '<strong>'; } ?>
																<?php echo $theme_key ?>
																<?php if ($theme_key == $current_theme) { echo '</strong> (current active theme)'; } ?>
															</li>
															<?php
														}
														?></ul><?php	
													}
												?>									
											</td>
										</tr>
										</table>
									</div>
								</div>
								<?php if (file_exists(trailingslashit($this->_settings['backupFolderFull']) . $item['file'])) { ?>					
									<input class="button-primary" type="submit" value="<?php _e('Restore Snapshot', SNAPSHOT_I18N_DOMAIN); ?>" />
								<?php } ?>
								<a class="button-secondary" href="<?php echo $this->_settings['SNAPSHOT_MENU_URL'] ?>snapshots_edit_panel">Cancel</a>
								<?php if (!file_exists(trailingslashit($this->_settings['backupFolderFull']) . $item['file'])) { 	
									?><div id='snapshot-error' class='error'><p><?php
									printf(__('Unable to restore because snapshot file %s is not found in the snapshot backup folder.',
									SNAPSHOT_I18N_DOMAIN), '<strong>'. $item['file'] .'</strong>');  
									?></p></div><?php
								} ?>
							</form>
							
						</div>
						<?php
					}				
				}
				?>
		</div>
		<?php
		
	}	
	
	
	/**
	 * Setup the context help instances for the user
	 *
	 * @since 1.0.0
	 * @uses $screen global screen instance
	 * @uses $screen->add_help_tab function to add the help sections
	 * @see $this->on_load_main_page where this function is referenced
	 *
	 * @param none
	 * @return none
	 */
	function admin_plugin_help() {

		$screen = get_current_screen();

		$screen->add_help_tab( array(
			'id'	=> 'snapshot-help-overview',
			'title'	=> __('Overview', SNAPSHOT_I18N_DOMAIN ),
			'content'	=> '<p>' . __( 'The Snapshot plugin provides the ability to create quick on-demand backups or snapshots of your WordPress site database. You can create as many snapshots as needed. The Snapshot plugin also provides the ability to restore a snapshot.', SNAPSHOT_I18N_DOMAIN ) . '</p>'
	    	) 
		);		

		if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == "snapshots_new_panel")) {
		
			$screen->add_help_tab( array(
				'id'	=> 'snapshot-help-new',
				'title'	=> __('New Snapshot', SNAPSHOT_I18N_DOMAIN ),
				'content'	=>  '<p>'. __('<strong>Name</strong> - Provide a custom name for this snapshot. Default name is "snapshot".', SNAPSHOT_I18N_DOMAIN ) .'</p>' 
								. '<p>' . __('<strong>Notes</strong> - Add some optional notes about the snapshot. Maybe some details on what plugins or theme were active. Or some note before you activate some new plugin.',SNAPSHOT_I18N_DOMAIN ) .'</p>' 
								. '<p>' . __('<strong>Table &ndash; WordPress core</strong> - This section lists all the core WordPress tables for your site.', SNAPSHOT_I18N_DOMAIN ) .'</p>' 
								. '<p>' . __('<strong>Table &ndash; other</strong> - This section lists other tables within your database which are not core tables. These tables could have been created and used by some of the plugins you have installed.', SNAPSHOT_I18N_DOMAIN ) .'</p>' 
		    	) 
			);
		}

		else if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == "snapshots_edit_panel")) {

			// Are we showing the edit form?
			if ((isset($_REQUEST['action'])) && ($_REQUEST['action'] == 'edit'))
			{
				$screen->add_help_tab( array(
					'id'	=> 'snapshot-help-edit',
					'title'	=> __('Edit Snapshot', SNAPSHOT_I18N_DOMAIN ),
					'content'	=> '<p>' . __( 'On the Edit Snapshot panel you can rename or add notes to the snapshot item. Also provided is a link to the snapshot file which you can download and archive to your local system.', SNAPSHOT_I18N_DOMAIN ) . '</p>'
				    ) 
				);
			} else if ((isset($_REQUEST['action'])) && ($_REQUEST['action'] == "restore-panel")) {

				$screen->add_help_tab( array(
					'id'	=> 'snapshot-help-edit',
					'title'	=> __('Restore Snapshot', SNAPSHOT_I18N_DOMAIN ),
					'content'	=> '<p>' . __( 'From this screen you can restore a snapshot. The restore will reload the database export into you current live site. Each table selected during the snapshot creation will be emptied before the snapshot information is loaded. It is important to understand this restore will be removing and new information added since the snapshot.', SNAPSHOT_I18N_DOMAIN ) . '</p>'
								. '<p>' . __( 'On the restore screen you will see a section for "Restore Option". The details for each option are discussed below', SNAPSHOT_I18N_DOMAIN ) . '</p>'
								. '<p>' . __( '<strong>Turn off all plugins</strong> - As part of the restore process you can automatically deactivate all plugins. This is helpful if you had trouble with a plugin and are trying to return your site back to some stable state.', SNAPSHOT_I18N_DOMAIN ) . '</p>'
								. '<p>' . __( '<strong>Set a theme to active</strong> - Similar to the Plugins option you can select to have a specific theme set to active as part of the restore process. Again, this is helpful if you installed a new theme that broke your site and you want to return your site back to a stable state.', SNAPSHOT_I18N_DOMAIN ) . '</p>'
				    ) 
				);

			} else {
				$screen->add_help_tab( array(
					'id'	=> 'snapshot-help-listing',
					'title'	=> __('All Snapshots', SNAPSHOT_I18N_DOMAIN ),
					'content'	=> '<p>' . __( 'All of your snapshots are listed here. Within the listing there are a number of options you can take.', SNAPSHOT_I18N_DOMAIN ) . '</p>' 
									. '<p>' . __( '<strong>Delete</strong> - On each row you will see a checkbox. To delete a snapshot or multiple snapshots check the box then click the blue "Delete Snapshots" button below the listing.', SNAPSHOT_I18N_DOMAIN ) . '</p>'
									. '<p>' . __( '<strong>Restore</strong> - The Restore button on each snapshot row will take you to a new screen where you can select from other options prior to performing a restore of the snapshot.', SNAPSHOT_I18N_DOMAIN ) . '</p>'
									. '<p>' . __( '<strong>Edit</strong> - To Edit a snapshot click on the snapshot name. You will be taken to a page where you can edit the Name and/or Notes information. The tables selected when your created the snapshot are not editable. ', SNAPSHOT_I18N_DOMAIN ) . '</p>'
				    ) 
				);
			}
		} else if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == "snapshots_activity_panel")) {

			$screen->add_help_tab( array(
				'id'	=> 'snapshot-help-activity',
				'title'	=> __('Activity Log', SNAPSHOT_I18N_DOMAIN ),
				'content'	=> '<p>' . __( 'The Activity log shows the last 30 actions add, update, delete and restore performed relating to all snapshots. If you have multiple admins this can help if you need to see if someone else restored a snapshot.', SNAPSHOT_I18N_DOMAIN ) . '</p>'
							. '<p>' . __( '<strong>Edit</strong> - To Edit a snapshot click on the snapshot name. You will be taken to a page where you can edit the Name and/or Notes information. The tables selected when your created the snapshot are not editable. ', SNAPSHOT_I18N_DOMAIN ) . '</p>'
			    ) 
			);
		} else if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == "snapshots_settings_panel")) {
		
			$screen->add_help_tab( array(
				'id'	=> 'snapshot-help-settings',
				'title'	=> __('Settings', SNAPSHOT_I18N_DOMAIN ),
				'content'	=> '<p>' . __( 'The Settings panel provides access to a number of configuration settings you can customize to meet you site needs.', SNAPSHOT_I18N_DOMAIN ) . '</p>'
							. '<p>' . __( "<strong>Backup Folder</strong> - By default the snapshot files are stored under your site's /wp-content/uploads/ directory in a new folder named 'snapshots'. If for some reason you already use a folder of this name you can set a different folder name to be used. If you change the folder name after some snapshots have been generated these files will be moved to the new folder. Note you cannot move the folder outside the /wp-content/uploads/ directory.", SNAPSHOT_I18N_DOMAIN ) . '</p>'
			    ) 
			);	
		} 
	}
	
	
	/**
	 * Processing 'delete' action from form post to delete a select Snapshot.
	 * Called from $this->process_snapshot_actions()
	 *
	 * @since 1.0.0
	 * @uses $_REQUEST['delete']
	 * @uses $this->config_data['items']
	 *
	 * @param none
	 * @return none
	 */				
	function snapshot_delete() {

		if (!isset($_REQUEST['delete'])) {
			wp_redirect($this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_edit_panel');
			die();
		}
		
		$CONFIG_CHANGED = false;
		foreach($_REQUEST['delete'] as $snapshot_key => $val) {
			if (array_key_exists($snapshot_key, $this->config_data['items'])) {
				$item = $this->config_data['items'][$snapshot_key];
				$this->snapshot_activity_log('delete', __("Delete snapshot", SNAPSHOT_I18N_DOMAIN), $item);
				
				$backupFile = trailingslashit($this->_settings['backupFolderFull']) . $item['file'];		
				if (file_exists($backupFile))
					@unlink($backupFile);					

				unset($this->config_data['items'][$snapshot_key]);
				$CONFIG_CHANGED = true;
			}
		}
		
		if ($CONFIG_CHANGED) {			
			$this->save_config();
			
			$location = add_query_arg('message', 'success-delete', $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_edit_panel');
			if ($location) {
				wp_redirect($location);
				die();
			}
		}
		
		wp_redirect($this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_edit_panel');
		die();
	}
	
	
	/**
	 * Processing 'add' action from form post to create a new Snapshot.
	 * Called from $this->process_snapshot_actions()
	 *
	 * @since 1.0.0
	 * @uses $_REQUEST['add']
	 *
	 * @param bool $redirect In normal cases we want to redirect to the listing 
	 * page after adding a new snapshot. But when activating the plugin we do
	 * not want to redirect from the main plugin listing.
	 *
	 * @return none
	 */		
	function snapshot_add($redirect=true) {
		
		global $wpdb;
		
		if (!$this->_settings['backupFolderFull']) {
			return;			
		}	
		
		if (isset($_REQUEST['backup_tables'])) {
			$tables = array_keys($_REQUEST['backup_tables']);
		} else {
			//$tables = array( $wpdb->prefix .'options' );			
			$this->_admin_header_error .= __("ERROR: You must select at least one table for the Snapshot", SNAPSHOT_I18N_DOMAIN);
			return;			
		}
		
		$time_key = time();
		$date_key = date('ymd-Hms', $time_key); // This timestamp format is used for the filename on disk. 

		$this->_settings['backupFile'] = trailingslashit($this->_settings['backupFolderFull']) .'snapshot-'. $date_key . '.sql';		
		$this->open($this->_settings['backupFile'], 'a');		
		if ($this->fp) {

			$backup_db = new BackupDatabase( );
			$backup_db->set_fp( $this->fp ); // Set our file point so the object can write to out output file. 
			$backup_db->backup_tables($tables);

			// Check if there were any processing errors during the backup
			if (count($backup_db->errors)) {
				
				// If yes then append to our admin header error and return.
				foreach($backup_db->errors as $error) {
					$this->_admin_header_error .= $error;
				}
				return;
			}
			unset($backup_db);
			
			$this->close();
			
			$current_user = wp_get_current_user();
			
			$backup_item = array(
				'timestamp'	=> 	$time_key,
				'name'		=> 	esc_attr($_REQUEST['name']),
				'notes'		=> 	esc_attr($_REQUEST['notes']),
				'file'		=>	basename($this->_settings['backupFile']),
				'user'		=>	$current_user->ID,
				'tables'	=>	$tables
			);
			
			// Saves the selected tables to our config. So noext time the user goes to make a snapshot these will be pre-selected. 
			$this->config_data['config']['tables'] = $tables;
			
			$this->snapshot_activity_log('add', __("Added snapshot", SNAPSHOT_I18N_DOMAIN), $backup_item); 
			$this->snapshot_save_item($backup_item);
			
			if ($redirect) {
				$location = add_query_arg('message', 'success-add', $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_edit_panel');

				if ($location) {
					wp_redirect($location);
				}
			}
		}
	}
	
	
	/**
	 * Processing 'update' action from form post when an edit is made to a Snapshot.
	 * Called from $this->process_snapshot_actions()
	 *
	 * @since 1.0.0
	 * @uses $_REQUEST['item']
	 * @uses $this->config_data['items']
	 *
	 * @param none
	 * @return none
	 */	
	function snapshot_update() {
		
		if (!isset($_REQUEST['item']))
			return;
		
		$item_key = $_REQUEST['item'];
		if (array_key_exists($item_key, $this->config_data['items'])) {
			
			$this->config_data['items'][$item_key]['name'] 	= esc_attr(stripslashes($_REQUEST['name']));
			$this->config_data['items'][$item_key]['notes'] = esc_attr(stripslashes($_REQUEST['notes']));

			$this->snapshot_activity_log('update', __("Update snapshot", SNAPSHOT_I18N_DOMAIN), $this->config_data['items'][$item_key]); 
			$this->save_config();
			
			$location = add_query_arg('message', 'success-update', $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_edit_panel');
			if ($location) {
				wp_redirect($location);
				die();
			}
		}
	}
	
	
	/**
	 * Processing 'restore-request' action from form post to restore a Snapshot.
	 * Called from $this->process_snapshot_actions()
	 *
	 * @since 1.0.0
	 * @uses $_REQUEST['delete']
	 * @uses $this->config_data['items']
	 *
	 * @param none
	 * @return none
	 */				
	function snapshot_restore() {
		
		$RESTORE_CHANGED = false;	
		
		global $wpdb;
		
		if (!isset($_REQUEST['item']))
			return;
		
		$item_key = $_REQUEST['item'];
		if (array_key_exists($item_key, $this->config_data['items'])) {

			$item = $this->config_data['items'][$item_key];
			$backupFile = trailingslashit($this->_settings['backupFolderFull']) . $item['file'];

			if (file_exists($backupFile)) {

				$zip = new PclZip($backupFile);
				$zip_contents = $zip->listContent();
				
				// Create a unique folder for our restore processing. Will later need to remove it. 
				$sessionBackupFolder = trailingslashit($this->_settings['backupFolderFull']) . "restore_". mt_rand();
				wp_mkdir_p($sessionBackupFolder);
				if (!is_writable($sessionBackupFolder)) {
					$this->_admin_header_error .= __("ERROR: The Snapshot folder is not writeable. Check the settings", SNAPSHOT_I18N_DOMAIN) . " ". $sessionBackupFolder;
					return;			
				}
				$extract_files = $zip->extract($sessionBackupFolder);
				if ($extract_files) {

					foreach($extract_files as $idx => $file_info) {
						
						// We know the manifest is there. Just ignore it for now. 
						
						// Do we have a SQL dump file?
						if (substr($file_info['stored_filename'], -4) == ".sql") {

							$backup_file_content = file_get_contents($file_info['filename']);
							if ($backup_file_content) {

								$backup_db = new BackupDatabase( );
								$backup_db->restore_databases($backup_file_content);

								// Check if there were any processing errors during the backup
								if (count($backup_db->errors)) {

									// If yes then append to our admin header error and return.
									foreach($backup_db->errors as $error) {
										$this->_admin_header_error .= $error;
									}
								}
								unset($backup_db);
								$RESTORE_CHANGED = true;
								
								$this->snapshot_activity_log('restore', __("Restore snapshot ", SNAPSHOT_I18N_DOMAIN), $item); 								
							}
						}
						// As we loop through the files we unlink them.
						unlink($file_info['filename']);						
					}
				}
				@rmdir($sessionBackupFolder);
				
				if ($RESTORE_CHANGED == true) {

					/* check our form codes to see if the user selected options */
					if (isset($_REQUEST['restore-option-theme']))
					{
						$_theme = esc_attr($_REQUEST['restore-option-theme']);
						if ($_theme)
						{
							delete_option('current_theme');
							add_option('current_theme', $_theme);
						}
					}
					
					if ((isset($_REQUEST['restore-option-plugins'])) && (esc_attr($_REQUEST['restore-option-plugins']) == "yes"))
					{
						$_plugin_file = basename(dirname(__FILE__)) ."/". basename(__FILE__);
						$_plugins = array($_plugin_file);
						delete_option('active_plugins');
						add_option('active_plugins', $_plugins);
					}
					
					$this->save_config(true);
 					$location = add_query_arg('message', 'success-restore', $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_edit_panel');
					if ($location)
					{
						wp_redirect($location);
						die();
					}
				}
			}
			else {
				/* Should not get here but just in case */
				$this->_admin_header_error .= __("ERROR: Snapshot backup file does not exist ", SNAPSHOT_I18N_DOMAIN) . $backupFile ."]";
				return;
			}
		}
	}
	
	
	/**
	 * Processing 'settings-update' action from form post to to update plugin global settings.
	 * Called from $this->process_snapshot_actions()
	 *
	 * @since 1.0.0
	 * @uses $_REQUEST['backupFolder']
	 * @uses $this->config_data['config']
	 *
	 * @param none
	 * @return none
	 */				
	function settings_config_update()
	{	
		$CONFIG_CHANGED = false;	
		
		// Because this needs to be universal we convert Windows paths entered be the user into proper PHP forward slash '/'
//		$_REQUEST['backupFolder'] = str_replace('\\', '/', stripslashes($_REQUEST['backupFolder']));
		
		if (isset($_REQUEST['backupFolder'])) {
//			if (substr($_REQUEST['backupFolder'], 0, 1) == "/") { // Setting Absolute path!
//
//				$backupFolder = esc_attr($_REQUEST['backupFolder']);
//				
//			} else {
				
				$this->config_data['config']['absoluteFolder'] = false;

				$backupFolder = esc_attr(basename(untrailingslashit($_REQUEST['backupFolder'])));
//			}				
			
			if ($backupFolder) {
				if ($backupFolder != $this->config_data['config']['backupFolder']) {
					$_oldbackupFolder = trailingslashit($this->_settings['backupFolderFull']);
					$this->config_data['config']['backupFolder'] = $backupFolder;

					$this->set_backup_folder();
					$_newbackupFolder = trailingslashit($this->_settings['backupFolderFull']);

					if ($_newbackupFolder !== $_oldbackupFolder) {

						foreach($this->config_data['items'] as $item) {
							if ((isset($item['file'])) && (strlen($item['file']))) {
								$_oldbackupFile = $_oldbackupFolder . $item['file'];
								$_newbackupFile = $_newbackupFolder . $item['file'];

								if (file_exists($_oldbackupFile)) {
									$rename_ret = rename($_oldbackupFile, $_newbackupFile);
								}
							}
						}
					}

					// Attempt to remove the previous folder. It may not be empty
					@rmdir($_oldbackupFolder);

					$CONFIG_CHANGED = true;
				}
			}
		}

		if ($CONFIG_CHANGED) {
			
			$this->save_config();
		}
		$location = add_query_arg('message', 'success-settings', $this->_settings['SNAPSHOT_MENU_URL'] .'snapshots_settings_panel');
		if ($location) {
			wp_redirect($location);
			die();
		}
		die();
	}
	
	
	/**
	 * Utility function to save a new Snapshot item to the master config array
	 * The item is stored with the timestamp as the key. 
	 * Called from $this->snapshot_add()
	 *
	 * @since 1.0.0
	 * @uses $this->config_data['items']
	 *
	 * @param array $item represents the snapshot item fields include timestamp, name (text), notes (text), user (ID), tables (array)
	 * @return none
	 */					
	function snapshot_save_item($item) {

		if (isset($item['timestamp'])) {
			$this->config_data['items'][$item['timestamp']] = $item;
			$this->save_config();
		}
	}
	
	
	/**
	 * Utility function to record an action form post into the processing activity log.
	 * The item is stored with the timestamp as the key. 
	 * Called from the various Process Action functions: $this->snapshot_restore,
	 * $this->snapshot_delete, $this->snapshot_update, etc.
	 *
	 * @since 1.0.0
	 * @uses $this->config_data['activity']
	 *
	 * @param string $action represents action verb (add, delete, restore, update). 
	 * @param message $message human readable text of what is occurring.
	 * @param array $item represents the snapshot item fields include timestamp, name (text), notes (text), user (ID), tables (array)
	 * @return none
	 */					
	function snapshot_activity_log($action, $message, $item) {

		$time_key = time();

		// record the user for the activity log. Note this user may be different 
		// than the one who created the snapshot.
		$current_user = wp_get_current_user();
		
		$log_item = array(
			'timestamp'	=>	$time_key,			
			'action'	=>	$action,
			'user'		=>	$current_user->ID,	
			'item'		=>	$item,
			'message'	=>	$message
		);
		
		while(array_key_exists($time_key, $this->config_data['activity'] )) {
			$time_key += 1;
		}
		$this->config_data['activity'][$time_key] = $log_item;
			
		krsort($this->config_data['activity']);
	}
	
	
	/**
	 * Utility function to open our output filename for writing and set the class file pointer (fp)
	 *
	 * @since 1.0.0
	 * @uses $this->fp
	 *
	 * @param string $filename file to be opened.
	 * @param char $mode how we want to open the file. Default is 'w' for write. 
	 * @return none
	 */					
	function open($filename = '', $mode = 'w') {

		if ('' == $filename) return false;
		$this->fp = @fopen($filename, $mode);
	}


	/**
	 * Utility function to close our opened file pointer (fp) 
	 *
	 * @since 1.0.0
	 * @uses $this->fp
	 *
	 * @return none
	 */					
	function close() {

		if ($this->fp)
			fclose($this->fp);
	}


	/**
	 * Utility function to read our config array from the WordPress options table. This
	 * function also will initialize needed instances of the array if needed. 
	 *
	 * @since 1.0.0
	 * @uses $this->_settings
	 * @uses $this->config_data
	 *
	 * @return none
	 */					
	function load_config() {

		global $wpdb;
		
		$this->config_data = get_option($this->_settings['options_key']);
		
		if (!isset($this->config_data['items']))
			$this->config_data['items'] = array();
		else
			krsort($this->config_data['items']); /* If we do have items sort them here instead of later. */
			
		if (!isset($this->config_data['config']))
			$this->config_data['config'] = array();

		if (!isset($this->config_data['config']['absoluteFolder']))
			$this->config_data['config']['absoluteFolder'] = false;

		if ( (!isset($this->config_data['config']['backupFolder'])) || (!strlen($this->config_data['config']['backupFolder'])) )
			$this->config_data['config']['backupFolder'] = "snapshots";
			
		if (!isset($this->config_data['activity'])) {
			$this->config_data['activity'] = array();
		}
		else
		{
			/* Sort the activity log first. Then trim off the last 1000 entries. */
			krsort($this->config_data['activity']);
			
			if (count($this->config_data['activity']) > 1000) {
				// Trim the Activity log entries to 1000 items
				$this->config_data['activity'] = array_slice($this->config_data['activity'], 0, 1000);
			}
		}
		
		/* Set the default table to be part of the snapshot */
		if (!isset($this->config_data['config']['tables']))
			$this->config_data['config']['tables'] = array();		
		
		/* Since we display a number of date/time fields we use the User settings from WordPress. */
		//$this->_settings['date_format'] = get_option('date_format');
		//$this->_settings['time_format'] = get_option('time_format');
	}
	
	
	/**
	 * Utility function to save our config array to the WordPress options table. 
	 *
	 * @since 1.0.0
	 * @uses $this->_settings
	 * @uses $this->config_data
	 *
	 * @param bool $force_save if set to true will first delete the option from the 
	 * global options array then re-add it. This is needed after a restore action where 
	 * the restored table my be the wp_options. In this case we need to re-add out own 
	 * plugins config array. When we call update_option() WordPress will not see a change
	 * when it compares our config data to its own internal version so the INSERT will be skipped.
	 * If we first delete the option from the WordPress internal version this will force 
	 * WordPress to re-insert our plugin option to the MySQL table.
	 * @return none
	 */
	function save_config($force_save = false) {

		if ($force_save)
			delete_option($this->_settings['options_key']);
			
		$ret = update_option($this->_settings['options_key'], $this->config_data);
	}


	/**
	 * Utility function to pull the snapshot item from the config_data based on 
	 * the $_REQUEST['item] value
	 *
	 * @since 1.0.0
	 * @uses $this->config_data
	 *
	 * @param array $item if found this array is the found snapshot item.
	 * @return none
	 */
	function get_edit_item() {
		if (!isset($_REQUEST['item']))
			return;
		
		// If the config_data[items] array has not yet been initialized or is empty return.
		if ((!isset($this->config_data['items'])) || (!count($this->config_data['items'])))
			return;
		
		$item_key = esc_attr($_REQUEST['item']);
		
		if (isset($this->config_data['items'][$item_key]))
			return $this->config_data['items'][$item_key];
	}


	/**
	 * Utility function to setup our destination folder to store snapshot output 
	 * files. The folder destination will be inside the site's /wp-content/uploads/ 
	 * folder tree. The default folder name will be 'snapshots'
	 *
	 * @since 1.0.0
	 * @see wp_upload_dir()
	 *
	 * @param none
	 * @return none
	 */
	function set_backup_folder() {

		// Are we dealing with Abolute or relative path?
//		if (substr($this->config_data['config']['backupFolder'], 0, 1) == "/") { 
//
//			// If absolute set a flag so we don't need to keep checking the substr();
//			$this->config_data['config']['absoluteFolder'] = true;
//			$_backupFolderFull = trailingslashit($this->config_data['config']['backupFolder']); 
//
//		} else {
			
			// If relative unset a flag so we don't need to keep checking the substr();
//			$this->config_data['config']['absoluteFolder'] = false;
			
			// If relative then we store the files into the /uploads/ folder tree.
			$wp_upload_dir = wp_upload_dir();
			$_backupFolderFull = trailingslashit($wp_upload_dir['basedir']) . $this->config_data['config']['backupFolder']; 
//		}
		
		//echo "_backupFolderFull=[". $_backupFolderFull ."]<br />";
		
		if (!file_exists($_backupFolderFull)) {

			/* If the destination folder does not exist try and create it */
			if (wp_mkdir_p($_backupFolderFull, 0775) === false) {
				
				/* If here we cannot create the folder. So report this via the admin header message and return */
				$this->_admin_header_error .= __("ERROR: Cannot create snapshot folder. Check that the parent folder is writeable", SNAPSHOT_I18N_DOMAIN) 
					." ". $_backupFolderFull;
				return;
			}
		}
		
		//echo "_backupFolderFull=[". $_backupFolderFull ."]<br />";
		/* If here the destination folder is present. But is it writeable by our process? */
		if (!is_writable($_backupFolderFull)) {
			
			/* Try updating the folder perms */
			@ chmod( $_backupFolderFull, 0775 );
			if (!is_writable($_backupFolderFull)) {

				/* Appears it is still not writeable then report this via the admin heder message and return */
				$this->_admin_header_error .= __("ERROR: The Snapshot destination folder is not writable", SNAPSHOT_I18N_DOMAIN) 
					." ". $_backupFolderFull;
			}
		}

		if ($this->config_data['config']['absoluteFolder'] == true) {
			if (!file_exists(trailingslashit($_backupFolderFull) ."index.php")) {
				$this->open(trailingslashit($_backupFolderFull) ."index.php");
				$this->close();
			}
		}

		$this->_settings['backupFolderFull'] 	= $_backupFolderFull;
		if ($this->config_data['config']['absoluteFolder'] != true) {
			
			$relative_path = substr($_backupFolderFull, strlen(ABSPATH));
			$this->_settings['backupURLFull']		= site_url($relative_path);
		} else {
			$this->_settings['backupURLFull']		= '';			
		}		
	}


	/**
	 * Utility function to grab the array of database tables for the site. This function
	 * is multisite aware in that is only grabs tables within the site's table prefix
	 * for example if on a multisite install the table prefix is wp_2_ then all other 
	 * tables 'wp_' and 'wp_x_' will be ignores. 
	 *
	 * The functions builds a multi array. On node of the array [wp]  will be the 
	 * core WP tables. Another node [non] will be tables within that site which 
	 * are not core tables. This could be table created by other plugins. 
	 *
	 * @since 1.0.0
	 * @see wp_upload_dir()
	 *
	 * @param none
	 * @return array $tables multi-dimensional array of tables.
	 */
	function get_database_tables() {

		global $wpdb;
		
		$blog_prefixes = array();
		if ((is_multisite()) && ($wpdb->prefix == $wpdb->base_prefix)) {
			$blogs = $wpdb->get_results( $wpdb->prepare("SELECT blog_id, site_id FROM $wpdb->blogs") );
			if ($blogs)
			{
				foreach($blogs as $blog_id => $blog_info)
				{
					if (($blog_info->blog_id == $wpdb->blogid) && ($blog_info->site_id == $wpdb->siteid))
						continue;
				
					$blog_prefix = $wpdb->base_prefix . $blog_info->blog_id .'_';
					$blog_prefixes[] = $blog_prefix;
				}
			}
		}
		
		//echo "prefix=[". $wpdb->prefix. "]<br />";
		//echo "base_prefix=[". $wpdb->base_prefix. "]<br />";
		//echo "blog_id=[". $wpdb->blogid. "]<br />";
		//echo "site_id=[". $wpdb->siteid. "]<br />";
		//echo "wpdb<pre>"; print_r($wpdb); echo "</pre>";
		
		$tables = array();
		$tables['wp'] = array();
		$tables['non'] = array();
		$tables['other'] = array();
		
		$tables_wp = $wpdb->tables('all');
		if ($tables_wp)
		{
			foreach($tables_wp as $table_key => $table_name)
			{
				if (strncasecmp ( $table_name, $wpdb->prefix , strlen($wpdb->prefix) ) == 0) {
					$tables['wp'][$table_name] = $table_name;
				}
			}
			ksort($tables_wp);
		}

		$tables_all_rows = $wpdb->query('show tables');
		if ($tables_all_rows) {
			foreach($wpdb->last_result as $table_set) {
				foreach($table_set as $table_name) {

					if ((is_multisite()) && ($wpdb->prefix == $wpdb->base_prefix)) {
						$blog_table_prefix = substr($table_name, 0, strlen($blog_prefixes[0]));
						if ((isset($blog_table_prefix)) && (strlen($blog_table_prefix))) {
							//echo "blog_table_prefix=[". $blog_table_prefix ."]<br />";

							if (array_search($blog_table_prefix, $blog_prefixes) === false)
								$tables_all[$table_name] = $table_name;										
						}
					} else {
						if (strncasecmp ( $table_name, $wpdb->prefix , strlen($wpdb->prefix) ) == 0) {
							$tables_all[$table_name] = $table_name;										
						}
					}
				}
			}
			
			ksort($tables_all);
		}

		// The 'non' tables are the difference bettern the all and wp table sets
		$tables['non'] = array_diff($tables_all, $tables_wp);
		
		// Now for each set set want to strip off the table prefix from the name 
		// so when they are displayed they take up less room. 
/*
		if (isset($tables['wp']))
		{
			foreach($tables['wp'] as $table_key => $table_name)
			{
				$tables['wp'][$table_key] = substr($table_name, strlen($wpdb->prefix));
			}
			asort($tables['wp']);
		}

		if (isset($tables['non']))
		{
			foreach($tables['non'] as $table_key => $table_name)
			{
				$tables['non'][$table_key] = substr($table_name, strlen($wpdb->prefix));
			}
			//ksort($tables['non']);
		}
		if (isset($tables['non']))
		{
			foreach($tables['other'] as $table_key => $table_name)
			{
				$tables['other'][$table_key] = substr($table_name, strlen($wpdb->prefix));
			}
			asort($tables['other']);
		}
*/
		return $tables;
	}
	
	
	/**
	 * Utility function to grab the user's name from various options from 
	 * the user_id. From the code note we try the display_name field. If 
	 * not set we try the user_nicename. If also not set we simply use the 
	 * user's login name.
	 *
	 * @since 1.0.0
	 * @see 
	 *
	 * @param int $user_id The user's ID
	 * @return string $user_name represents the user's name
	 */
	function get_user_name($user_id) {

		$user_name = get_the_author_meta('display_name', intval($user_id));

		if (!$user_name)
			$user_name = get_the_author_meta('user_nicename', intval($user_id));

		if (!$user_name)
			$user_name = get_the_author_meta('user_login', intval($user_id));

		return $user_name;
	}


	/**
	 * AJAX Gateway to adding a new snapshot. Seems the simple form post is too much given 
	 * the number of tables possibly selected. So instead we intercept the form submit with 
	 * jQuery and process each selected table as its own HTTP POST into this gateway. 
	 *
	 * The process starts with the 'init' which sets up the session backup filename based on
	 * the session id. Next each 'table' is called. Last a 'finish' action is called to move 
	 * the temp file into the final location and add a record about the backup to the activity log
	 *
	 * @since 1.0.0
	 * @see 
	 *
	 * @param none
	 * @return none
	 */

	function snapshot_ajax_backup_proc()
	{
		@session_start();

		$this->load_config();
		$this->set_backup_folder();
		//$this->set_timezone();		
		$this->snapshot_add();

		switch($_REQUEST['snapshot_action'])
		{
			case 'init':
				$this->snapshot_ajax_backup_init();
				break;
			
			case 'table':
				$this->snapshot_ajax_backup_table();
				break;
				
			case 'finish':
				$this->snapshot_ajax_backup_finish();
				break;
				
			default:
				break;
		}
		die();
	}

	/**
	 * This 'init' process begins the user's backup via AJAX. Creates the session backup file. 
	 *
	 * @since 1.0.0
	 * @see 
	 *
	 * @param none
	 * @return none
	 */

	function snapshot_ajax_backup_init() {
		
		$sessionBackupFolder = trailingslashit($this->_settings['backupFolderFull']) . session_id();
		wp_mkdir_p($sessionBackupFolder);
		if (!is_writable($sessionBackupFolder)) {
			echo __("ERROR: The Snapshot folder is not writeable. Check the settings", SNAPSHOT_I18N_DOMAIN) . " ". $sessionBackupFolder;
			die();
		}

		$backupFile = trailingslashit($sessionBackupFolder) .'snapshot_backups.sql';		
		$this->open($backupFile, 'w');
		if ($this->fp) {

			if (isset($_SESSION['backupTables']))
				unset($_SESSION['backupTables']);

			$_SESSION['backupFile'] = $backupFile;

			$this->close();
		}		
	}
	
	/**
	 * This 'table' process is called from JS for each table selected. The contents of the SQL table
	 * are appended to the session backup file. 
	 *
	 * @since 1.0.0
	 * @see 
	 *
	 * @param none
	 * @return none
	 */
	
	function snapshot_ajax_backup_table() {
		
		if ((isset($_SESSION['backupFile'])) && (isset($_REQUEST['snapshot_table'])))
		{
			$this->open($_SESSION['backupFile'], 'a');
			if ($this->fp) {

				$backup_db = new BackupDatabase( );
				$backup_db->set_fp( $this->fp ); // Set our file point so the object can write to out output file. 
				$backup_db->backup_table($_REQUEST['snapshot_table']);
				unset($backup_db);
				$this->close();

				// Add the table to our session 
				$_SESSION['backupTables'][$_REQUEST['snapshot_table']] = $_REQUEST['snapshot_table'];
			}
		}
	}
	
	/**
	 * This 'finish' process is called from JS when all selected tables have been archived. This process
	 * renames the session backup file to the final location and writes an activity log record. 
	 *
	 * @since 1.0.0
	 * @see 
	 *
	 * @param none
	 * @return none
	 */
	
	function snapshot_ajax_backup_finish() {

		if ( (isset($_SESSION['backupFile'])) && (file_exists($_SESSION['backupFile'])) ) {

			$archiveFiles = array();

			$archiveFiles[] = $_SESSION['backupFile'];
			
			/* Create a zip manifest file */
			$manifestFile = trailingslashit(dirname($_SESSION['backupFile'])) . 'snapshot_manifest.txt';
			$this->open($manifestFile);
			if ($this->fp)
			{
				fwrite($this->fp, "VERSION:". $this->_settings['SNAPSHOT_VERSION'] ."\r\n"); 
				fwrite($this->fp, "TABLES:". implode(', ', $_SESSION['backupTables']) ."\r\n"); 
				
				$this->close();
				$archiveFiles[] = $manifestFile;
			}

			$time_key = time();
			$date_key = date('ymd-Hms', $time_key); // This timestamp format is used for the filename on disk. 

			$backupZipFile = trailingslashit($this->_settings['backupFolderFull']) .'snapshot-'. $date_key . '.zip';
			if (file_exists($backupZipFile))
				@unlink($backupZipFile);

			$zip = new PclZip($backupZipFile);

			// Let's actually create the zip file from the files_array. We strip off the leading path (3rd param)
			$zip->create($archiveFiles, '', dirname($_SESSION['backupFile']));

			foreach($archiveFiles as $archiveFile) {
				@unlink($archiveFile);				
			}
			
			// Remove the parent session folder. 
			@rmdir(dirname($_SESSION['backupFile']));

			$current_user = wp_get_current_user();
			
			$backup_item = array(
				'timestamp'	=> 	$time_key,
				'name'		=> 	esc_attr(stripslashes($_REQUEST['name'])),
				'notes'		=> 	esc_attr(stripslashes($_REQUEST['notes'])),
				'file'		=>	basename($backupZipFile),
				'user'		=>	$current_user->ID,
				'tables'	=>	$_SESSION['backupTables']
			);
			

			// Saves the selected tables to our config. So noext time the user goes to make a snapshot these will be pre-selected. 
			$this->config_data['config']['tables'] = array_values($_SESSION['backupTables']);
			
			$this->snapshot_activity_log('add', __("Added snapshot", SNAPSHOT_I18N_DOMAIN), $backup_item); 
			$this->snapshot_save_item($backup_item);
			
			// echo out the finished message so the user knows we are done.
			echo "Created Snapshot: ". basename($backupZipFile);
		}
	}


	/**
	 * Utility function to build the display of a timestamp into the date time format. 
	 *
	 * @since 1.0.0
	 * @see 
	 *
	 * @param int UNIX timestamp from time()
	 * @return none
	 */
	function snapshot_show_date_time($timestamp) {
		
		echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp + ( get_option( 'gmt_offset' ) * 3600));
	}
	
	/**
	 * Uninstall/Delete plugin action. Called from uninstall.php file. This function removes file and options setup by plugin.
	 *
	 * @since 1.0.0
	 * @see 
	 *
	 * @param int UNIX timestamp from time()
	 * @return none
	 */
	
	function uninstall_snapshot() {

		$this->load_config();
		$this->set_backup_folder();

		if ((isset($this->_settings['backupFolderFull'])) && (strlen($this->_settings['backupFolderFull']))) {
			
			/* Delete all the snapshot files */
			foreach($this->config_data['items'] as $idx => $item) {

				if (file_exists(trailingslashit($this->_settings['backupFolderFull']) . $item['file']))
				{
					unlink(trailingslashit($this->_settings['backupFolderFull']) . $item['file']);
				}
			}

			// Remove the blank index.php we created to prevent directory listing
			if (file_exists(trailingslashit($this->_settings['backupFolderFull']) . "index.php"))
			{
				unlink(trailingslashit($this->_settings['backupFolderFull']) . "index.php");
			}
			
			/* Now attempt to remove the snapshot folder. Note it may nove remove if not empty */
			@rmdir(untrailingslashit($this->_settings['backupFolderFull']));
		}
		delete_option($this->_settings['options_key']);
	}
}

$snapshot = new DBSnapshot();
