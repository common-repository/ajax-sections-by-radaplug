<?php

namespace Radaplug_Ajax_Sections_Name_Space\Radaplug_Insert_Pages_Name_Space;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Radaplug_Insert_Pages' ) ) {
	/**
	 * Class InsertPagesPlugin
	 */
	class Radaplug_Insert_Pages {
		/**
		 * Stack tracking inserted pages (for loop detection).
		 *
		 * @var  array Array of page ids inserted.
		 */
		protected $inserted_page_ids;

		/**
		 * Flag to only render the TinyMCE plugin dialog once.
		 *
		 * @var boolean
		 */
		private static $link_dialog_printed = false;

		/**
		 * Flag checked when rendering TinyMCE modal to ensure that required scripts
		 * and styles were enqueued (normally done in `admin_init` hook).
		 *
		 * @var boolean
		 */
		private static $is_admin_initialized = false;

		/**
		 * Singleton plugin instance.
		 *
		 * @var object Plugin instance.
		 */
		protected static $instance = null;


		/**
		 * Access this plugin's working instance.
		 *
		 * @return object Object of this class.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}


		/**
		 * Disable constructor to enforce a single plugin instance..
		 */
		protected function __construct() {
		}


		/**
		 * Action hook: WordPress 'init'.
		 *
		 * @return void
		 */
		public function insert_pages_init() {

			// Register the [radaplug-insert] shortcode.
			add_shortcode( 'radaplug-insert', array( $this, 'insert_pages_handle_shortcode_insert' ) );

        }

		/**
		 * Shortcode hook: Replace the [radaplug-insert ...] shortcode with the inserted page's content.
		 *
		 * @param  array  $atts    Shortcode attributes.
		 * @param  string $content Content to replace shortcode.
		 * @return string          Content to replace shortcode.
		 */
		public function insert_pages_handle_shortcode_insert( $atts, $content = null ) {
			global $wp_query, $post, $wp_current_filter;

			$echo = '';

			// Shortcode attributes.
			$attributes = shortcode_atts(
				array(
					'page'        => '0',
					'display'     => 'all',
					'class'       => '',
					'id'          => '',
					'querystring' => '',
					'size'        => '',
					'inline'      => false,
					'public'      => false,
				),
				$atts,
				'insert'
			);

			// Validation checks.
			if ( '0' === $attributes['page'] ) {
				return $content;
			}

			// Short circuit if trying to embed same page in itself.
			if (
				! is_null( $post ) && property_exists( $post, 'ID' ) &&
				(
					( intval( $attributes['page'] ) > 0 && intval( $attributes['page'] ) === $post->ID ) ||
					$attributes['page'] === $post->post_name
				)
			) {
				return $content;
			}

			// Get options set in WordPress dashboard (Settings > Insert Pages).
			$options = get_option( 'wpip_settings' );

			/**
			 * Filter the flag indicating whether to wrap the inserted content in inline tags (span).
			 *
			 * @param bool $use_inline_wrapper Indicates whether to wrap the content in span tags.
			 */
			$attributes['inline'] = apply_filters( 'insert_pages_use_inline_wrapper', $attributes['inline'] );
			$attributes['wrapper_tag'] = $attributes['inline'] ? 'span' : 'div';

			$attributes['public'] = ( false !== $attributes['public'] && 'false' !== $attributes['public'] ) || array_search( 'public', $atts, true ) === 0 || is_user_logged_in();

			/**
			 * Filter the querystring values applied to every inserted page. Useful
			 * for admins who want to provide the same querystring value to all
			 * inserted pages sitewide.
			 *
			 * @since 3.2.9
			 *
			 * @param string $querystring The querystring value for the inserted page.
			 */
			$attributes['querystring'] = apply_filters(
				'insert_pages_override_querystring',
				str_replace( '{', '[', str_replace( '}', ']', htmlspecialchars_decode( $attributes['querystring'] ) ) )
			);

			$attributes['should_apply_the_content_filter'] = true;
			/**
			 * Filter the flag indicating whether to apply the_content filter to post
			 * contents and excerpts that are being inserted.
			 *
			 * @param bool $apply_the_content_filter Indicates whether to apply the_content filter.
			 */
			$attributes['should_apply_the_content_filter'] = apply_filters( 'insert_pages_apply_the_content_filter', $attributes['should_apply_the_content_filter'] );

			// Disable the_content filter if using inline tags, since wpautop
			// inserts p tags and we can't have any inside inline elements.
			if ( $attributes['inline'] ) {
				$attributes['should_apply_the_content_filter'] = false;
			}

			/**
			 * Filter the chosen display method, where display can be one of:
			 * title, link, excerpt, excerpt-only, content, post-thumbnail, all, {custom-template.php}
			 * Useful for admins who want to restrict the display sitewide.
			 *
			 * @since 3.2.7
			 *
			 * @param string $display The display method for the inserted page.
			 */
			$attributes['display'] = apply_filters( 'insert_pages_override_display', $attributes['display'] );

			// // If a URL is provided, translate it to a post ID.
			// if ( filter_var( $attributes['page'], FILTER_VALIDATE_URL ) ) {
			// 	$attributes['page'] = url_to_postid( $attributes['page'] );
			// }

			// Get the WP_Post object from the provided slug, or ID.
			if ( ! is_numeric( $attributes['page'] ) ) {
				// Get list of post types that can be inserted (page, post, custom
				// types), excluding builtin types (nav_menu_item, attachment).
				$insertable_post_types = array_filter(
					get_post_types(),
					array( $this, 'is_post_type_insertable' )
				);
				$inserted_page = get_page_by_path( $attributes['page'], OBJECT, $insertable_post_types );

				// If get_page_by_path() didn't find the page, check to see if the slug
				// was provided instead of the full path (useful for hierarchical pages
				// that are nested under another page).
				if ( is_null( $inserted_page ) ) {
					global $wpdb;
					$page = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->prepare(
							"SELECT ID FROM $wpdb->posts WHERE post_name = %s AND (post_status = 'publish' OR post_status = 'private') LIMIT 1",
							$attributes['page']
						)
					);
					if ( $page ) {
						$inserted_page = get_post( $page );
					}
				}

				$attributes['page'] = $inserted_page ? $inserted_page->ID : $attributes['page'];
			} else {
				$inserted_page = get_post( intval( $attributes['page'] ) );
			}

			// Prevent unprivileged users from inserting private posts from others.
			if ( is_object( $inserted_page ) && 'publish' !== $inserted_page->post_status ) {
				$post_type = get_post_type_object( $inserted_page->post_type );
				$parent_post_author_id = intval( get_the_author_meta( 'ID' ) );
				if ( ! user_can( $parent_post_author_id, $post_type->cap->read_post, $inserted_page->ID ) ) {
					$inserted_page = null;
				}
			}

			// If inserted page's status is private, don't show to anonymous users
			// unless 'public' option is set.
			if ( is_object( $inserted_page ) && 'private' === $inserted_page->post_status && ! $attributes['public'] ) {
				$inserted_page = null;
			}

			// Integration: if Simple Membership plugin is used, check that the
			// current user has permission to see the inserted post.
			// See: https://simple-membership-plugin.com/simple-membership-miscellaneous-php-tweaks/
			if ( class_exists( 'SwpmAccessControl' ) ) {
				$access_ctrl = \SwpmAccessControl::get_instance();
				if ( ! $access_ctrl->can_i_read_post( $inserted_page ) && ! current_user_can( 'edit_files' ) ) {
					$inserted_page = null;
					$content = wp_kses_post( $access_ctrl->why() );
				}
			}

			// Loop detection: check if the page we are inserting has already been
			// inserted; if so, short circuit here.
			if ( ! is_array( $this->inserted_page_ids ) ) {
				// Initialize stack to the main page that contains inserted page(s).
				$this->inserted_page_ids = array( get_the_ID() );
			}
			if ( isset( $inserted_page->ID ) ) {
				if ( ! in_array( $inserted_page->ID, $this->inserted_page_ids ) ) {
					// Add the page being inserted to the stack.
					$this->inserted_page_ids[] = $inserted_page->ID;
				} else {
					// Loop detected, so exit without rendering this post.
					return $content;
				}
			}

			// Set any querystring params included in the shortcode.
			parse_str( $attributes['querystring'], $querystring );

			$original_wp_query_vars = $GLOBALS['wp']->query_vars;
			if (
				! empty( $querystring ) &&
				isset( $GLOBALS['wp'] ) &&
				method_exists( $GLOBALS['wp'], 'parse_request' ) &&
				empty( $GLOBALS['wp']->query_vars['rest_route'] )
			) {
				$GLOBALS['wp']->parse_request( $querystring );
			}

			// Use "Normal" insert method (get_post).
			if ( 'legacy' !== $options['wpip_insert_method'] ) {

				// If we couldn't retrieve the page, fire the filter hook showing a not-found message.
				if ( null === $inserted_page ) {
					/**
					 * Filter the html that should be displayed if an inserted page was not found.
					 *
					 * @param string $content html to be displayed. Defaults to an empty string.
					 */
					$content = apply_filters( 'insert_pages_not_found_message', $content );

					// Short-circuit since we didn't find the page.
					return $content;
				}

				// Start output buffering so we can save the output to a string.
				ob_start();

				// If Beaver Builder, SiteOrigin Page Builder, Elementor, or WPBakery
				// Page Builder (Visual Composer) are enabled, load any cached styles
				// associated with the inserted page.
				// Note: Temporarily set the global $post->ID to the inserted page ID,
				// since both builders rely on the id to load the appropriate styles.
				if (
					class_exists( 'UAGB_Post_Assets' ) ||
					class_exists( 'FLBuilder' ) ||
					class_exists( 'SiteOrigin_Panels' ) ||
					class_exists( '\Elementor\Post_CSS_File' ) ||
					defined( 'VCV_VERSION' ) ||
					defined( 'WPB_VC_VERSION' )
				) {
					// If we're not in The Loop (i.e., global $post isn't assigned),
					// temporarily populate it with the post to be inserted so we can
					// retrieve generated styles for that post. Reset $post to null
					// after we're done.
					if ( is_null( $post ) ) {
						$old_post_id = null;
						$post = $inserted_page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					} else {
						$old_post_id = $post->ID;
						$post->ID = $inserted_page->ID;
					}

					// Enqueue assets for Ultimate Addons for Gutenberg.
					// See: https://ultimategutenberg.com/docs/assets-api-third-party-plugins/.
					if ( class_exists( 'UAGB_Post_Assets' ) ) {
						$post_assets_instance = new \UAGB_Post_Assets( $inserted_page->ID );
						$post_assets_instance->enqueue_scripts();
					}

					if ( class_exists( 'FLBuilder' ) ) {
						\FLBuilder::enqueue_layout_styles_scripts( $inserted_page->ID );
					}

					if ( class_exists( 'SiteOrigin_Panels' ) ) {
						$renderer = \SiteOrigin_Panels::renderer();
						$renderer->add_inline_css( $inserted_page->ID, $renderer->generate_css( $inserted_page->ID ) );
					}

					if ( class_exists( '\Elementor\Post_CSS_File' ) ) {
						$css_file = new \Elementor\Post_CSS_File( $inserted_page->ID );
						$css_file->enqueue();
					}

					// Enqueue custom style from WPBakery Page Builder (Visual Composer).
					if ( defined( 'VCV_VERSION' ) ) {
						wp_enqueue_style( 'vcv:assets:front:style' );
						wp_enqueue_script( 'vcv:assets:runtime:script' );
						wp_enqueue_script( 'vcv:assets:front:script' );

						$bundle_url = get_post_meta( $inserted_page->ID, 'vcvSourceCssFileUrl', true );
						if ( $bundle_url ) {
							$version = get_post_meta( $inserted_page->ID, 'vcvSourceCssFileHash', true );
							if ( ! preg_match( '/^http/', $bundle_url ) ) {
								if ( ! preg_match( '/assets-bundles/', $bundle_url ) ) {
									$bundle_url = '/assets-bundles/' . $bundle_url;
								}
							}
							if ( preg_match( '/^http/', $bundle_url ) ) {
								$bundle_url = set_url_scheme( $bundle_url );
							} elseif ( defined( 'VCV_TF_ASSETS_IN_UPLOADS' ) && constant( 'VCV_TF_ASSETS_IN_UPLOADS' ) ) {
								$upload_dir = wp_upload_dir();
								$bundle_url = set_url_scheme( $upload_dir['baseurl'] . '/' . VCV_PLUGIN_ASSETS_DIRNAME . '/' . ltrim( $bundle_url, '/\\' ) );
							} elseif ( class_exists( 'VisualComposer\Helpers\AssetsEnqueue' ) ) {
								// These methods should work for Visual Composer 26.0.
								// Enqueue custom css/js stored in vcvSourceAssetsFiles postmeta.
								$vc = new \VisualComposer\Helpers\AssetsEnqueue;
								if ( method_exists( $vc, 'enqueueAssets' ) ) {
									$vc->enqueueAssets($inserted_page->ID);
								}
								// Enqueue custom CSS stored in vcvSourceCssFileUrl postmeta.
								$upload_dir = wp_upload_dir();
								$bundle_url = set_url_scheme( $upload_dir['baseurl'] . '/' . VCV_PLUGIN_ASSETS_DIRNAME . '/' . ltrim( $bundle_url, '/\\' ) );
							} else {
								$bundle_url = content_url() . '/' . VCV_PLUGIN_ASSETS_DIRNAME . '/' . ltrim( $bundle_url, '/\\' );
							}
							wp_enqueue_style(
								'vcv:assets:source:main:styles:' . sanitize_title( $bundle_url ),
								$bundle_url,
								array(),
								VCV_VERSION . '.' . $version
							);
						}
					}

					// GoodLayers page builder content (retrieved from post meta).
					// See: https://docs.goodlayers.com/add-page-builder-in-product/.
					do_action( 'gdlr_core_print_page_builder' );

					if ( is_null( $old_post_id ) ) {
						$post = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					} else {
						$post->ID = $old_post_id;
					}
				}

				/**
				 * Show either the title, link, content, everything, or everything via a
				 * custom template.
				 *
				 * Note: if the sharing_display filter exists, it means Jetpack is
				 * installed and Sharing is enabled; this plugin conflicts with Sharing,
				 * because Sharing assumes the_content and the_excerpt filters are only
				 * getting called once. The fix here is to disable processing of filters
				 * on the_content in the inserted page.
				 *
				 * @see https://codex.wordpress.org/Function_Reference/the_content#Alternative_Usage
				 */
				switch ( $attributes['display'] ) {

					case 'content':
						// If Elementor is installed, try to render the page with it. If there is no Elementor content, fall back to normal rendering.
						if ( class_exists( '\Elementor\Plugin' ) ) {
							$elementor_content = \Elementor\Plugin::$instance->frontend->get_builder_content( $inserted_page->ID );
							if ( strlen( $elementor_content ) > 0 ) {
								$echo .= $elementor_content;
								break;
							}
						}

						// Render the content normally.
						$content = get_post_field( 'post_content', $inserted_page->ID );
						if ( $attributes['should_apply_the_content_filter'] ) {
							$content = apply_filters( 'the_content', $content );
						}
						$echo .= $content;
						break;

				}

				// Save output buffer contents.
				$content = ob_get_clean();

			} else { // Use "Legacy" insert method (query_posts).

				// Construct query_posts arguments.
				if ( is_numeric( $attributes['page'] ) ) {
					$args = array(
						'p' => intval( $attributes['page'] ),
						'post_type' => get_post_types(),
						'post_status' => $attributes['public'] ? array( 'publish', 'private' ) : array( 'publish' ),
					);
				} else {
					$args = array(
						'name' => esc_attr( $attributes['page'] ),
						'post_type' => get_post_types(),
						'post_status' => $attributes['public'] ? array( 'publish', 'private' ) : array( 'publish' ),
					);
				}

				// We save the previous query state here instead of using
				// wp_reset_query() because wp_reset_query() only has a single stack
				// variable ($GLOBALS['wp_the_query']). This allows us to support
				// pages inserted into other pages (multiple nested pages).
				$old_query = $GLOBALS['wp_query'];
				$posts = query_posts( $args );

				// Prevent unprivileged users from inserting private posts from others.
				if ( have_posts() ) {
					$can_read = true;
					$parent_post_author_id = intval( get_the_author_meta( 'ID' ) );
					foreach ( $posts as $post ) {
						if ( is_object( $post ) && 'publish' !== $post->post_status ) {
							$post_type = get_post_type_object( $post->post_type );
							if ( ! user_can( $parent_post_author_id, $post_type->cap->read_post, $post->ID ) ) {
								$can_read = false;
							}
						}
					}
					if ( ! $can_read ) {
						// Force an empty query so we don't show any posts.
						$posts = query_posts( array( 'post__in' => array( 0 ) ) );
					}
				}

				if ( have_posts() ) {
					// Start output buffering so we can save the output to string.
					ob_start();

					// If Beaver Builder, SiteOrigin Page Builder, Elementor, or WPBakery
					// Page Builder (Visual Composer) are enabled, load any cached styles
					// associated with the inserted page.
					// Note: Temporarily set the global $post->ID to the inserted page ID,
					// since both builders rely on the id to load the appropriate styles.
					if (
						class_exists( 'UAGB_Post_Assets' ) ||
						class_exists( 'FLBuilder' ) ||
						class_exists( 'SiteOrigin_Panels' ) ||
						class_exists( '\Elementor\Post_CSS_File' ) ||
						defined( 'VCV_VERSION' ) ||
						defined( 'WPB_VC_VERSION' )
					) {
						// If we're not in The Loop (i.e., global $post isn't assigned),
						// temporarily populate it with the post to be inserted so we can
						// retrieve generated styles for that post. Reset $post to null
						// after we're done.
						if ( is_null( $post ) ) {
							$old_post_id = null;
							$post = $inserted_page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
						} else {
							$old_post_id = $post->ID;
							$post->ID = $inserted_page->ID;
						}

						// Enqueue assets for Ultimate Addons for Gutenberg.
						// See: https://ultimategutenberg.com/docs/assets-api-third-party-plugins/.
						if ( class_exists( 'UAGB_Post_Assets' ) ) {
							$post_assets_instance = new \UAGB_Post_Assets( $inserted_page->ID );
							$post_assets_instance->enqueue_scripts();
						}

						if ( class_exists( 'FLBuilder' ) ) {
							\FLBuilder::enqueue_layout_styles_scripts( $inserted_page->ID );
						}

						if ( class_exists( 'SiteOrigin_Panels' ) ) {
							$renderer = \SiteOrigin_Panels::renderer();
							$renderer->add_inline_css( $inserted_page->ID, $renderer->generate_css( $inserted_page->ID ) );
						}

						if ( class_exists( '\Elementor\Post_CSS_File' ) ) {
							$css_file = new \Elementor\Post_CSS_File( $inserted_page->ID );
							$css_file->enqueue();
						}

						// Enqueue custom style from WPBakery Page Builder (Visual Composer).
						if ( defined( 'VCV_VERSION' ) ) {
							wp_enqueue_style( 'vcv:assets:front:style' );
							wp_enqueue_script( 'vcv:assets:runtime:script' );
							wp_enqueue_script( 'vcv:assets:front:script' );

							$bundle_url = get_post_meta( $inserted_page->ID, 'vcvSourceCssFileUrl', true );
							if ( $bundle_url ) {
								$version = get_post_meta( $inserted_page->ID, 'vcvSourceCssFileHash', true );
								if ( ! preg_match( '/^http/', $bundle_url ) ) {
									if ( ! preg_match( '/assets-bundles/', $bundle_url ) ) {
										$bundle_url = '/assets-bundles/' . $bundle_url;
									}
								}
								if ( preg_match( '/^http/', $bundle_url ) ) {
									$bundle_url = set_url_scheme( $bundle_url );
								} elseif ( defined( 'VCV_TF_ASSETS_IN_UPLOADS' ) && constant( 'VCV_TF_ASSETS_IN_UPLOADS' ) ) {
									$upload_dir = wp_upload_dir();
									$bundle_url = set_url_scheme( $upload_dir['baseurl'] . '/' . VCV_PLUGIN_ASSETS_DIRNAME . '/' . ltrim( $bundle_url, '/\\' ) );
								} elseif ( class_exists( 'VisualComposer\Helpers\AssetsEnqueue' ) ) {
									// These methods should work for Visual Composer 26.0.
									// Enqueue custom css/js stored in vcvSourceAssetsFiles postmeta.
									$vc = new \VisualComposer\Helpers\AssetsEnqueue;
									if ( method_exists( $vc, 'enqueueAssets' ) ) {
										$vc->enqueueAssets($inserted_page->ID);
									}
									// Enqueue custom CSS stored in vcvSourceCssFileUrl postmeta.
									$upload_dir = wp_upload_dir();
									$bundle_url = set_url_scheme( $upload_dir['baseurl'] . '/' . VCV_PLUGIN_ASSETS_DIRNAME . '/' . ltrim( $bundle_url, '/\\' ) );
								} else {
									$bundle_url = content_url() . '/' . VCV_PLUGIN_ASSETS_DIRNAME . '/' . ltrim( $bundle_url, '/\\' );
								}
								wp_enqueue_style(
									'vcv:assets:source:main:styles:' . sanitize_title( $bundle_url ),
									$bundle_url,
									array(),
									VCV_VERSION . '.' . $version
								);
							}
						}

						// GoodLayers page builder content (retrieved from post meta).
						// See: https://docs.goodlayers.com/add-page-builder-in-product/.
						do_action( 'gdlr_core_print_page_builder' );

						if ( is_null( $old_post_id ) ) {
							$post = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
						} else {
							$post->ID = $old_post_id;
						}
					}

					/**
					 * Show either the title, link, content, everything, or everything via a
					 * custom template.
					 *
					 * Note: if the sharing_display filter exists, it means Jetpack is
					 * installed and Sharing is enabled; this plugin conflicts with Sharing,
					 * because Sharing assumes the_content and the_excerpt filters are only
					 * getting called once. The fix here is to disable processing of filters
					 * on the_content in the inserted page.
					 *
					 * @see https://codex.wordpress.org/Function_Reference/the_content#Alternative_Usage
					 */
					switch ( $attributes['display'] ) {
						case 'title':
							the_post();
							$title_tag = $attributes['inline'] ? 'span' : 'h1';
							$echo .= "<$title_tag class='insert-page-title'>";
							$echo .= the_title('', '', false);
							$echo .= "</$title_tag>";
							break;
						case 'title-content':
							// Title.
							the_post();
							$title_tag = $attributes['inline'] ? 'span' : 'h1';
							$echo .= "<$title_tag class='insert-page-title'>";
							$echo .= the_title('', '', false);
							$echo .= "</$title_tag>";
							// Content.
							// If Elementor is installed, try to render the page with it. If there is no Elementor content, fall back to normal rendering.
							if ( class_exists( '\Elementor\Plugin' ) ) {
								$elementor_content = \Elementor\Plugin::$instance->frontend->get_builder_content( $inserted_page->ID );
								if ( strlen( $elementor_content ) > 0 ) {
									$echo .= $elementor_content;
									break;
								}
							}
							// Render the content normally.
							if ( $attributes['should_apply_the_content_filter'] ) {
								// the_content();
							} else {
								$echo .= get_the_content();
							}
							// Render any <!--nextpage--> pagination links.
							wp_link_pages( array(
								'before' => '<div class="page-links">' . __( 'Pages:', 'ajax-sections-by-radaplug' ),
								'after'  => '</div>',
							) );
							break;
						case 'link':
							the_post();
							$echo .= '<h1><a href="' . get_permalink() . '">' . the_title('', '', false) . '</a></h1>';
							break;
						case 'excerpt':
							the_post();
							$echo .= '<h1><a href="' . get_permalink() . '">' . the_title('', '', false) . '</a></h1>';
							if ( $attributes['should_apply_the_content_filter'] ) {
								// the_excerpt();
							} else {
								$echo .= get_the_excerpt();
							}
							break;
						case 'excerpt-only':
							the_post();
							if ( $attributes['should_apply_the_content_filter'] ) {
								// the_excerpt();
							} else {
								$echo .= get_the_excerpt();
							}
							break;
						case 'content':
							// If Elementor is installed, try to render the page with it. If there is no Elementor content, fall back to normal rendering.
							if ( class_exists( '\Elementor\Plugin' ) ) {
								$elementor_content = \Elementor\Plugin::$instance->frontend->get_builder_content( $inserted_page->ID );
								if ( strlen( $elementor_content ) > 0 ) {
									$echo .= $elementor_content;
									break;
								}
							}
							// Render the content normally.
							the_post();
							if ( $attributes['should_apply_the_content_filter'] ) {
								// the_content();
							} else {
								$echo .= get_the_content();
							}
							// Render any <!--nextpage--> pagination links.
							wp_link_pages( array(
								'before' => '<div class="page-links">' . __( 'Pages:', 'ajax-sections-by-radaplug' ),
								'after'  => '</div>',
							) );
							break;
						case 'post-thumbnail':
							$size = empty( $attributes['size'] ) ? 'post-thumbnail' : $attributes['size'];
							$echo .= '<a href="' . get_permalink( $inserted_page->ID ) . '">' . get_the_post_thumbnail( $inserted_page->ID, $size ) . '</a>';
							break;
						default: // Display is either invalid, or contains a template file to use.
							$template = locate_template( $attributes['display'] );
							// Only allow templates that don't have any directory traversal in
							// them (to prevent including php files that aren't in the active
							// theme directory or the /wp-includes/theme-compat/ directory).
							$path_in_theme_or_childtheme_or_compat = (
								// Template is in current theme folder.
								0 === strpos( realpath( $template ), realpath( get_stylesheet_directory() ) ) ||
								// Template is in current or parent theme folder.
								0 === strpos( realpath( $template ), realpath( get_template_directory() ) ) ||
								// Template is in theme-compat folder.
								0 === strpos( realpath( $template ), realpath( ABSPATH . WPINC . '/theme-compat/' ) )
							);
							if ( strlen( $template ) > 0 && $path_in_theme_or_childtheme_or_compat ) {
								// include $template; // Execute the template code.
							} else { // Couldn't find template, so fall back to printing a link to the page.
								the_post();
								$echo .= '<a href="' . get_permalink() . '">' . the_title('', '', false) . '</a>';
							}
							break;
					}
					// Save output buffer contents.
					$content = ob_get_clean();
				} else {
					/**
					 * Filter the html that should be displayed if an inserted page was not found.
					 *
					 * @param string $content html to be displayed. Defaults to an empty string.
					 */
					$content = apply_filters( 'insert_pages_not_found_message', $content );
				}
				// Restore previous query and update the global template variables.
				$GLOBALS['wp_query'] = $old_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				wp_reset_postdata();
			}

			/**
			 * Filter the markup generated for the inserted page.
			 *
			 * @param string $content The post content of the inserted page.
			 * @param object $inserted_page The post object returned from querying the inserted page.
			 * @param array $attributes Extra parameters modifying the inserted page.
			 *   page: Page ID or slug of page to be inserted.
			 *   display: Content to display from inserted page.
			 *   class: Extra classes to add to inserted page wrapper element.
			 *   id: Optional ID for the inserted page wrapper element.
			 *   inline: Boolean indicating wrapper element should be a span.
			 *   public: Boolean indicating anonymous users can see private inserted pages.
			 *   querystring: Extra querystring values provided to the custom template.
			 *   should_apply_the_content_filter: Whether to apply the_content filter to post contents and excerpts.
			 *   wrapper_tag: Tag to use for the wrapper element (e.g., div, span).
			 */
			$content = apply_filters( 'insert_pages_wrap_content', $content, $inserted_page, $attributes );

			$GLOBALS['wp']->query_vars = $original_wp_query_vars;

			// Loop detection: remove the page from the stack (so we can still insert
			// the same page multiple times on another page, but prevent it from being
			// inserted multiple times within the same recursive chain).
			if ( isset( $inserted_page->ID ) ) {
				foreach ( $this->inserted_page_ids as $key => $page_id ) {
					if ( $page_id === $inserted_page->ID ) {
						unset( $this->inserted_page_ids[ $key ] );
					}
				}
			} elseif ( is_array( $inserted_page ) && ! empty( $inserted_page ) ) {
				// Legacy template code populates $inserted_page with query_posts()
				// output. Remove each from the stack (should just be a single page).
				foreach ( $inserted_page as $page ) {
					foreach ( $this->inserted_page_ids as $key => $page_id ) {
						if ( $page_id === $page->ID ) {
							unset( $this->inserted_page_ids[ $key ] );
						}
					}
				}
			}

			return $echo . $content;
		}

		/**
		 * Default filter for insert_pages_wrap_content.
		 *
		 * @param  string $content    Content of shortcode.
		 * @param  array  $posts      Post data of inserted page.
		 * @param  array  $attributes Shortcode attributes.
		 * @return string             Content to replace shortcode.
		 */
		public function insert_pages_wrap_content( $content, $posts, $attributes ) {
			return sprintf(
				'<%1$s data-post-id="%2$s" class="insert-page insert-page-%2$s %3$s"%4$s>%5$s</%1$s>',
				esc_attr( $attributes['wrapper_tag'] ),
				esc_attr( $attributes['page'] ),
				esc_attr( $attributes['class'] ),
				empty( $attributes['id'] ) ? '' : ' id="' . esc_attr( $attributes['id'] ) . '"',
				$content
			);
		}

		/**
		 * Indicates whether a particular post type is able to be inserted.
		 *
		 * @param  boolean $type Post type.
		 * @return boolean       Whether post type is insertable.
		 */
		private function is_post_type_insertable( $type ) {
			return ! in_array(
				$type,
				array(
					'nav_menu_item',
					'attachment',
					'revision',
					'customize_changeset',
					'oembed_cache',
					// Exclude Flamingo messages (created via Contact Form 7 submissions).
					// See: https://wordpress.org/support/topic/plugin-hacked-14/
					'flamingo_inbound',
				),
				true
			);
		}

	}
}

// Initialize Radaplug_Insert_Pages object.
if ( class_exists( 'Radaplug_Ajax_Sections_Name_Space\Radaplug_Insert_Pages_Name_Space\Radaplug_Insert_Pages' ) ) {
	$insert_pages_plugin = Radaplug_Insert_Pages::get_instance();
}

// Actions and Filters handled by InsertPagesPlugin class.
if ( isset( $insert_pages_plugin ) ) {
	// Register shortcode [radaplug-insert ...].
	add_action( 'init', array( $insert_pages_plugin, 'insert_pages_init' ), 1 );

	// Use internal filter to wrap inserted content in a div or span.
	add_filter( 'insert_pages_wrap_content', array( $insert_pages_plugin, 'insert_pages_wrap_content' ), 10, 3 );
}
