<?php

/**
 * @package WW Reviews Plugin
 *
 */

if( !defined( 'ABSPATH' )) { die; }

$wwRevCPT = get_posts( array( 'post_type' => 'review_cpt', 'numberposts' => -1 ));

foreach ($wwRevCPT as $wwRevCPTPost) {
	wp_delete_post( $wwRevCPTPost->ID, true );
}
