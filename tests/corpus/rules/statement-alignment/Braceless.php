<?php

function braceless_bodies( $post, $title, $format ) {
	$string = '<a href="' . get_permalink( $post->ID ) . '">';
	if ( 'yes' == $title )
		/** This filter is documented in wp-includes/post-template.php */
		$string .= apply_filters( 'the_title', $post->post_title, $post->ID );
	// @mago-expect lint:wordpress/statement-alignment
	$string .= '</a>';
	$format = str_replace( '%', $string, $format );

	return $format;
}

function braceless_if_in_block( $item ) {
	// @mago-expect lint:wordpress/statement-alignment(2)
	$summary = $item['summary'];
	if ( isset( $item['summary'] ) )
		$item['description'] = $item['summary'];
	$item['content']['encoded'] = $summary;

	return $item;
}
