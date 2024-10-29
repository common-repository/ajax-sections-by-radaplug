<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */
?>

<?php 

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $Radaplug_Ajax_Sections_Instance;

if ( $Radaplug_Ajax_Sections_Instance ) {
	$allowed_html = [
		'div' => [
			'id' => [],
			'class' => [],
			'style' => [],
			'post' => [],
			'type' => [],
			'delay' => [],
			'spinner' => [],
			'button' => [],
			'_page_builder' => [],
			'_load_via_ajax' => [],
		],
	];
	echo wp_kses( $Radaplug_Ajax_Sections_Instance->radaplug_ajax_section_sc($attributes), $allowed_html );
}

?>
