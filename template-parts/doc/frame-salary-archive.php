<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
global $wp_query;

if ( $wp_query->have_posts() ) {
	while ( have_posts() ) :
		the_post();
	?>
	<?php require( 'frame-salary.php' ); ?>

<?php endwhile; ?>
<?php } ?>

<div class="bill-no-print">
<div class="container">
<p>このエリアは印刷されません。</p>
<div class="row">
<?php get_template_part( 'template-parts/breadcrumb' ); ?>
</div>
</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
