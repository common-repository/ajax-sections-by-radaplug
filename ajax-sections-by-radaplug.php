<?php
/*
 * Plugin Name: Ajax Sections by Radaplug
 * Plugin URI: https://radaplug.com/ajax-sections-by-radaplug
 * Description: Ajax Sections by Radaplug lets you load via ajax Blog Post contents, and Patterns created in Gutenberg, so that initial load time of the page will decrease signifacantly. Pro version also supports Sections designed in Elementor, and contents from custom posts.
 * Author: Radaplug
 * Text Domain: ajax-sections-by-radaplug
 * Domain Path: /languages
 * License: GPLv2 or Later
 * Requires at least: 6.0
 * Version: 1.2
 * Requires PHP: 7.4
 */

/*
Copyright 2024 Radaplug (email: radaplug.soft@gmail.com)
*/

namespace Radaplug_Ajax_Sections_Name_Space;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!class_exists('Radaplug_Ajax_Sections')) {

	class Radaplug_Ajax_Sections
	{

		private $radaplug_sc_times = true;

		function __construct()
		{

			register_activation_hook(__FILE__, array(&$this, 'install'));

			add_action('wp_enqueue_scripts', array(&$this, 'wp_enqueue_scripts'));
			add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
			add_action('init', array(&$this, 'plugin_init'));

			add_action('wp_ajax_radaplug_ajax_section', array(&$this, 'ajax_radaplug_ajax_section'));
			add_action('wp_ajax_nopriv_radaplug_ajax_section', array(&$this, 'ajax_radaplug_ajax_section'));

		}

		function plugin_init()
		{

			$radaplug_allowed_posts = array();
			$radaplug_allowed_posts[] = 'post';
			$radaplug_allowed_posts[] = 'wp_block';

			$current_allowed_posts = get_option('current_allowed_posts');

			if (is_array($current_allowed_posts)) {
				$array_diff = array_diff($radaplug_allowed_posts, $current_allowed_posts);
				if (!empty($array_diff)) {
					$current_allowed_posts = array_unique(array_merge($radaplug_allowed_posts, $current_allowed_posts), SORT_REGULAR);
					update_option('current_allowed_posts', $current_allowed_posts);
				}
			} else if (!$current_allowed_posts) {
				update_option('current_allowed_posts', $radaplug_allowed_posts);
			}

		}

		function wp_enqueue_scripts()
		{

			$asset_file = include( plugin_dir_path( __FILE__ ) . 'inc/radaplug-gutenberg-ajax-blocks/radaplug-gutenberg-ajax-package/build/index.asset.php');
			$asset_file['dependencies'][] = 'jquery';
			wp_enqueue_style('radaplug-ajax-sections', plugins_url('css/ajax-sections-by-radaplug.css', __FILE__), array(), wp_rand(1, 1000) / 100);
			wp_enqueue_script('radaplug-ajax-sections', plugins_url('js/ajax-sections-by-radaplug.js', __FILE__), $asset_file['dependencies'], wp_rand(1, 1000) / 100, false);
			wp_localize_script(
				'radaplug-ajax-sections',
				'radaplug_ajax_var',
				array(
					'ajaxurl' => admin_url('admin-ajax.php'),
					'nonce' => wp_create_nonce('ajax-nonce'),
				)
			);

		}

		function admin_enqueue_scripts()
		{

			// $wp_screen = new WP_Screen();
			// if (!$wp_screen->is_block_editor()) {
			if (true) {
				$this->wp_enqueue_scripts();
			}

		}

		function install()
		{

			if (!function_exists('is_plugin_active')) {
				include_once (ABSPATH . 'wp-admin/includes/plugin.php');
			}

			if (is_plugin_active('ajax-sections-pro-by-radaplug/ajax-sections-pro-by-radaplug.php')) {
				deactivate_plugins(plugin_basename(__FILE__));
				wp_die('<b>Could not activate plugin because Radaplug Ajax Sections Pro is already activated</b>, <a href=javascript:history.back();>Go back to Plugins Page</a>');
			}

		}

