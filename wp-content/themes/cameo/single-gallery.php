<?php /* Template Name: Gallery */ ?>
<?php $page_type = "gallery"; ?>
<?php include("header.php");?>
<section id="main-content">
	<div class="center-wrap">
<strong>Actual Patient Photos</strong>
<br>	
<br>	
<ul id="gallery">
			<?php 
			  if(get('gallery_item_title')){
				  $total = getGroupDuplicates('gallery_item_title');
	          	  for($i = 1; $i < $total+1; $i++):
	          	    if($i % 4 === 0){ $class="class='last'";} else { $class = "";}
	          	    echo "<li ".$class."><a rel='gallery' class='fancybox' title='".get('gallery_item_description',$i,1)."' href='".get('gallery_item_image',$i,1)."'><img height='109' src='".get('gallery_item_image_thumbnail',$i,1)."'/><h3>".get('gallery_item_title',$i,1)."</h3><p>".get('gallery_item_description',$i,1)."</p></a>";
          		endfor;			  		
			  };
			 ?>
		</ul>
	</div>
</section>
<?php get_footer(); ?>