<?php

/*
  Template Name: WP Search
 */
?>

<?php get_header(); ?>


<?php

global $wpsearch;
$params['query'] = get_search_query();
$params['page'] = $_GET['wpage']; //get_query_var( 'page' );
$params['queryname'] = "s";

$wpsearch->doSearch($params);
?>  


<?php get_footer(); ?>
