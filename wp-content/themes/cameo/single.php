<?php $page_type = "blog"; ?>
<?php include("header.php");?>
<section id="main-content">
	<div class="center-wrap">
		<section class="main-column">
			<?php
				if ( have_posts() ) : while ( have_posts() ) : the_post();
 	   			echo "
 	   					<article class='single'>
							<h1><a href='".get_permalink()."'>".get_the_title()."</a></h1>
							<a href='".get_permalink()."'>";
				if ( p75HasThumbnail($post->ID)){
			    	echo "<img src='".p75GetThumbnail($post->ID)."' alt='post thumbnail' />";
			    }
				echo "</a>
							<div class='details'>";
				the_date('j M', '<h2>', '</h2>');
				echo "<p>Posted by <a>";
				the_author();
				echo "</a></p>
								<ul>
									<li>Categories ";
				the_category(',');				
			    echo "</li>
									<li>Tags ";
				the_tags();
				echo "</li>
									<li>Comments: <a href='#'>";
				comments_number( '0', '1', '%' );
				echo "</a></li>
								</ul>
								<!--<a class='share' href='#'>Share</a>-->
							</div>
							";
				the_content();
				echo "</article>
 	   			";
			endwhile; else:
				_e('Sorry, no posts matched your criteria.');
			endif;
 	   	?>			
        <div class="responses">
	      <?php comments_template(); ?>
       	</div>       	   	
 	   </section>
		<aside>
			<?php if ( !function_exists('dynamic_sidebar') || !dynamic_sidebar('sidebar') ) : ?> <?php endif; ?>
		</aside>
	</div>
</section>
<?php get_footer(); ?>