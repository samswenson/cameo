<?php 
$blogheader = explode("wp-content",$_SERVER["SCRIPT_FILENAME"]);
include $blogheader[0]."wp-blog-header.php";
global $wpdb;
$site_uri = get_settings('siteurl');
$table_name = $wpdb->prefix . "displaywidget";
$action = $_GET['action'];

$title = $_GET['id'];
$description = "#".$title." h3 { display:none; }";

if($action=='addwidgetoption') {
	/* code to insert title need to hide from backend */
	$sql_insert = "INSERT INTO ".$table_name." SET 
				title		= '".$title."',
				description = '".$description."'";
	$results = $wpdb->query($sql_insert);
	


	/* code to select css values from database and write all css to style.css file */
	$sql_DWidget = "Select * from ".$table_name;
	$CssResponse = $wpdb->get_results($sql_DWidget);
	foreach($CssResponse as $CssClass) {
		$descCss .= $CssClass->description.'  ';
	}
	
	$blog_title = get_bloginfo('name'); // To display blog title
	$template_path = get_option('template');

	/* code to write style.css according to checkbox selection*/
	$myFile = ABSPATH."/wp-content/plugins/hide-widget-title/style.css";
	$fh = fopen($myFile, 'w') or die("can't open file");
	fwrite($fh,$descCss);
	fclose($fh);

	
	echo 'Hide title from public users.';
}

?>