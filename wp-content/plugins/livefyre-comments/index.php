<?php
/*
Plugin Name: Livefyre Realtime Comments
Plugin URI: http://livefyre.com/wordpress#
Description: Implements livefyre realtime comments for wordpress
Author: Livefyre, Inc.
Version: 3.17
Author URI: http://livefyre.com/
*/


//wp_options usage:
global $livefyre_wp_options;
$livefyre_wp_options=array(
'livefyre_activity_id',
'livefyre_blogname', // - name (id) of the livefyre record associated with this blog
'livefyre_ctype_migrated', // - flag that we ran the migration to fix very old installations
'livefyre_db_version', // - current iteration of the livefyre specific db schema
'livefyre_import_status', /* - track the state of migration from wp comments => livefyre comments
    -started: a request was sent to livefyre to initiate the import
    -csv_uploaded: livefyre posted a csv which was imported*/
'livefyre_secret', // - shared key used to sign requests to/from livefyre
'livefyre_active_sync'); // - if we know we are missing postback data, this flag is set to more aggressively schedule sync jobs

global $livefyre_db_version;
global $livefyre_activity_table;
global $livefyre_top_domain;
global $livefyre_site_domain;
global $livefyre_plugin_version;
global $livefyre_quill_url;
global $livefyre_admin_url;
global $livefyre_debug_mode;
global $livefyre_bootstrap_url;
global $livefyre_assests_url;
global $livefyre_comment_filter_enabled;
global $wpdb;

$livefyre_debug_mode = false;

$livefyre_top_domain = 'livefyre.com';
$livefyre_www_domain = 'www.livefyre.com';
$livefyre_site_domain = "rooms.livefyre.com";

$livefyre_http_url = "http://$livefyre_www_domain";
$livefyre_assests_url = "http://zor.$livefyre_top_domain";
$livefyre_quill_url = "http://quill.$livefyre_top_domain";
$livefyre_admin_url = "http://admin.$livefyre_top_domain";
$livefyre_bootstrap_url = "http://bootstrap.$livefyre_top_domain";

$livefyre_db_version = "1.0";
$livefyre_plugin_version = "3.17";

$livefyre_activity_table = $wpdb->prefix . "livefyre_activity_map";
$livefyre_comment_map_table = $wpdb->prefix . "livefyre_comment_map";

if (isset($_GET['livefyre_cross_domain'])) {
       exit;
}

function livefyre_utf8_to_unicode_code($utf8_string)
{
    $expanded = iconv("UTF-8", "UTF-32", $utf8_string);
    return unpack("L*", $expanded);
}

function livefyre_unicode_code_to_utf8($unicode_list)
{ 
    $result = "";
    foreach($unicode_list as $key => $value) {
        $one_character = pack("L", $value);
        $result .= iconv("UTF-32", "UTF-8", $one_character);
    }
    return $result;
}

function livefyre_filter_unicode_longs($long) {
    return ($long == 0x9 || $long == 0xa || $long == 0xd || ($long >= 0x20 && $long <= 0xd7ff) || ($long >= 0xe000 && $long <= 0xfffd) || ($long >= 0x10000 && $long <= 0x10ffff));
}

