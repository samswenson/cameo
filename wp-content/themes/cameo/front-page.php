<?php /* Template Name: Home */ ?>
<?php $page_type = "home"; ?>
<?php include("header.php");?>
<section class="main-feature">
	<div class="center-wrap">
		<hgroup>
			<h1><?php echo get('slider_title_text_h1');?></h1>
			<p><?php echo get('slider_title_text_p');?></p>
			<h2><?php echo get('slider_title_text_h2');?></h2>
			<h3><?php echo get('slider_title_text_h3');?></h3>
		</hgroup>
		<ul class="page-gallery">
			<?php 
				$sliderImages = getFieldDuplicates('slider_image',1);
			    for($a = 1; $a < $sliderImages+1; $a++){ 
			    	echo "<li><img src='".get('slider_image',1,$a)."' /></li>";
			    };
			 ?>
			</li>
		</ul>
		<ul class="page-gallery-toggler"></ul>
	</div>
</section>		
<section id="main-content">
	<div class="center-wrap">
		<section class="free-download">
			<h2><?php echo get('free_download_title');?></h2>
			<img src="<?php echo get('free_download_image');?>"/>
			<a class="free-download-link" target="_blank" href="#">Free Download</a>			
		</section>
		<section class="fb">
			<h2><?php echo get('social_title');?></h2>
			<img src="<?php echo get('social_social_image');?>"/>
			<a class="fb-link" target="_blank" href="<?php echo get('social_facebook_link'); ?>">Like us on Facebook</a>
		</section>
		<section class="latest-news">
			<h2><?php echo get('latest_news_title');?></h2>
			<div id="latest-news-wrapper">
			<!-- Get Latest Post Image -->
			<?php
				$the_query = new WP_Query( 'posts_per_page=1');
				while ( $the_query->have_posts() ) : $the_query->the_post();
			?>
			<div id="box<?php echo $the_query->current_post+1; ?>">
				<?php
					if ( has_post_thumbnail($post->ID) ) { // check if the post has a Post Thumbnail assigned to it.
						the_post_thumbnail($post->ID);
						}
				?>
				</div>
				<div "featured-wrapper">
				<!-- Get Latest Post Date -->
				<div id="featured-post-date"><?php echo get_the_date('M j'); ?></div>
				<!-- Get Latest Post Author -->
				<div id="featured-post-author">posted by <?php echo get_the_author(); ?></div>
				<!-- Get Latest Post Title -->
				<div id="featured-title"><?php the_title(); ?></div>
				<!-- Get Latest Post City/State (excerpt) -->
				<div id="featured-post-excerpt"></div>
				<?php endwhile; ?>
				</div>
			</div>
			<h3><?php echo get('latest_news_link');?></h3>
		</section>
	</div>
</section>
<?php get_footer(); ?>