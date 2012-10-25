<?php
/*
Plugin Name: Simple Post Thumbnails
Plugin URI: http://www.press75.com/the-simple-post-thumbnails-wordpress-plugin/
Description: Easily add thumbnail images to your posts. Brought to you by <a href="http://www.press75.com" title="Press75.com">Press75.com</a>. 
Version: 1.5
Author: James Lao
Author URI: http://jameslao.com/
*/

$pos = strpos(WP_CONTENT_URL, $_SERVER['HTTP_HOST']) + strlen($_SERVER['HTTP_HOST']);
define("P75_THUMB_WEB", substr(WP_CONTENT_URL, $pos) . '/thumbnails/'); // thumbnail url
define("P75_THUMB_DIR", WP_CONTENT_DIR . '/thumbnails/'); // thumbnail directory
define("SPT_VERSION", "1.5");

/**
 * Plugin activation.
 */
register_activation_hook(__FILE__, 'p75_sptActivate');

function p75_sptActivate()
{
	global $wpdb;
	
	// Set default thumbnail file type.
	add_option('p75_default_thumbnail_width', 200);
	add_option('p75_default_thumbnail_height', 150);
	add_option('p75_thumbnail_file_type', 'jpg');
	
}


// Check for thumbnail directory
if( !is_dir( P75_THUMB_DIR ) )
{
	// Attempt to create thumbnail directory if non-existent
	if( !@mkdir( P75_THUMB_DIR ) )
	{
		function p75_WarningThumbnailFolder()
		{
			echo '<div class="updated fade"><p>Simple Post Thumbnails could not create the thumbnail folder. Please create a folder named &quot;thumbnails&quot; in the &quot;wp-content&quot; folder of your WordPress installation. Go to the <a href="http://www.press75.com/documentation-support/simple-post-thumbnails-setup-usage/">plugin support page</a> for setup information.</p></div>';
		}
		add_action('admin_notices', 'p75_WarningThumbnailFolder');
	}
}

// Check for thumbnail directory
if( !is_writable( P75_THUMB_DIR ) )
{
	function p75_WarningThumbnailFolderNotWritable()
	{
		echo '<div class="updated fade"><p>Simple Post Thumbnails cannot write to the thumbnail folder. This is probably due to permissions. Please make sure the <code>thumbnails</code> folder inside your <code>wp-content</code> folder is is writable. Go to the <a href="http://www.press75.com/documentation-support/simple-post-thumbnails-setup-usage/">plugin support page</a> for setup information.</p></div>';
	}
	add_action('admin_notices', 'p75_WarningThumbnailFolderNotWritable');
}

// Check for GD support
if ( !function_exists("imagecreatetruecolor") )
{
	function p75_WarningNoGD()
	{
		echo '<div class="updated fade"><p>The Simple Post Thumbnails plugin relies on the GD graphics extension to function. GD was not detected. Check with your host to make sure it is enabled.</p></div>';
	}
	add_action('admin_notices', 'p75_WarningNoGD');
}

/**
 * Post admin hooks
 */
add_action('admin_menu', "p75_thumbnailAdminInit");
add_action('save_post', 'p75_saveThumb');

function p75_thumbnailAdminInit()
{
	if( function_exists("add_meta_box") )
		add_meta_box("p75-thumbnail-posting", "Post Thumbnail Options", "p75_thumbnailPosting", "post", "advanced");
	
	add_options_page('Simple Post Thumbnails Options', 'Thumbnail Options', 8, 'thumbnailoptions', 'p75_thumbnailOptionsAdmin');
}