function livefyre_comment_data_filter($comment, $test=false) {
    if ($test || livefyre_check_utf_conversion()) {
        $before=$comment;
        if (function_exists( 'iconv' )) {
            $unicode=array_filter(livefyre_utf8_to_unicode_code($comment), "livefyre_filter_unicode_longs");
            $comment = livefyre_unicode_code_to_utf8( $unicode);
        }
        $after=$comment;
        if (get_option('livefyre_cleaned_data','no')=='no' && $before!=$after) {
            update_option('livefyre_cleaned_data','yes');
            livefyre_error_report("before and after are different when exporting content, this means we saw bad data and cleaned it up\nbefore:\n$befo
re\n\nafter:\n$after");
        }
    }
    $comment=preg_replace('/\&/', '&amp;',$comment);
    $comment=preg_replace('/\>/', '&gt;',$comment);
    $comment=preg_replace('/\</', '&lt;',$comment);
    return $comment;
}

function livefyre_strip_cdata($data) {
    $data=preg_replace('/\<!\[CDATA\[/', '', $data);
    $data=preg_replace('/\]\]\>/', '', $data);
    return $data;
}

function livefyre_check_utf_conversion() {
    global $livefyre_comment_filter_enabled;
    if (!isset($livefyre_comment_filter_enabled)) {
        $test_string='Testing 1 2 3!! ?/#@';
        $converted=livefyre_comment_data_filter($test_string, true);
        if ($converted!=$test_string) {
            $livefyre_comment_filter_enabled=false;
        } else {
            $livefyre_comment_filter_enabled=true;
        }
    }
    return $livefyre_comment_filter_enabled;
}

function livefyre_skip_trackback_filter($c) {
    if ($c->comment_type == 'trackback' || $c->comment_type == 'pingback' || $c->comment_agent=='Livefyre, Inc. Comments Agent') { return false; }
    return true;
}

function livefyre_returned_from_setup() {
    return (isset($_GET['lf_login_complete']) && $_GET['lf_login_complete']=='1');
}

function livefyre_new_import_session($resume=false) {
    global $livefyre_quill_url, $livefyre_plugin_version;
    
    $blogId = get_option('livefyre_blogname', '');
    if ($blogId == '') {
        return false;
    }
    
    $url = "$livefyre_quill_url/import/wordpress/$blogId/";
    if ($resume) {
        $url = $url."resume";
    } else {
        $url = $url."start";    
    }
    
    $resp = livefyre_request($url, false, "{\"plugin-version\":\"$livefyre_plugin_version\"}", 
                'POST', array('Content-Type' => 'application/json'));
    if (is_wp_error($resp)) {
        $status='error';
        $message=$resp->get_error_message();
    } else {
        $json=json_decode($resp);
        $status = $json->status;
        $message = $json->message;
    }

    if ($status == 'import-already-exists' && !$resume) {
        return livefyre_new_import_session(true);
    }
    if ($status != 'error') {
        update_option('livefyre_import_status','started');
        return true;
    } else {
        livefyre_error_report('Error requesting import session, message: '.$message);
        return false;
    }
}

add_action('init', 'livefyre_health_check');

function livefyre_health_check() {
    global $livefyre_debug_mode, $wpdb, $livefyre_wp_options;
    if (function_exists( 'home_url' )) {
        $home_url=home_url();
    } else {
        $home_url=get_option('home');
    }
    //make sure we're allowed to import comments
    if (isset($_GET['livefyre_ping_hash'])) {
        //check the signature
        if ($_GET['livefyre_ping_hash']!=md5($home_url)) {
            echo "hash does not match! my url is: $home_url";
            exit;
        } else {
            echo "\nhash matched for url: $home_url\n";
            echo "site's server thinks the time is: ".gmdate('d/m/Y H:i:s', time());
            $notset='[NOT SET]';
            foreach ($livefyre_wp_options as $optname) {
                echo "\n\nlivefyre option: $optname";
                $optval=get_option($optname, $notset);
                #obscure the secret key (first 2 chars only)
                $val=(($optname=='livefyre_secret' && $optval!=$notset) ? substr($optval, 0, 2) : $optval );
                echo "\n          value: $val";
            }
            exit;
        }
    }
}

#No sig required
#wordpress url, what time the blog thinks it is, first 2 chars of secret key
#should work for a plugin in ANY state.  ?livefyre_ping_hash=[md5(url)]  even if the hash is wrong, it should say "bad hash"

add_action('init', 'livefyre_check_import_request');

function livefyre_check_import_request() {
    global $livefyre_debug_mode, $wpdb;
    //make sure we're allowed to import comments
    if (isset($_GET['livefyre_comment_import']) && isset($_GET['offset'])) {
        //check the signature
        livefyre_debug('Comment import request received from Livefyre.');
        $key=get_option('livefyre_secret');
        $string=('import|'.$_GET['offset'].'|'.$_POST['sig_created']);
        
        livefyre_debug(" -comment import request sig inputs: $string, input sig: " .$_POST['sig']);

        if (getHmacsha1Signature(base64_decode($key), $string)!=$_POST['sig'] || abs($_POST['sig_created']-time())>259200) {
            livefyre_debug(" -sig failed");
            echo 'sig-failure';
            exit;
        } else {
            livefyre_debug(" -sig correct, rendering");
            $blogId = get_option('livefyre_blogname', '');
            if ($blogId != '') {
                //extract xml
                header('Content-type: application/vnd.livefyre.wpxml+xml');
                $response = livefyre_extractXML($blogId, intval($_GET['offset']));
                echo $response;
                exit;
            } else {
                livefyre_debug(" -tried to render, but no blogid");
                echo 'missing-blog-id';
                exit;
            }
        }
    }
}

add_action( 'init', 'lf_check_activity_map_import' );

function lf_check_activity_map_import() {
    # A postback that indicates that the import is complete, and we can download the activity_map.
    if (isset($_POST['import_complete'])) {
        echo livefyre_fetch_activity_map();
        status_header(202);
        exit;
    }
}

function livefyre_fetch_activity_map() {
    global $livefyre_quill_url;

    $blog_id = get_option('livefyre_blogname', '');
    if ($blog_id == '') {
        return false;
    }

    try {
        while (livefyre_fetch_activity_map_chunk($blog_id)) {}
        // Reset the missing comments flag
        // If a user starts an import which fails in fetching the activity map the deactivates the plugin
        // any WordPress comments made while the plugin was deactivated will need to be imported in a new import.

        delete_option('livefyre_missing_comments');
        update_option('livefyre_import_status','csv_uploaded');
        livefyre_request("$livefyre_quill_url/import/wordpress/$blog_id/done");
    } catch (Exception $e) {
        update_option('livefyre_import_status','activity_map_error');
        throw $e;
    }
}

function livefyre_fetch_activity_map_chunk($blog_id) {
    global $livefyre_quill_url, $wpdb, $livefyre_activity_table, $livefyre_debug_mode;

    $last_activity=livefyre_get_activity_id();
    if ($last_activity == '0') {
        $last_activity = 0;       
    }

    $chunk_size=10000;

    if( !class_exists( 'WP_Http' ) ) {
        include_once( ABSPATH . WPINC. '/class-http.php' );
    }

    $request = new WP_Http;
    $result = $request->get( "$livefyre_quill_url/import/wordpress/$blog_id/results?last_activity_id=$last_activity&count=$chunk_size" );
    
    if (is_wp_error($result)) {
        livefyre_debug("Error fetching the activity map, activity_id=$last_activity, count=$chunk_size");
        throw new Exception("Error fetching activity map.");
    }
    
    if (json_decode($result['body']) != null) {
        // A JSON response means this is not a successful reponse
        livefyre_debug("Error response from activity map request: response=$result, activity_id=$last_activity, count=$chunk_size");
        throw new Exception("Invalid response activity_map response: $result");
    }
    
    $rows=explode("\n",$result['body']);

    $has_activity = false;
    foreach ($rows as $row) {
        if ($row == '') {
            break;
        }
        try {
            $rowparts=explode(",",$row);
            if (count($rowparts) != 3) {
                throw new Exception("Invalid response activity_map response: $row");
            }
            
            livefyre_debug("Comment import request received from Livefyre, inserting: $rowparts[0], $rowparts[1], $rowparts[2]" );

            livefyre_set_activity_id($rowparts[0]);
            if ($wpdb->query("insert into $livefyre_activity_table values ( $rowparts[0], $rowparts[1], $rowparts[2] )")) {
                $has_activity = true;
            } else {
                livefyre_debug("Error inserting $rowparts[0], $rowparts[1], $rowparts[2] into activity table.");
            }
            
            
        } catch (Exception $e) {
            livefyre_debug("Error processing activity_map download: ".$e->getMessage());
            throw $e;
        }
    }
    return $has_activity;
}

function livefyre_extractXML($blogId, $offset=0) {
    $maxqueries=50;
    $maxlength=500000;
    $index=$offset;
    $next_chunk=false;
    $total_queries=0;
    do {
        $total_queries++;
        if ($total_queries>$maxqueries) {
            $next_chunk=true;
            break;
        }
        $args = array(
            'post_type' => 'any',
            'numberposts' => 20,
            'offset' => $index
        );
        $myposts = get_posts($args);
        if (!isset($articles)) {
            $articles='';
        }
        $inner_idx=0;
        foreach ($myposts as $post) {
            if($parent_id = wp_is_post_revision($post->ID)) {
                $post_id = $parent_id;
            } else {
                $post_id = $post->ID;
            }
            $newArticle='<article id="'.$post_id.'"><title>'.livefyre_comment_data_filter($post->post_title).'</title><source>'.get_permalink($post->ID).'</source>';
            if ($post->post_date_gmt!=null && !strstr($post->post_date_gmt, '0000-00-00')) {
                $newArticle.='<created>'.preg_replace('/\s/', 'T' ,$post->post_date_gmt).'Z</created>';
            }
            $comment_array = get_approved_comments($post->ID);
            $comment_array = array_filter($comment_array, 'livefyre_skip_trackback_filter');
            foreach($comment_array as $comment){
                $comment_content=livefyre_comment_data_filter($comment->comment_content);
                if ($comment_content=="") {
                    continue; #don't sync blank
                }
                $commentParent=($comment->comment_parent ? " parent-id=\"$comment->comment_parent\"" : '');
                $commentXML="<comment id=\"$comment->comment_ID\"$commentParent>";
                $commentXML.='<author format="html">'.livefyre_comment_data_filter($comment->comment_author).'</author>';
                $commentXML.='<author-email format="html"><![CDATA['.livefyre_strip_cdata(livefyre_comment_data_filter( $comment->comment_author_email )). ']]></author-email>';
                $commentXML.='<author-url format="html"><![CDATA['.livefyre_strip_cdata(livefyre_comment_data_filter( $comment->comment_author_url)) .']]></author-url>';
                $commentXML.='<body format="wphtml">'.$comment_content.'</body>';
                $use_date=$comment->comment_date_gmt;
                if ($use_date=='0000-00-00 00:00:00Z') {
                    $use_date=$comment->comment_date;
                }
                if ($use_date!=null && !strstr($use_date, '0000-00-00')) {
                    $commentXML.='<created>'.preg_replace('/\s/', 'T' ,$use_date).'Z</created>';
                } else {
                    // we need to supply a datetime so the XML parser does not fail
                    $now = new DateTime;
                    $commentXML.='<created>'.$now->format('Y-m-d\TH:i:s\Z').'</created>';
                }
                $commentXML.='</comment>';
                $newArticle.=$commentXML;
            }
            $newArticle.='</article>';
            if (strlen($newArticle)+strlen($articles) > $maxlength && strlen($articles)) {
                $next_chunk=true;
                break;
            } else {
                $inner_idx+=1;
                $articles.=$newArticle;
            }
            unset($newArticle);
        }
    } while (count($myposts)!=0 && !$next_chunk && ($index=$index+10));
    if (strlen($articles)==0) {
        return 'no-data';
    } else {
        return 'to-offset:'.($inner_idx+$index)."\n".livefyre_wrap_import_xml($articles);    
    }
}

function livefyre_wrap_import_xml(&$articles) {
    return '<?xml version="1.0" encoding="UTF-8"?><site xmlns="http://livefyre.com/protocol" type="wordpress">'.$articles.'</site>';
}

function livefyre_request($path, $upload=false, $data=null, $type='POST', $headers=null) {
    if( !class_exists( 'WP_Http' ) )
    include_once( ABSPATH . WPINC. '/class-http.php' );
    $request = new WP_Http;
    
    if ($headers == null) {
        $headers = array();
    }
    
    if ($upload) {
        $headers['Content-Type'] = 'multipart/form-data';
        $result = $request->request( $path, array( 'method' => 'POST', 'body' => $data, 'headers'=>$headers, 'timeout'=>600) );
    } else {
        $result = $request->request( $path, array( 'method' => $type, 'body' => $data, 'headers' => $headers) );
    }
    unset($request);
    if (is_wp_error($result)) {
        $respond_with='Error - '.$result->get_error_message();
    } else {
        $respond_with=$result['body'];
    }
    return $respond_with;
}

function livefyre_site_rest_url() {
    global $livefyre_http_url;
    return $livefyre_http_url.'/site/'.get_option('livefyre_blogname');
}

function livefyre_debug($message) {
    global $livefyre_debug_mode, $wpdb;
    if ($livefyre_debug_mode) {
        $wpdb->query("INSERT INTO livefyre_debug_blobs (text) VALUES ('$message');");
    }
}

function livefyre_error_report($message) {
    livefyre_debug($message);
    $postdata=array('message'=>$message);
    livefyre_request(livefyre_site_rest_url().'/error', false, $postdata);
}

function livefyre_comments_off() {
    return (get_option('livefyre_blogname', '')=='');
}

function livefyre_show_comments(){
    return (is_single() || is_page()) && !is_preview();
}

function livefyre_comments($cmnts) {
  return dirname(__FILE__) . '/comments.php';
}

function livefyre_comments_number($count, $post) {
  global $post;
  return '<span data-lf-article-id="' . $post->ID . '" data-lf-site-id="' . get_option('livefyre_blogname', '') . '" class="livefyre-commentcount">'.$count.'</span>';
}

function lf_embed_head_script() {
    global $post, $livefyre_http_url, $livefyre_assests_url;
    if (comments_open() && (is_single() || is_page()) && !is_preview()) {// is this a post page?
        if($parent_id = wp_is_post_revision($post->ID)) {
            $post_id = $parent_id;
        } else {
            $post_id = $post->ID;
        }
        
        $article_id_param='&article_id='.$post_id;
        
        echo '<script type="text/javascript" src="'.$livefyre_http_url.'/wjs/v1.0/javascripts/livefyre_embed.js?platform=wordpress#bn='.get_option('livefyre_blogname').$article_id_param.'"></script>';
    }
    if (!is_singular()) {
        # if we're not displaying an article page, assume commment counts could be required
        echo '<script type="text/javascript" src="'.$livefyre_assests_url.'/wjs/v1.0/javascripts/CommentCount.js"></script>';
    }
}

function lf_embed_head_test() {
    echo '<!--##LivefyreHeadTestOK##-->';
    if (get_option('livefyre_blogname', '')!='') {
        echo '<!--##LivefyreHeadTestBlognameOK##-->';
    } 
    if (get_option('livefyre_importdb','')=='completed') {
        echo '<!--##LivefyreHeadTestImportOK##-->';
    }
}

add_action('wp_head', 'lf_embed_head_test');

function livefyre_plugin_menu() {
    add_comments_page( 'Livefyre Settings', (get_option('livefyre_import_status','')=='started' ? 'Livefyre (importing...)' : 'Livefyre'), 'manage_options', 'livefyre', 'livefyre_plugin_options' );
}

function livefyre_plugin_options() {
  if (!current_user_can('manage_options')) {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }

  include(dirname(__FILE__) . "/options.php");
}

/* begin Livefyre's additions to support a remote signed comment post method*/
function getHmacsha1Signature($key, $data) {
    //convert binary hash to BASE64 string
    return base64_encode(hmacsha1($key, $data));
}

// encrypt a base string w/ HMAC-SHA1 algorithm
function hmacsha1($key,$data) {
    $blocksize=64;
    $hashfunc='sha1';
    if (strlen($key)>$blocksize) {
        $key=pack('H*', $hashfunc($key));
    }
    $key=str_pad($key,$blocksize,chr(0x00));
    $ipad=str_repeat(chr(0x36),$blocksize);
    $opad=str_repeat(chr(0x5c),$blocksize);
    $hmac = pack( 'H*',$hashfunc( ($key^$opad).pack( 'H*',$hashfunc( ($key^$ipad).$data ) ) ) );
    return $hmac;
}

add_action( 'init', 'lf_check_show_tags' );

function lf_check_show_tags() {
    if (isset($_GET['livefyre_showtags']) && $_GET['livefyre_showtags']) {
        if($parent_id = wp_is_post_revision($_GET['post_id'])) {
            $post_id = $parent_id;
        } else {
            $post_id = $_GET['post_id'];
        }
        $tags = get_the_tags($post_id);
        if ($tags) {
            $tagnames=array();
            foreach($tags as $tag) {
                array_push($tagnames, $tag->name);
            }
            echo implode(',', $tagnames);
        }
        exit;
    }
}

function lf_wp_is_sync_status_req() {
    return (isset($_GET['lf_active_sync_status']));
}

function livefyre_post_param($name, $plain_to_wp_html=false, $default=null) {
    $in=( isset($_POST[$name]) ) ? trim($_POST[$name]) : $default;
    if ($plain_to_wp_html) {
        $out = str_replace("&", "&amp;", $in);
        $out = str_replace("<", "&lt;", $out);
        $out = str_replace(">", "&gt;", $out);
    } else {$out=$in;}
    return $out;
}

add_action('init', 'livefyre_comment_update');
 
function livefyre_comment_update() {
    global $livefyre_plugin_version;
    if (isset($_GET['lf_wp_comment_postback_request']) && $_GET['lf_wp_comment_postback_request']=='1') {
        livefyre_do_sync();
        // Instruct the backend to use the site sync postback mechanism for future updates.
        echo "{\"status\":\"ok\",\"plugin-version\":\"$livefyre_plugin_version\",\"message\":\"sync-initiated\"}";
        exit;
    }
}

function livefyre_insert_activity($data) {
    global $wpdb, $livefyre_activity_table;
    if (isset($data['lf_comment_parent']) && $data['lf_comment_parent']!=null) {
        $wp_comment_parent = livefyre_get_wp_comment_id($data['lf_comment_parent']);
        if ($wp_comment_parent==null) {
            //something is wrong. might want to log this, essentially flattening because parent is not mapped
        }
    } else {$wp_comment_parent=null;}
    $wp_comment_id = livefyre_get_wp_comment_id($data['lf_comment_id']);
    $at=$data['lf_action_type'];
    $data['comment_approved']=( (isset($data['lf_state']) && $data['lf_state']=='active') ? 1 : 0);
    $data['comment_parent']=$wp_comment_parent;

    if (in_array($at, array('comment-add', 
                            'comment-moderate:mod-approve', 
                            'comment-moderate:mod-hide', 
                            'comment-moderate:mod-minimize', 
                            'comment-update')) && $wp_comment_id!=null) {
        //update existing comment
        $data['comment_ID']=$wp_comment_id;
        if (isset($data['comment_content']) && $data['comment_content']!='') {
            if (substr($at, 0, 16) == 'comment-moderate') {
                # Remove actor info, we don't want to change that
                unset($data['comment_author']);
                unset($data['comment_author_email']);
                unset($data['comment_author_url']);
                unset($data['comment_author_IP']);
            }
            wp_update_comment($data);
        }
    } else if ($at=='comment-add') {
        //new comment
        if (!isset($data['comment_content'])) {
            livefyre_error_report('comment_content missing for synched activity id:'.$data['lf_activity_id']);
        }
        if (isset($data['wp_comment_id'])) {
            //livefyre migrated the mapping from the old (livefyre-maintained) mapping
            $wp_comment_id=(int) $data['wp_comment_id'];
        } else {
            //this really is completely new to wordpress
            $wp_comment_id=wp_insert_comment($data);
        }
    } else {
        return false; //we do not know how to handle this condition
    }
    
    livefyre_set_activity_id($data['lf_activity_id']);
    if (!$wpdb->insert( $livefyre_activity_table, 
                        array( 'lf_activity_id' => $data['lf_activity_id'], 'lf_comment_id' => $data['lf_comment_id'], 
                                'wp_comment_id' => $wp_comment_id), 
                        array( '%s', '%s', '%s', '%s' ))) {
        livefyre_debug("Error inserting activity data: lf_activity_id=".$data['lf_activity_id'].", lf_comment_id=".$data['lf_comment_id'].", wp_comment_id=$wp_comment_id");
        return false;
    }

    return true;
}

add_action( 'livefyre_sync', 'livefyre_do_sync' );

function livefyre_do_sync() {
    /*fetch 200 comments from the livefyre server, providing last activity id we have
    schedule the next sync if we get a full 200. if there are no more comments, no new sync
    */
    global $wpdb, $livefyre_debug_mode;
    
    livefyre_debug("Livefyre synched at ".time());
    
    $max_activity = livefyre_get_activity_id();
    if ($max_activity == '0') {
        $final_path_seg='';
    } else {
        $final_path_seg=$max_activity.'/';
    }
    
    $url=livefyre_site_rest_url().'/sync/'.$final_path_seg;
    $sigcreated_param='sig_created='.time();
    $key=get_option('livefyre_secret');
    $url.='?'.$sigcreated_param.'&sig='.urlencode(getHmacsha1Signature(base64_decode($key), $sigcreated_param));
    $str_comments=livefyre_request($url);
    $json_array=json_decode($str_comments);
    if (!is_array($json_array)) {
        livefyre_schedule_sync();
        livefyre_error_report('Error during livefyre_do_sync:'.'Invalid response (not a valid json array) from sync request to url: '.$url.' it responded with: '.$str_comments);
        return;
    }

    $last_message=''; //works around a bug with more-data being sent twice when we're really in the finished state
    foreach ($json_array as $json) {
        if ($json->message_type=='error') {
            livefyre_schedule_sync(); //we've been notified.  hopefully it can be fixed for next synch
            return;
        } else if ($json->message_type=='more-data') {
            livefyre_debug(time()." got more data scheduling for 15s");
            livefyre_schedule_sync(true);  //schedule a new job almost immediately
            return;
        } else if ($json->message_type=='lf-activity') {
            $comment_date =(int) $json->created;
            $comment_date = get_date_from_gmt(date('Y-m-d H:i:s', $comment_date));
            $data = array(
                'lf_activity_id' =>  $json->activity_id,
                 'lf_action_type' => $json->activity_type,
                'comment_post_ID' => $json->article_identifier,
                'comment_author' => $json->author,
                'comment_author_email' => $json->author_email,
                'comment_author_url' => $json->author_url,
                'comment_type' => '',
                'lf_comment_parent' => $json->lf_parent_id,
                'lf_comment_id' => $json->lf_comment_id,
                'user_id' => null,
                'comment_author_IP' => $json->author_ip,
                'comment_agent' => 'Livefyre, Inc. Comments Agent',
                'comment_date' => $comment_date,
                'lf_state' => $json->state
            );
            if (isset($json->wp_comment_id)) {
                $data['wp_comment_id']=$json->wp_comment_id;
            }
            if (isset($json->body_text)) {
                $data['comment_content'] = $json->body_text;
            }
            livefyre_insert_activity($data);
        }
        $last_message=$json->message_type;
    }

    livefyre_schedule_sync();
}

function livefyre_schedule_sync($immediate=false) {
    global $wpdb, $livefyre_debug_mode;
    $hook="livefyre_sync";
    $opt='livefyre_active_sync';
    wp_clear_scheduled_hook($hook);
    if ($immediate) {
        update_option($opt,'1');
    } else {
        delete_option($opt);
    }
    if (get_option($opt,'')=='1') {
        //we should schedule the next sync "immediately" (in 15s)
        //this flag should be reset when no more data to sync
        livefyre_debug(time()." scheduling early hook");
        wp_schedule_single_event(time()+15, $hook);
    } else {
        //schedule the next sync in typical fashion for 7hrs from now
        livefyre_debug(time()." scheduling late hook");
        wp_schedule_single_event(time()+25200, $hook);
    }
}

if (lf_wp_is_sync_status_req()) {
    $status=(get_option('livefyre_active_sync','')=='1' ? 'on' : 'off');
    spawn_cron();
    echo $_GET['callback']."({'status':'$status'});";
    exit;
}

function livefyre_get_wp_comment_id($lf_id) {
    global $wpdb, $livefyre_activity_table;
    return $wpdb->get_var($wpdb->prepare("SELECT wp_comment_id FROM $livefyre_activity_table where lf_comment_id=%d limit 1;", $lf_id));
}

function livefyre_get_activity_id() {
    return get_option('livefyre_activity_id', '0');
}

function livefyre_set_activity_id($activity_id) {
    update_option('livefyre_activity_id', $activity_id);
}

function livefyre_check_lastid_umapped() {
    global $wpdb, $livefyre_activity_table, $livefyre_debug_mode;
    $cid=$wpdb->get_var($wpdb->prepare("SELECT comment_ID FROM $wpdb->comments ORDER BY comment_date DESC LIMIT 1"));
    if ($cid!=null) {

        $lf_wp_id=$wpdb->get_var($wpdb->prepare("SELECT wp_comment_id FROM $livefyre_activity_table WHERE lf_activity_id=%d", get_option('livefyre_activity_id', null)));
        $result=($lf_wp_id!=$cid);
    } else {
        $result=0;
    }
    return $result;
}

add_action( 'admin_menu', 'livefyre_plugin_menu' );

if ( !livefyre_comments_off() ) {
    add_filter( 'comments_template', 'livefyre_comments' );
    add_filter( 'comments_number', 'livefyre_comments_number', 10, 2 );
}

function livefyre_reset_caches() {
    global $cache_path, $file_prefix;
    if ( function_exists( 'prune_super_cache' ) ) {
        prune_super_cache( $cache_path, true );
    }
    if ( function_exists( 'wp_cache_clean_cache' ) ) {
        wp_cache_clean_cache( $file_prefix );
    }
}

add_filter ( 'save_post' , 'livefyre_save_post' , 99 );

function livefyre_save_post($post_id) {
    global $livefyre_quill_url;
    
    $page = get_page($post_id);
    if($parent_id = wp_is_post_revision($post_id)) {
        $post_id = $parent_id;
        $parent_page = get_page($parent_id);
    }
    if ((isset($parent_page) && $parent_page->post_status=='publish') || $page->post_status=='publish') {
        $url = "$livefyre_quill_url/api/v1.1/private/management/site/".get_option('livefyre_blogname').'/conv/initialize/';
        $sig_created = time();
        $postdata = array(
            'article_identifier' => $post_id,
            'source_url' => get_permalink($post_id),
            'article_title' => $page->post_title,
            'sig_created' => $sig_created,
            'sig' => getHmacsha1Signature(base64_decode(get_option('livefyre_secret')), "sig_created=$sig_created")
        );
        livefyre_request($url, false, $postdata);
    }
}

function livefyre_check_add_activity_map() {
    global $wpdb, $livefyre_activity_table, $livefyre_db_version, $livefyre_debug_mode;
    if($wpdb->get_var("SHOW TABLES LIKE '$livefyre_activity_table'") !== $livefyre_activity_table) {
        $sql = "CREATE TABLE " . $livefyre_activity_table . " (
              lf_activity_id bigint(11) NOT NULL,
              lf_comment_id bigint(11) NOT NULL,
              wp_comment_id bigint(11) NOT NULL,
              UNIQUE KEY id (lf_activity_id)
            );";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option("livefyre_db_version", $livefyre_db_version);
        
        if ($livefyre_debug_mode) {
            $sql = "CREATE TABLE livefyre_debug_blobs (
              `id` int(11) NOT NULL auto_increment,
              `text` longtext,
              PRIMARY KEY  (`id`)
            );";
        }
        dbDelta($sql);
    }
}

