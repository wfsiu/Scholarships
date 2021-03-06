<?php
if ( ( isset($_GET['uselang']) ) or ( isset( $values['uselang']) ) ) {
	$res = array_merge( $_GET, $_POST );
	$lang = $wgLang->setLang($res);
}
?><!DOCTYPE html>
<!--[if lt IE 7 ]><html class="ie ie6 no-js" xml:lang="<?= $lang ?>" lang="<?= $lang ?>"><![endif]-->
<!--[if IE 7 ]><html class="ie ie7 no-js" xml:lang="<?= $lang ?>" lang="<?= $lang ?>"><![endif]-->
<!--[if IE 8 ]><html class="ie ie8 no-js" xml:lang="<?= $lang ?>" lang="<?= $lang ?>"><![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--><html class="no-js" xml:lang="<?= $lang ?>" lang="<?= $lang ?>"><!--<![endif]-->
<head>
	<meta http-equiv="Content-language" content="<?= $lang ?>"/>
	<meta charset="utf-8"/>
	<title><?php echo $wgLang->message('header-title');?></title>
	<link rel="stylesheet" type="text/css" href="<?= $TEMPLATEBASE ?>css/base.css"/>
	<link rel="stylesheet" type="text/css" href="<?= $TEMPLATEBASE ?>css/skeleton.css"/>
	<link rel="stylesheet" type="text/css" href="<?= $TEMPLATEBASE ?>css/style.css"/>
	<link rel="shortcut icon" type="image/png" href="favicon.png"/>
        <!--[if lt IE 9]>
        <script type="text/javascript" src="<?= $TEMPLATEBASE ?>js/html5.js"></script>
        <![endif]-->
</head>
<body>
<div class="container">
<div id="langbar" class="fifteen columns">
<ul class="langlist">
<!--<li><a href="<?= $basepath ?>?uselang=de">de</a></li>-->
<li><a href="<?= $basepath ?>?uselang=en">en</a></li>
<li><a href="<?= $basepath ?>?uselang=zh">zh</a></li>
<!--<li><a href="<?= $basepath ?>?uselang=pl">pl</a></li>-->
<li class="last"><a href="<?= $BASEURL ?>translate"><?= $wgLang->message('help-translate') ?></a></li>
</ul>
</div>
<h1><a id="banner" href="<?= $BASEURL ?>" title="Wikimania 2013"><img src="<?php echo $TEMPLATEBASE;?>images/Wikimania-2013-bannerm.png" alt="Wikimania 2013"/></a></h1>
