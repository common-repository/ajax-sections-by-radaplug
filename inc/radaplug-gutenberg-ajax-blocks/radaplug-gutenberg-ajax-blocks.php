<?php

namespace Radaplug_Ajax_Sections_Name_Space\Radaplug_Gutenberg_Ajax_Blocks_Name_Space;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!class_exists('Radaplug_Gutenberg_Ajax_Blocks')) {

	class Radaplug_Gutenberg_Ajax_Blocks
	{
		function __construct()
		{

			register_block_type( __DIR__ . '/radaplug-gutenberg-ajax-package/build' );

		}

	}

	if ( class_exists( 'Radaplug_Ajax_Sections_Name_Space\Radaplug_Gutenberg_Ajax_Blocks_Name_Space\Radaplug_Gutenberg_Ajax_Blocks' ) ) {

		$Radaplug_Gutenberg_Ajax_Blocks_Instance = new Radaplug_Gutenberg_Ajax_Blocks();

	}

}