// Style reference removed - it is included via the page template that livefyre serves up.
//wp_register_style( 'livefyre', $livefyre_http_url.'/wjs/v1.0/css/livefyre_embed.css' );

if (!livefyre_comments_off()) {
    //wp_enqueue_style( 'livefyre' );
    add_action('wp_head', 'lf_embed_head_script');
}

add_action( 'admin_notices', 'lf_import_in_progress' );
add_action( 'admin_notices', 'lf_install_warning' );
add_action( 'admin_notices', 'lf_missing_comments_warning' );

function lf_import_in_progress() {
    if ( get_option('livefyre_import_status','')=='started' ) {
        echo "<div id='livefyre-import-warning' class='updated fade'><p><strong>" . __( 'Livefyre is ready to use, but your old comments are still being imported and will become visible as they are processed.' ) . '</strong></p></div>';
    }
}

function lf_install_warning() {
    global $livefyre_http_url, $livefyre_site_domain, $livefyre_plugin_version;
    if (function_exists( 'home_url' )) {
        $home_url=home_url();
    } else {
        $home_url=get_option('home');
    }
    if ( get_option('livefyre_blogname', '') == '' && !livefyre_returned_from_setup()) {
        echo "<div id='livefyre-warning' class='updated fade'><p><strong>" . __( 'Livefyre is almost ready.' ) . '</strong> ' . 'You must <a href="'.$livefyre_http_url.'/installation/logout?site_url='.urlencode($home_url).'&domain='.$livefyre_site_domain.'&version='.$livefyre_plugin_version.'&type=wordpress&postback_hook='.urlencode($home_url.'/?lf_wp_comment_postback_request=1').'&transport=http">confirm your blog configuration with livefyre.com</a> for it to work.'  . '</p></div>';
    }
}

