=== Simple Post Thumbnails ===
Contributors: James Lao, Jason Schuller
Donate link: http://jameslao.com/
Tags: thumbnails, images, automatic, simple
Requires at least: 2.6
Tested up to: 2.8.4
Stable tag: 1.5

Adds a widget to the posting screen that makes creating post thumbnails a cinch.

== Description ==

This plugin adds a widget to the posting screen that allows you to add a post thumbnail by uploading an image from your computer or via a URL. The thumbnail is scaled and cropped to the correct size automatically.

Please visit the [documentation](http://press75.com/docs/simple-post-thumbnails/) for detailed instructions on installation and usage.

== Installation ==

1. Make sure that your host has the GD graphics extension for PHP enabled.
2. Upload the plugin file to your `wp-plugins/` folder.
3. Go the plugin management page and activate the plugin.
4. Add the `p75GetThumbnail(int $postID, [int $width, [int $height, [string $fileType]]])` function to your theme to get the URL of a thumbnail and `p75GetOriginalImage(int $postID)` to get the URL to the original image.
5. Go to Settings > Thumbnail Options to configure default values for the plugin.

== Frequently Asked Questions ==

= How do I add a thumbnail? =

Go to the posting screen and either upload an image from your computer or enter in the URL of the image in the form provided.

= I am getting errors when I try to upload thumbnails, what is wrong? =

Make sure that a folder called "`thumbnails`" was created in your `wp-content` folder and make sure it has write permissions (`755` works). If this folder does not exist, create it.

== Changelog ==

= 1.5 =
* Added short code support.
* Added feed support.
* Fixed thumbnail preview when default thumb is not set.

= 1.4 =
* Removed domain from the URL passed to timthumbs.
* Made the admin notices more informative and useful.
* Added some debug information to the options page that may help resolve issues.

= 1.3 =
* Changed all the folder variables to use WP native constants.
* Updated timthumb with latest fixes.