function p75_thumbnailOptionsAdmin()
{
	$fileType = get_option("p75_thumbnail_file_type");
?>
	<div class="wrap">
	<h2>Simple Post Thumbnails Options</h2>
	
		<form method="post" action="options.php">
			<?php wp_nonce_field('update-options'); ?>
	
			<table class="form-table">
				<tr valign="top">
					<th style="white-space:nowrap;" scope="row"><label for="p75_default_thumbnail"><?php _e("Default thumbnail"); ?>:</label></th>
					<td><input id="p75_default_thumbnail" type="text" name="p75_default_thumbnail" value="<?php echo get_option('p75_default_thumbnail'); ?>" /></td>
					<td style="width:100%;">The URL to the thumbnail to use if one is not set.</td>
				</tr>
				
				<tr valign="top">
					<th style="white-space:nowrap;" scope="row"><label for="p75_thumbnail_file_type"><?php _e("Thumbnail file type"); ?>:</label></th>
					<td>
						<select id="p75_thumbnail_file_type" name="p75_thumbnail_file_type">
							<option value="jpg"<?php if ( $fileType=="jpg" ) echo ' selected="selected"' ?>>JPEG</option>
							<option value="png"<?php if ( $fileType=="png" ) echo ' selected="selected"' ?>>PNG</option>
							<option value="gif"<?php if ( $fileType=="gif" ) echo ' selected="selected"' ?>>GIF</option>
						</select>
					</td>
					<td>The image file type to be used for thumbnails.</td>
				</tr>
				
				<tr valign="top">
					<th style="white-space:nowrap;" scope="row"><label for="p75_default_thumbnail_width"><?php _e("Default thumbnail width"); ?>:</label></th>
					<td><input id="p75_default_thumbnail_width" type="text" name="p75_default_thumbnail_width" value="<?php echo get_option('p75_default_thumbnail_width'); ?>" /></td>
					<td>The width of the thumbnail to use if not set. For experienced users.</td>
				</tr>
				
				<tr valign="top">
					<th style="white-space:nowrap;" scope="row"><label for="p75_default_thumbnail_height"><?php _e("Default thumbnail height"); ?>:</label></th>
					<td><input id="p75_default_thumbnail_height" type="text" name="p75_default_thumbnail_height" value="<?php echo get_option('p75_default_thumbnail_height'); ?>" /></td>
					<td>The height of the thumbnail to use if not set. For experienced users.</td>
				</tr>
			</table>
	
			<input type="hidden" name="action" value="update" />
			<input type="hidden" name="page_options" value="p75_default_thumbnail_width,p75_default_thumbnail_height,p75_default_thumbnail,p75_thumbnail_file_type" />
	
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
<a id="spt_debug_toggle" href="#" title="status">Status</a>
<pre id="spt_debug" style="display:none;background:#ccc;border:1px solid #aaa;padding:10px;">P75_THUMB_WEB = <?php echo P75_THUMB_WEB . "\n"; ?>
PLUGIN VERSION = <?php echo SPT_VERSION . "\n"; ?>
PHP VERSION = <?php echo phpversion() . "\n"; ?>
GD IS ENABLED = <?php echo (function_exists('imagecreatetruecolor') ? "YES" : "NO") . "\n"; ?>
THUMBNAIL FOLDER IS WRITABLE = <?php echo (is_writable(P75_THUMB_DIR) ? "YES" : "NO") . "\n"; ?></pre>
<script>
	jQuery("#spt_debug_toggle").click(function(){
		jQuery("#spt_debug").toggle();
	});
</script>
	</div>

<?php
}

/**
 * Code for the meta box.
 */
function p75_thumbnailPosting()
{
	global $post_ID;
?>
	<script type="text/javascript">
		document.getElementById("post").setAttribute("enctype","multipart/form-data");
		document.getElementById('post').setAttribute('encoding','multipart/form-data');
	</script>

	<?php
		$thumb = p75GetThumbnail($post_ID);
		if ( $thumb )
		{
	?>
	<div style="float: left; margin-right: 10px;">
		<img style="border: 1px solid #ccc; padding: 3px;" src="<?php echo $thumb . (p75HasThumbnail($post_ID) ? "&amp;nocache=" . time() : ""); ?>" alt="Thumbnail preview" />
	</div>
	<?php
		}
		else
		{
	?>
	<div style="float: left; margin-right: 10px; width: 200px; height: 150px; line-height: 150px; border: solid 1px #ccc; text-align: center;">Thumbnail preview</div>
	<?php } ?>
	
	<div style="float: left;">
		<p>
			<label for="p75-thumb-url-upload"><?php _e("Upload via URL, or Select Image (Below)"); ?>:</label><br />
			<input style="width: 300px; margin-top:5px;" id="p75-thumb-url-upload" name="p75-thumb-url-upload" type="text" />
		</p>	
		<p>
			<p><label for="p75-thumbnail"><?php _e("Upload a thumbnail"); ?>:</label><br />
			<input id="p75-thumbnail" type="file" name="p75-thumbnail" />
		</p>
		<p><input id="p75-thumb-delete" type="checkbox" name="p75-thumb-delete"> <label for="p75-thumb-delete"><?php _e("Delete thumbnail"); ?></label></p>
		 
		<p style="margin:10px 0 0 0;"><input id="publish" class="button-primary" type="submit" value="<?php _e("Update Post"); ?>" accesskey="p" tabindex="5" name="save"/></p>
	</div>
	
	<div class="clear"></div>
<?php
}

/**
 * Saves the thumbnail image as a meta field associated
 * with the current post. Runs when a post is saved.
 *
 * @param The post ID
 */
