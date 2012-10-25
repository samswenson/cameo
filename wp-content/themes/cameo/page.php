<?php $page_type = "about"; ?>
<?php include("header.php");?>
<section id="main-content">
	<div class="center-wrap">
  	  <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
		<article>
		  <?php if(get('page_image')){?>
			<figure>
				<ul class="page-gallery">
					<?php
					    $total = getFieldDuplicates('page_image',1);
					    for($i = 1; $i < $total+1; $i++){ echo "<li>" .get_image('page_image',1,$i). "</li>";
					}?>
				</ul>
				<ul class="page-gallery-toggler"></ul>
			</figure>
		  <?php };?>
		  <h1><?php the_title();?></h1>
		  <?php the_content();?>
		</article>
		<aside>
		  <?php team_sidebar_module();?>
	      <?php $total = getGroupDuplicates('sidebar_module_title');?>
	          <?php for($a = 1; $a < $total+1; $a++):
		        echo "<section>";
	    	        echo "<h2>".get('sidebar_module_title',$a,1)."</h2>";  
	    	          
			        if(get("sidebar_module_list",$a,1)){
			        	$totalList = getFieldDuplicates('sidebar_module_list',$a,1);
			        	echo "<ul class='check'>";
				        for($b = 1; $b < $totalList+1; $b++){ 
	    	    		  echo "<li>".get('sidebar_module_list',$a,$b)."</li>";
			        	};
	        			echo "</ul>";
	        		};
	        		
		        	if(get("sidebar_module_gallery",$a,1)){
	    	    		echo "<ul class='sidebar-gallery'>";
	         	     	$totalImages = getFieldDuplicates('sidebar_module_gallery',$a,1);
			        	for($c = 1; $c < $totalImages+1; $c++){ 
			    	      $new_phpthumb = array ("w" => 60, "h" => 55);
				          if($c % 3 === 0){ $class="class='last'";} else { $class = "";}
			    	      echo "<li ".$class."><a class='fancybox' rel='gallery-".$a."' href='".get('sidebar_module_gallery',$a,$c)."'>";
			    	      echo gen_image('sidebar_module_gallery',$a,$c,$new_phpthumb);
			    	      echo "</a></li>";
	        			};
			        	echo "</ul>";
	    		    };
	    		    
	        		if(get("sidebar_module_text_box",$a,1)){ echo get("sidebar_module_text_box",$a,1);}
	          	echo "</section>";
            endfor;?>             
		</aside>
		<?php endwhile; else: ?>
			<p><?php _e('Sorry, no posts matched your criteria.'); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php get_footer(); ?>