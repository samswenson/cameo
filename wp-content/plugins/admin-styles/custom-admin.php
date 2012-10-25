<?php
/*
Plugin Name: Custom Wordpress Admin Theme
Plugin URI: http://www.bryankwilliams.com
Description: Custom Admin stylesheet by yours truly
Author: Bryan Williams
Version: 1.0
Author URI: http://www.bryankwilliams.com/
*/

function admin_test() {
    $url = get_settings('siteurl');
    $url = $url . '/wp-content/plugins/admin-styles/wp-admin.css';
    echo '<link rel="stylesheet" type="text/css" href="' . $url . '" />';
}
add_action('admin_head', 'admin_test');

?>