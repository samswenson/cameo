<?php 
/*
Plugin Name: Hide Widget Title
Plugin URI: http://wpprobusiness.com
Description: Adds checkbox to all widgets that enables you to hide a widget title on the frontend of your site. All widget titles remain visible in admin.
Author: Adam W. Warner	
Author URI: http://wpprobusiness.com
Version: 1.0
*/

register_activation_hook(__FILE__,'header_tbl_install');
function header_tbl_install () {

   global $wpdb;
   $table_name = $wpdb->prefix . "displaywidget";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
     
	$sql = "CREATE TABLE " . $table_name . " (
          id mediumint(9) NOT NULL AUTO_INCREMENT,
		  title VARCHAR(255) NOT NULL,
		  description text(300) NOT NULL,
		  PRIMARY KEY id (id)
	      );";
	 
	  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);
	  
   }
}

//Adding to insert plugin css into theme header
wp_enqueue_style('style', '/wp-content/plugins/hide-widget-title/style.css', false, '1.0', 'screen');


function dw_show_hide_widget_options($widget, $return, $instance){
global $wpdb;
$site_uri = get_settings('siteurl');
?>
<script language="javascript" type="text/javascript" src="<?php echo bloginfo('siteurl');  ?>/wp-content/plugins/hide-widget-title/jsajax.js" ></script>	
<!-- <link type="text/css" href="<?php echo bloginfo('siteurl');  ?>/wp-content/plugins/hide-widget-title/style.css" rel="stylesheet" /> -->
<script type="text/javascript">
	function chkStatus(val) {
		var id = val.id;
		if(val.checked==true) {
			widgetHideDB(id);
		} else {
			widgetshowDB(id);
		}
	}
	
	// function to hide widget title from front end according to id
	function widgetHideDB(id) {
		var url_widget = "<?php echo $site_uri;?>/wp-content/plugins/hide-widget-title";
		var action="addwidgetoption";

		url_widget=url_widget+"/ajaxwidget.php";
		url_widget=url_widget+"?action="+action+"&id="+id;	

		var result_widget=url_widget;
		return get_response(result_widget,'displaywidget');
	}


	// function to show widget title to front end according to id
	function widgetshowDB(id1) {
		
		var url_widget1 = "<?php echo $site_uri;?>/wp-content/plugins/hide-widget-title";
		var actiondel="delwidgetoption";

		url_widget1=url_widget1+"/ajaxdelwidgetDB.php";
		url_widget1=url_widget1+"?actiondel="+actiondel+"&id1="+id1;	

		var result_widget1=url_widget1;
		return get_response(result_widget1,'displaywidget1');
	}
</script>
<div id="displaywidget"></div>
<div id="displaywidget1"></div>	
<?php 
	global $wpdb;
	$table_name = $wpdb->prefix . "displaywidget";
	foreach($widget as $key=>$value) {
	  if($key == 'id') {

		 /* code to select css values from database and write all css to style.css file */
		 $sql_DWidget = "Select * from ".$table_name." WHERE title='".$value."'";
		 $CssResponse = $wpdb->get_results($sql_DWidget);
		 $titleDB = $CssResponse[0]->title;
		 if($titleDB==$value) {
?>   
		 <p>
			<input type="checkbox" name="<?php echo $value;?>" id="<?php echo $value;?>" checked="checked" onchange="javascript:chkStatus(this);"> <span style="color:#21759B;font-weight:bold;"> Hide title from public users</span>

		 </p>    

<?php } else { ?>
			
		 <p>
			<input type="checkbox" name="<?php echo $value;?>" id="<?php echo $value;?>"  onchange="javascript:chkStatus(this);"> <span style="color:#21759B;font-weight:bold;"> Hide title from public users</span>
		 </p>    
<?php } ?>
<?php      
	 } // if braces closed
	} // foreach braces closed
}

add_action('in_widget_form', 'dw_show_hide_widget_options', 10, 3);
?>