function p75_saveThumb( $postID )
{
	global $wpdb;

	// Get the correct post ID if revision.
	if ( $wpdb->get_var("SELECT post_type FROM $wpdb->posts WHERE ID=$postID")=='revision')
		$postID = $wpdb->get_var("SELECT post_parent FROM $wpdb->posts WHERE ID=$postID");

	if ( $_POST['p75-thumb-delete'] )
	{
		@unlink(P75_THUMB_DIR . get_post_meta($postID, '_p75_thumbnail', true));
		delete_post_meta($postID, '_p75_thumbnail');
	}
	elseif ( $_POST['p75-thumb-url-upload'] || !empty($_FILES['p75-thumbnail']['tmp_name']) )
	{
		if ( !empty($_FILES['p75-thumbnail']['name']) )
			preg_match("/(\.(?:jpg|jpeg|png|gif))$/i", $_FILES['p75-thumbnail']['name'], $matches);
		else
			preg_match("/(\.(?:jpg|jpeg|png|gif))$/i", $_POST['p75-thumb-url-upload'], $matches);
		
		$thumbFileName = $postID . strtolower($matches[0]);
   
		// Location of thumbnail on server.
		$loc = P75_THUMB_DIR . $thumbFileName;
		
		$thumbUploaded = false;
   
		if ( $_POST['p75-thumb-url-upload'] )
		{
			// Try just using fopen to download the image.
			if( ini_get('allow_url_fopen') )
			{
				copy($_POST['p75-thumb-url-upload'], $loc);
				$thumbUploaded = true;
			}
			else
			
			// If fopen doesn't work, try cURL.
			if( function_exists('curl_init') )
			{
				$ch = curl_init($_POST['p75-thumb-url-upload']);
				$fp = fopen($loc, "wb");
   
				$options = array(CURLOPT_FILE => $fp,
					CURLOPT_HEADER => 0,
					CURLOPT_FOLLOWLOCATION => 1,
					CURLOPT_TIMEOUT => 60);
				curl_setopt_array($ch, $options);
				
				curl_exec($ch);
				curl_close($ch);
   
				fclose($fp);
				$thumbUploaded = true;
			}
		}
		else
   
		// Attempt to move the uploaded thumbnail to the thumbnail directory.
		if ( !empty($_FILES['p75-thumbnail']['tmp_name']) && move_uploaded_file($_FILES['p75-thumbnail']['tmp_name'], $loc) )
			$thumbUploaded = true;
		
		if ( $thumbUploaded )
		{
			if ( !update_post_meta($postID, '_p75_thumbnail', $thumbFileName) )
				add_post_meta($postID, '_p75_thumbnail', $thumbFileName);
		}

	}
}

function p75_thumbnail_short_code($atts, $content=null) {
	global $post;
	
	extract(shortcode_atts(array(
		'id' => $post->ID,
		'width' => "",
		'height' => "",
		'filetype' => ""
	), $atts));
	
	return sprintf("<img src='%s' alt='post thumbnail' />\n", p75GetThumbnail($id, $width, $height, $filetype));
}

add_shortcode('simple_thumbnail', 'p75_thumbnail_short_code');

function p75_feed_thumbnail_filter($content)
{
	global $post;
	if ( is_feed() && p75HasThumbnail($post->ID) )
		return sprintf("<p><img src='%s' alt='post thumbnail' /></p>\n%s", p75GetThumbnail($post->ID), $content);
	else
		return $content;
}

add_filter("the_content", "p75_feed_thumbnail_filter");

/**
 * Gets the thumbnail.
 *
 * @param $postID The post ID of the thumbnail.
 * @param $width The width of the thumbnail (optional)
 * @param $height The height of the thumbnail (optional)
 * @param $fileType The file type of the thumbnail; jpg, png, or gif (optional)
 * @return The URL of the thumbnail.
 */
function p75GetThumbnail($postID, $width="", $height="", $fileType="")
{
	// Fetch default values for optional paramters if not specified.
	if ( empty($width) )
		$width = get_option("p75_default_thumbnail_width");
	if ( empty($height) )
		$height = get_option("p75_default_thumbnail_height");
	if ( empty($fileType) )
		$fileType = get_option("p75_thumbnail_file_type");

	if ( $thumb = get_post_meta($postID, '_p75_thumbnail', true) )
		return WP_PLUGIN_URL . "/simple-post-thumbnails/timthumb.php?src=" . P75_THUMB_WEB . $thumb . "&amp;w=" . $width . "&amp;h=" . $height . "&amp;zc=1&amp;ft=" . $fileType;
	else
		return get_option('p75_default_thumbnail');
}

/**
 * Gets the original image.
 *
 * @param $postID The post ID.
 * @return The web URL to the original image, false if does not exist.
 */
function p75GetOriginalImage($postID)
{
	if ( $pic = get_post_meta($postID, '_p75_thumbnail', true) )
		return P75_THUMB_WEB . $pic;
	else
		return false;
}

function p75HasThumbnail($postID)
{
	return (bool) get_post_meta($postID, '_p75_thumbnail', true);
}

?>