		function radaplug_ajax_section_sc($atts = [])
		{

			$radaplug_ajax_section  = '<div id=radaplug-message-div></div>';
			$radaplug_ajax_section .= '<div class=radaplug-ajax-section';

			if (isset($atts['post'])) {

				$post = sanitize_text_field($atts['post']);
				$post_type = sanitize_text_field($atts['post_type']);

				$wp_post = new \stdClass();

				$post_id = (int) $post;
				if (is_numeric($post_id) and $post_id !== 0) {
					$post = $post_id;
					$wp_post = get_post($post);
				} else if (is_string($post) AND $post != '') {
					$wp_query = get_posts(
						array(
							'name' => $post,
							'post_type' => $post_type,
							'numberposts' => -1,
							'post_status' => 'publish',
						)
					);
					if (count($wp_query) == 1) {
						$wp_post = $wp_query[0];
						$post = $wp_post->ID;
					} else {
						$post = 0;
					}
				}

				$wp_post = $this->radaplug_before_sc_post($wp_post);

				if (!empty($wp_post) and $wp_post->post_status == 'publish') {
					$radaplug_ajax_section .= ' post="' . $post . '"';
					$radaplug_ajax_section .= ' type="' . $post_type . '"';
				} else {
					return '';
				}

			} else {

				return '';

			}

			if (isset($atts['delay'])) {
				$delay = (int) sanitize_text_field($atts['delay']);
				$radaplug_ajax_section .= ' delay="' . $delay . '"';
			}

			if (isset($atts['button']) and $atts['button'] != '') {
				$button = sanitize_text_field($atts['button']);
				$button = str_replace(' ', '&#32;', $button);
				$radaplug_ajax_section .= ' button="' . $button . '"';
			}

			if (isset($atts['radaplug_load_via_ajax'])) {
				$atts['radaplug_load_via_ajax'] = sanitize_text_field($atts['radaplug_load_via_ajax']);
				if ($atts['radaplug_load_via_ajax'] == 1 OR $atts['radaplug_load_via_ajax'] == 'yes') {
					$atts['_load_via_ajax'] = 'yes';
				} else {
					$atts['_load_via_ajax'] = 'no';
				}
			} else {
				$atts['_load_via_ajax'] = 'no';
			}
			$radaplug_ajax_section .= ' _load_via_ajax=' . $atts['_load_via_ajax'];

			$_page_builder = '';
			if (isset($atts['_page_builder'])) {
				$_page_builder = sanitize_text_field($atts['_page_builder']);
				$radaplug_ajax_section .= ' _page_builder=' . $_page_builder;
			}

			if ($atts['_load_via_ajax'] == 'no') {
				$content = do_shortcode('[radaplug-insert page=' . $post . ' display=content]');
			} else {
				$content = '&nbsp;';
			}

			if (isset($atts['spinner'])) {
				$atts['spinner'] = sanitize_text_field($atts['spinner']);
				if ($atts['spinner'] == 1 OR $atts['spinner'] == 'yes') {
					$radaplug_ajax_section .= ' spinner="yes"';
				} else {
					$radaplug_ajax_section .= ' spinner="no"';
				}
			} else {
				$radaplug_ajax_section .= ' spinner="yes"';
			}

			$radaplug_ajax_section .= '>' . $content . '</div>';

			if ($this->radaplug_sc_times) {
				$this->radaplug_sc_times = false;
			} else {
				return '';
			}
	
			return $radaplug_ajax_section;

		}

		function ajax_radaplug_ajax_section()
		{

			if ( !isset( $_POST['nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['nonce'] ) ) , 'ajax-nonce' ) ) {
				$verify_nonce = false;
			} else {
				$verify_nonce = true;
			}

			$post = sanitize_text_field($_POST['post']);
			$dup = (int) sanitize_text_field($_POST['dup']);
			$post_type = sanitize_text_field($_POST['post_type']);

			$content = '';
			$response = array();

			if (isset($post)) {

				$wp_post = new \stdClass();

				$post_id = (int) $post;
				if (is_numeric($post_id) and $post_id !== 0) {
					$post = $post_id;
					$wp_post = get_post($post);
				} else if (is_string($post)) {
					$post = esc_html($post);
					$wp_query = get_posts(
						array(
							'name' => $post,
							'post_type' => $post_type,
							'numberposts' => -1,
							'post_status' => 'publish',
						)
					);
					if (count($wp_query) == 1) {
						$wp_post = $wp_query[0];
						$post_id = $wp_post->ID;
					} else {
						$post_id = 0;
					}

				}

				if ($dup == 0) {
					$response['post_id'] = $post_id;
					wp_send_json_success( wp_json_encode($response) );
					die();
				}

				$wp_post = $this->radaplug_before_ajax_post($wp_post);

				if (!$verify_nonce) {

					if (isset($post) and !empty($wp_post)) {
						$response['post'] = $post;
						$response['post_id'] = $post_id;
						$response['type'] = $wp_post->post_type;
						$response['content'] = 'Session expired, Please refresh the page and try again';
					}

					wp_send_json_success( wp_json_encode($response) );
					die();

				}

				if (!empty($wp_post) and $wp_post->post_status == 'publish') {
					$content = do_shortcode('[radaplug-insert page=' . $post . ' display=content]');
				}

				$response['post'] = $post;
				$response['post_id'] = $post_id;
				$response['dup'] = $dup;
				$response['type'] = $wp_post->post_type;
				$response['content'] = $content;

			}

			wp_send_json_success( wp_json_encode($response) );
			die();

		}

		function radaplug_before_sc_post($wp_post)
		{

			if (!empty($wp_post) and $wp_post->post_status == 'publish' and $wp_post->ID != get_the_ID()) {
				return $this->radaplug_before_ajax_post($wp_post);
			} else {
				return false;
			}

		}

		function radaplug_before_ajax_post($wp_post)
		{

			$current_allowed_posts = get_option('current_allowed_posts');

			if (in_array($wp_post->post_type, $current_allowed_posts)) {
				return $wp_post;
			} else {
				return false;
			}

		}

	}

	if ( class_exists( 'Radaplug_Ajax_Sections_Name_Space\Radaplug_Ajax_Sections' ) ) {
		$Radaplug_Ajax_Sections_Instance = new Radaplug_Ajax_Sections();
		require_once (plugin_dir_path(__FILE__) . 'inc/radaplug-insert-pages/radaplug-insert-pages.php');
		require_once (plugin_dir_path(__FILE__) . 'inc/radaplug-gutenberg-ajax-blocks/radaplug-gutenberg-ajax-blocks.php');
	}
	
}