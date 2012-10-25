<?php
  add_theme_support( 'post-thumbnails' );
  register_nav_menus( array('primary' => 'Primary Navigation') );
  register_sidebar(array('name'=>'Listings Widget',));
  register_sidebar(array('name'=>'sidebar',
    'before_widget' => '<section id="%1$s" class="%2$s">',
    'after_widget' => '</section>',
    'before_title' => '<h2 class="widgettitle">',
    'after_title' => '</h2>',
  ));

  function team_sidebar_module(){
    if(get('team_sidebar_module_name')){
      echo "<section><h2>Our Team</h2><ul class='check'>";
      $teamtotal = getGroupDuplicates('team_sidebar_module_name');
      for($d = 1; $d < $teamtotal +1; $d++):
        echo "
          <li>
            <a class='fancybox' rel='gallery-team' title='".get('team_sidebar_module_name',$d,1)." - ".get('team_sidebar_module_title',$d,1)." ".get('team_sidebar_module_bio',$d,1)."' href='".get('team_sidebar_module_image',$d,1)."'>".get('team_sidebar_module_name',$d,1)." - ".get('team_sidebar_module_title',$d,1)."
            </a>
          </li>
        ";
        endfor;
      echo "</li></section>";    
    };
  }

  function custom_body_class(){
  	if(is_home()){
  		$class = "home";	
  	} elseif(!(is_page())){
		$class = "blog";  	
	} else {
  		$class = "page";	
  	}
  	echo "class='".$page."'";
  }

function custom_comment($comment, $args, $depth) {
    $GLOBALS['comment'] = $comment; ?>
	<li <?php comment_class(); ?> id="li-comment-<?php comment_ID( ); ?>">
	  <div id="comment-<?php comment_ID( ); ?>" class="thumb">
	    
	    <?php /* echo comment_reply_link(array('before' => '<div class="reply">', 'after' => '</div>', 'reply_text' => 'Reply to this comment', 'depth' => $depth, 'max_depth' => $args['max_depth'] ));*/  ?>
	    <?php if ($args['avatar_size'] != 0) echo get_avatar( $comment, $args['avatar_size'] ); ?>
	  </div>
      
	  <div class="rsponstxt">
		<h6><?php comment_author_link() ?></h6>
		<p class="date"><?php comment_date('l, F jS Y') ?> AT <?php comment_time() ?></p>
		<div class="clear"></div>
		<?php if ($comment->comment_approved == '0') : ?>
			<em>Your comment is awaiting moderation.</em>
		<?php endif; ?>
		<?php comment_text() ?>
	  </div>
<?php } ?>