function lf_missing_comments_warning() {
    if (get_option('livefyre_missing_comments','')=='1' && get_option('livefyre_import_status','')!='started' && !(isset($_GET['new_import']) && $_GET['new_import']=='1')) {
        //delete_option('livefyre_missing_comments'); ?? leave this??
        if (function_exists( 'home_url' )) {
            $home_url=home_url();
        } else {
            $home_url=get_option('home');
        }
        echo "<div id='livefyre-warning' class='updated fade'><p><strong>" . __( 'You have comments that have not been synchronized with Livefyre, which means they won\'t appear in the Livefyre interface.' ) . '</strong> ' . '<a href="'.$home_url.'/wp-admin/edit-comments.php?page=livefyre&new_import=1'.'">Click here to fix this.</a>'  . '</p></div>';
    }
}

function livefyre_deactivate() {
    #livefyre_plugin_status('deactivated');
    livefyre_reset_caches();
}

function livefyre_activate() {
    global $wpdb, $livefyre_activity_table, $livefyre_debug_mode;
    #livefyre_plugin_status('activated');
    livefyre_reset_caches();
    if (get_option('livefyre_ctype_migrated','')!='done') {
        livefyre_comment_type_migration();
        update_option('livefyre_ctype_migrated','done');
    }
    if (get_option('livefyre_activitymap_added','')!='done') {
        livefyre_check_add_activity_map();
        update_option('livefyre_activitymap_added','done');
    }
    if (get_option('livefyre_wppostmeta_cleanup','')!='done') {
        livefyre_check_wppostmeta_cleanup();
        update_option('livefyre_wppostmeta_cleanup','done');
    }
    $blogname=get_option('livefyre_blogname',null);
    $blogexists=($blogname!=null && $blogname!='');
    if (get_option('livefyre_import_method',null)!=null) {
        #this is an existing plugin. we're upgrading.  clear/update settings
        delete_option('livefyre_importdb');
        delete_option('livefyre_import_method');
    }
    if (get_option('livefyre_import_status','')=='started') {
        update_option('livefyre_import_status','csv_uploaded');
    }
    
    $max_activity = $wpdb->get_var( 
        $wpdb->prepare( "SELECT lf_activity_id FROM $livefyre_activity_table ORDER BY lf_activity_id DESC LIMIT 1;" ) 
    );

    if ($max_activity > intval(livefyre_get_activity_id())) {
        livefyre_set_activity_id($max_activity);
    };
    
    if ($blogexists) {
        #perform a sync now, which ensures that we have all postback data for livefyre comments
        livefyre_do_sync();
    }
}

function livefyre_check_wppostmeta_cleanup() {
    //we no longer use this synchronization scheme, and it can mess up the admin interface.  cleanup.
    global $wpdb;
    $wpdb->query( "delete from $wpdb->postmeta where substring(meta_key,1,14)='lf_commentseq_'" );
}

function livefyre_comment_type_migration() {
    //we are cleaning up after ourselves.  typing these differently than standard wp comments is not correct
    global $wpdb;
    $wpdb->update( $wpdb->comments, array( 'comment_type' => ''), array( 'comment_type' => 'livefyre' ));
}

register_activation_hook( __FILE__, 'livefyre_activate' );
register_deactivation_hook( __FILE__, 'livefyre_deactivate' );


?>
