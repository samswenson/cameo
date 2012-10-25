<!DOCTYPE>
<html>
	<head>
	

		<title><?php wp_title('&laquo;', true, 'right'); ?> <?php /* bloginfo('name'); */ ?></title>
		<meta name="title" content="<?php wp_title('-', true, 'right'); ?> <?php bloginfo('name'); ?>"/>
        <meta name="google-site-verification" content="Qn7x2BkaX8PbWp-sJMTxOSt9MA6-liVut90udpjLrLM" />
		<link rel="alternate" type="application/rss+xml" title="<?php bloginfo('name'); ?> RSS Feed" href="<?php bloginfo('rss2_url'); ?>" />
		<link rel="alternate" type="application/atom+xml" title="<?php bloginfo('name'); ?> Atom Feed" href="<?php bloginfo('atom_url'); ?>" />
		<link rel="shortcut icon" href="<?php bloginfo('template_url'); ?>/images/favicon.ico" />
		<link rel="shortcut icon" href="<?php bloginfo('template_url'); ?>/images/favicon.png" />  		
		<link rel="stylesheet" type="text/css" href="<?php bloginfo('template_url'); ?>/style.css">

		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
		<script src="<?php bloginfo('template_url'); ?>/scripts/application-ck.js"></script>
	<?php wp_head(); ?>

<!-- Google Analytics - ADD CODE BETWEEN '' MARKS-->    
    <script type="text/javascript">

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', '']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

</script>
<!-- Google Analytics End --> 
    
	</head>
	<body id="<?php echo $page_type; ?>">
			<div class="center-wrap">		
		<header id="main-header">
				<h1><a href="<?php bloginfo('url'); ?>">Cameo Homes</a></h1>
				<nav>
					<?php wp_nav_menu(); ?>
				</nav>
			</div>
		</header>