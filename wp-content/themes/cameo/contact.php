<?php /* Template Name: Contact */ ?>
<?php $page_type = "contact"; ?>
<?php include("header.php");?>
<section id="main-content">
	<div class="center-wrap">
		<article>
			<figure>
				<ul class="page-gallery">
					<li><img src="<?php bloginfo('template_url'); ?>/images/contact_01.jpg" /></li>
					<li></li>
				</ul>
				<ul class="page-gallery-toggler">
					<li><a class="current" href="#">slide 1</a></li>
					<li><a href="#">slide 2</a></li>
					<li><a href="#">slide 3</a></li>
				</ul>
			</figure>
			<h1>Contact Us</h1>
			<!--p>Why Choose Us? Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis vel arcu at est porta rutrum. Fusce nibh nisi, rhoncus luctus tincidunt sit amet, varius eu ante. Nam nisl dolor. </p-->
			<form>
				<input class="name" type="text" />
				<input class="email" type="text" />
				<textarea></textarea>
				<button type="submit"><span>Send Message</span></button>
			</form>
			
		</article>
		<aside>
			<section>
				<h1>Contact Info</h1>
					<ul>
						<li><a href="https://maps.google.com/maps?q=Tobias+Dental+Care,+Marriott-Slaterville,+UT&hl=en&sll=37.0625,-95.677068&sspn=60.116586,114.169922&oq=Tobias+Dental+Care,+Marr&hq=Tobias+Dental+Care,&hnear=Marriott-Slaterville,+Weber,+Utah&t=m&z=15" target="blank_"><span>Marriott-Slaterville Office</span></a><address>1920 West 250 North, Suite 3<br/>Marriott-Slaterville, UT 84404<br/>phone: <a><span>801.731.4848</span></a></address>
						</li>
                        <li><a href="https://maps.google.com/maps?q=5300+Adams+Ave+Parkway+%2315,+Ogden,+UT&hl=en&ll=41.167639,-111.968579&spn=0.028494,0.055747&sll=41.167593,-111.968229&hnear=5300+Adams+Ave+Pkwy,+Ogden,+Utah+84405&t=m&z=15" target="blank_"><<span>South Ogden Office</span></a><address>5300 South Adams Ave Pkwy Ste 15<br/>South Ogden, Utah 84405<br/>phone: <a><span>801.479.7600</span></a></address>
						</li>
						<li>e-mail: <a href="mailto:info@tobiasdental.com" target="_blank"><span>info@tobiasdental.com</span></a>
						</li>                        
						<li>
							<a href="http://www.facebook/tobiasdental" target="_blank">facebook/<span>TobiasDental</span></a><br/>
							<a href="http://www.twitter/tobiasdental" target="_blank">twitter/<span>TobiasDental</span></a>
						</li>
					</ul>
		</aside>
	</div>
</section>
<?php get_footer(); ?>