<?php
/**
 * Plugin Name: Ajax Load More for Users
 * Plugin URI: https://connekthq.com/plugins/ajax-load-more/extensions/users/
 * Description: Ajax Load More extension to infinite scroll WordPress users.
 * Author: Darren Cooney
 * Twitter: @KaptonKaos
 * Author URI: https://connekthq.com
 * Version: 1.1
 * License: GPL
 * Copyright: Darren Cooney & Connekt Media
 *
 * @package ALM_Users
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ALM_USERS_PATH' ) ) {
	define( 'ALM_USERS_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ALM_USERS_URL' ) ) {
	define( 'ALM_USERS_URL', plugins_url( '', __FILE__ ) );
}

// Plugin installation helpers.
require_once plugin_dir_path( __FILE__ ) . 'functions/install.php';

/**
 *  Installation hook.
 */
function alm_users_extension_install() {
	// Users add-on is installed.
	if ( is_plugin_active( 'ajax-load-more-users/ajax-load-more-users.php' ) ) {
		// Deactivate the add-on.
		deactivate_plugins( 'ajax-load-more-users/ajax-load-more-users.php' );
	}

	// ALM Pro add-on is installed and Users is activated.
	if ( is_plugin_active( 'ajax-load-more-pro/ajax-load-more-pro.php' ) && class_exists( 'ALMUsers' ) ) {
		set_transient( 'alm_users_extension_pro_admin_notice', true, 5 );
	}

	// Confirm core Ajax Load More is installed.
	if ( ! is_plugin_active( 'ajax-load-more/ajax-load-more.php' ) ) {
		set_transient( 'alm_users_extension_admin_notice', true, 5 );
	}
}
register_activation_hook( __FILE__, 'alm_users_extension_install' );

if ( ! class_exists( 'ALMUsers' ) ) :
	/**
	 * User Class.
	 */
	class ALMUsers {

		/**
		 * Construct the class.
		 */
		public function __construct() {
			add_action( 'alm_users_installed', [ &$this, 'alm_users_installed' ] );
			add_action( 'wp_ajax_alm_users', [ &$this, 'alm_users_query' ] );
			add_action( 'wp_ajax_nopriv_alm_users', [ &$this, 'alm_users_query' ] );
			add_filter( 'alm_users_shortcode', [ &$this, 'alm_users_shortcode' ], 10, 7 );
			add_filter( 'alm_users_preloaded', [ &$this, 'alm_users_preloaded' ], 10, 4 );
			add_action( 'alm_users_settings', [ &$this, 'alm_users_settings' ] );
		}

		/**
		 * Preload users if preloaded is true in alm shortcode.
		 *
		 * @param array  $args             The query args.
		 * @param string $preloaded_amount The preloaded amount.
		 * @param string $repeater         The Repeater Template name.
		 * @param string $theme_repeater   The Theme Repeater name.
		 * @since 1.0
		 */
		public function alm_users_preloaded( $args, $preloaded_amount, $repeater, $theme_repeater ) {
			$id      = isset( $args['id'] ) ? $args['id'] : '';
			$post_id = isset( $args['post_id'] ) ? $args['post_id'] : '';

			$offset           = isset( $args['offset'] ) ? $args['offset'] : 0;
			$preloaded_amount = isset( $preloaded_amount ) ? $preloaded_amount : $args['users_per_page'];
			$role             = isset( $args['users_role'] ) ? $args['users_role'] : '';
			$order            = isset( $args['users_order'] ) ? $args['users_order'] : 5;
			$orderby          = isset( $args['users_orderby'] ) ? $args['users_orderby'] : 'user_login';
			$include          = isset( $args['users_include'] ) ? $args['users_include'] : false;
			$exclude          = isset( $args['users_exclude'] ) ? $args['users_exclude'] : false;
			$search           = isset( $args['search'] ) ? $args['search'] : '';

			// Custom Fields.
			$meta_key     = isset( $args['meta_key'] ) ? $args['meta_key'] : '';
			$meta_value   = isset( $args['meta_value'] ) ? $args['meta_value'] : '';
			$meta_compare = isset( $args['meta_compare'] ) ? $args['meta_compare'] : '';
			if ( empty( $meta_compare ) ) {
				$meta_compare = 'IN';
			}
			if ( $meta_compare === 'lessthan' ) {
				$meta_compare = '<'; // do_shortcode fix (shortcode was rendering as HTML).
			}
			if ( $meta_compare === 'lessthanequalto' ) {
				$meta_compare = '<='; // do_shortcode fix (shortcode was rendering as HTML).
			}
			$meta_relation = ( isset( $args['meta_relation'] ) ) ? $args['meta_relation'] : '';
			if ( empty( $meta_relation ) ) {
				$meta_relation = 'AND';
			}
			$meta_type = ( isset( $args['meta_type'] ) ) ? $args['meta_type'] : '';
			if ( empty( $meta_type ) ) {
				$meta_type = 'CHAR';
			}

			$data            = '';
			$alm_found_posts = 0;

			if ( ! empty( $role ) ) {

				// Get decrypted role.
				$role = alm_role_decrypt( $role );

				// Get query type.
				$role_query = self::alm_users_get_role_query_type( $role );

				// Get user role array.
				$role = self::alm_users_get_role_as_array( $role, $role_query );

				// User Query.
				$preloaded_args = [
					$role_query => $role,
					'number'    => $preloaded_amount,
					'order'     => $order,
					'orderby'   => $orderby,
					'offset'    => $offset,
				];

				// Search.
				if ( $search ) {
					$preloaded_args['search']         = $search;
					$preloaded_args['search_columns'] = apply_filters( 'alm_users_query_search_columns_' . $id, [ 'user_login', 'display_name', 'user_nicename' ] );
				}

				// Include.
				if ( $include ) {
					$preloaded_args['include'] = explode( ',', $include );
				}

				// Exclude.
				if ( $exclude ) {
					$preloaded_args['exclude'] = explode( ',', $exclude );
				}

				// Meta Query.
				if ( ! empty( $meta_key ) && ! empty( $meta_value ) || ! empty( $meta_key ) && $meta_compare !== 'IN' ) {

					// Parse multiple meta query.
					$meta_query_total = count( explode( ':', $meta_key ) ); // Total meta_query objects.
					$meta_keys        = explode( ':', $meta_key ); // convert to array.
					$meta_value       = explode( ':', $meta_value ); // convert to array.
					$meta_compare     = explode( ':', $meta_compare ); // convert to array.
					$meta_type        = explode( ':', $meta_type ); // convert to array.

					// Loop Meta Query.
					$preloaded_args['meta_query'] = [
						'relation' => $meta_relation,
					];

					for ( $mq_i = 0; $mq_i < $meta_query_total; $mq_i++ ) {
						$preloaded_args['meta_query'][] = alm_get_meta_query( $meta_keys[ $mq_i ], $meta_value[ $mq_i ], $meta_compare[ $mq_i ], $meta_type[ $mq_i ] );
					}
				}

				// Meta_key, used for ordering by meta value.
				if ( ! empty( $meta_key ) ) {
					if ( strpos( $orderby, 'meta_value' ) !== false ) { // Only order by meta_key, if $orderby is set to meta_value{_num}.
						$meta_key_single            = explode( ':', $meta_key );
						$preloaded_args['meta_key'] = $meta_key_single[0];
					}
				}

				/**
				 * ALM Users Filter Hook.
				 *
				 * @return $args;
				 */
				$preloaded_args = apply_filters( 'alm_users_query_args_' . $id, $preloaded_args, $post_id );

				// WP_User_Query.
				$user_query = new WP_User_Query( $preloaded_args );

				$alm_found_posts = $user_query->total_users;
				$alm_page        = 0;
				$alm_item        = 0;
				$alm_current     = 0;

				if ( ! empty( $user_query->results ) ) {
					ob_start();

					foreach ( $user_query->results as $user ) {

						$alm_item++;
						$alm_current++;

						// Repeater Template.
						if ( $theme_repeater !== 'null' && has_action( 'alm_get_theme_repeater' ) ) {
							// Theme Repeater.
							do_action( 'alm_get_users_theme_repeater', $theme_repeater, $alm_found_posts, $alm_page, $alm_item, $alm_current, $user );
						} else {
							// Repeater.
							$type = alm_get_repeater_type( $repeater );
							include alm_get_current_repeater( $repeater, $type );
						}
						// End Repeater Template.

					}
					$data = ob_get_clean();

				} else {
					$data = null;
				}
			}

			$results = [
				'data'  => $data,
				'total' => $alm_found_posts,
			];

			return $results;
		}

		/**
		 * Query users via wp_user_query, send results via ajax.
		 *
		 * @see https://codex.wordpress.org/Class_Reference/WP_User_Query
		 *
		 * @return JSON
		 * @since 1.0
		 */
		public function alm_users_query() {
			$form_data = filter_input_array( INPUT_GET, FILTER_SANITIZE_STRING );
			if ( ! $form_data ) {
				// Bail early if not an Ajax request.
				return;
			}

			$id             = isset( $form_data['id'] ) ? $form_data['id'] : '';
			$post_id        = isset( $form_data['post_id'] ) ? $form_data['post_id'] : '';
			$page           = isset( $form_data['page'] ) ? $form_data['page'] : 0;
			$offset         = isset( $form_data['offset'] ) ? $form_data['offset'] : 0;
			$repeater       = isset( $form_data['repeater'] ) ? $form_data['repeater'] : 'default';
			$type           = alm_get_repeater_type( $repeater );
			$theme_repeater = isset( $form_data['theme_repeater'] ) ? $form_data['theme_repeater'] : 'null';
			$query_type     = isset( $form_data['query_type'] ) ? $form_data['query_type'] : 'standard';
			$search         = isset( $form_data['search'] ) ? $form_data['search'] : '';
			$canonical_url  = isset( $form_data['canonical_url'] ) ? $form_data['canonical_url'] : $_SERVER['HTTP_REFERER'];

			// Users data array - from ajax-load-more.js.
			$data = isset( $form_data['users'] ) ? $form_data['users'] : '';
			if ( $data ) {
				$role           = isset( $data['role'] ) ? $data['role'] : '';
				$users_per_page = isset( $data['per_page'] ) ? $data['per_page'] : 5;
				$order          = isset( $data['order'] ) ? $data['order'] : 5;
				$orderby        = isset( $data['orderby'] ) ? $data['orderby'] : 'login';
				$include        = isset( $data['include'] ) ? $data['include'] : false;
				$exclude        = isset( $data['exclude'] ) ? $data['exclude'] : false;
			}

			// Custom Fields.
			$meta_key     = isset( $form_data['meta_key'] ) ? $form_data['meta_key'] : '';
			$meta_value   = isset( $form_data['meta_value'] ) ? $form_data['meta_value'] : '';
			$meta_compare = isset( $form_data['meta_compare'] ) ? $form_data['meta_compare'] : '';
			if ( empty( $meta_compare ) ) {
				$meta_compare = 'IN';
			}
			if ( $meta_compare === 'lessthan' ) {
				$meta_compare = '<'; // do_shortcode fix (shortcode was rendering as HTML).
			}
			if ( $meta_compare === 'lessthanequalto' ) {
				$meta_compare = '<='; // do_shortcode fix (shortcode was rendering as HTML).
			}
			$meta_relation = isset( $form_data['meta_relation'] ) ? $form_data['meta_relation'] : '';
			if ( empty( $meta_relation ) ) {
				$meta_relation = 'AND';
			}
			$meta_type = isset( $form_data['meta_type'] ) ? $form_data['meta_type'] : '';
			if ( empty( $meta_type ) ) {
				$meta_type = 'CHAR';
			}

			// Cache Add-on.
			$cache_id        = isset( $form_data['cache_id'] ) ? sanitize_text_field( $form_data['cache_id'] ) : '';
			$cache_slug      = isset( $form_data['cache_slug'] ) && $form_data['cache_slug'] ? sanitize_text_field( $form_data['cache_slug'] ) : '';
			$cache_logged_in = isset( $form_data['cache_logged_in'] ) ? $form_data['cache_logged_in'] : false;
			$do_create_cache = $cache_logged_in === 'true' && is_user_logged_in() ? false : true;

			// Preload Add-on.
			$preloaded        = isset( $form_data['preloaded'] ) ? $form_data['preloaded'] : false;
			$preloaded_amount = isset( $form_data['preloaded_amount'] ) ? $form_data['preloaded_amount'] : '5';
			if ( has_action( 'alm_preload_installed' ) && $preloaded === 'true' ) {
				$old_offset     = $preloaded_amount;
				$offset         = $offset + $preloaded_amount;
				$alm_loop_count = $old_offset;
			} else {
				$alm_loop_count = 0;
			}

			// SEO Add-on.
			$seo_start_page = isset( $form_data['seo_start_page'] ) ? $form_data['seo_start_page'] : 1;

			if ( ! empty( $role ) ) { // Role Defined.

				// Get decrypted role.
				$role = alm_role_decrypt( $role );

				// Get query type.
				$role_query = self::alm_users_get_role_query_type( $role );

				// Get user role array.
				$role = self::alm_users_get_role_as_array( $role, $role_query );

				// User Query Args.
				$args = [
					$role_query => $role,
					'number'    => $users_per_page,
					'order'     => $order,
					'orderby'   => $orderby,
					'offset'    => $offset + ( $users_per_page * $page ),
				];

				// Search.
				if ( $search ) {
					$args['search']         = $search;
					$args['search_columns'] = apply_filters( 'alm_users_query_search_columns_' . $id, [ 'user_login', 'display_name', 'user_nicename' ] );
				}

				// Include.
				if ( $include ) {
					$args['include'] = explode( ',', $include );
				}

				// Exclude.
				if ( $exclude ) {
					$args['exclude'] = explode( ',', $exclude );
				}

				// Meta Query.
				if ( ! empty( $meta_key ) && ! empty( $meta_value ) || ! empty( $meta_key ) && $meta_compare !== 'IN' ) {

					// Parse multiple meta query.
					$meta_query_total = count( explode( ':', $meta_key ) ); // Total meta_query objects.
					$meta_keys        = explode( ':', $meta_key ); // Convert to array.
					$meta_value       = explode( ':', $meta_value ); // Convert to array.
					$meta_compare     = explode( ':', $meta_compare ); // Convert to array.
					$meta_type        = explode( ':', $meta_type ); // Convert to array.

					// Loop Meta Query.
					$args['meta_query'] = [
						'relation' => $meta_relation,
					];
					for ( $mq_i = 0; $mq_i < $meta_query_total; $mq_i++ ) {
						$args['meta_query'][] = alm_get_meta_query( $meta_keys[ $mq_i ], $meta_value[ $mq_i ], $meta_compare[ $mq_i ], $meta_type[ $mq_i ] );
					}
				}

				// Meta_key, used for ordering by meta value.
				if ( ! empty( $meta_key ) ) {
					if ( strpos( $orderby, 'meta_value' ) !== false ) { // Only order by meta_key, if $orderby is set to meta_value{_num}.
						$meta_key_single  = explode( ':', $meta_key );
						$args['meta_key'] = $meta_key_single[0];
					}
				}

				/**
				 * ALM Users Filter Hook.
				 *
				 * @return $args;
				 */
				$args = apply_filters( 'alm_users_query_args_' . $id, $args, $post_id );

				/**
				 * ALM Core Filter Hook
				 *
				 * @return $alm_query/false;
				 */
				$debug = apply_filters( 'alm_debug', false ) && ! $cache_id ? $args : false;

				// WP_User_Query.
				$user_query = new WP_User_Query( $args );

				if ( $query_type === 'totalposts' ) {
					$return = [
						'totalposts' => ! empty( $user_query->results ) ? $user_query->total_users : 0,
					];

				} else {
					$alm_page       = $page;
					$alm_item       = 0;
					$alm_current    = 0;
					$alm_page_count = $page === 0 ? 1 : $page + 1;
					$data           = '';

					if ( ! empty( $user_query->results ) ) {
						$alm_post_count  = count( $user_query->results ); // total for this query.
						$alm_found_posts = $user_query->total_users; // total of entire query.

						ob_start();
						foreach ( $user_query->results as $user ) {
							$alm_item++;
							$alm_current++;
							$alm_item = ( $alm_page_count * $users_per_page ) - $users_per_page + $alm_loop_count; // Get current item.
							if ( $theme_repeater !== 'null' && has_action( 'alm_get_theme_repeater' ) ) {
								// Theme Repeater.
								do_action( 'alm_get_users_theme_repeater', $theme_repeater, $alm_found_posts, $alm_page, $alm_item, $alm_current, $user );
							} else {
								// Repeater.
								include alm_get_current_repeater( $repeater, $type );
							}
						}

						$data = ob_get_clean();
					}

					// Build return data.
					$return = [
						'html' => $data,
						'meta' => [
							'postcount'  => isset( $alm_post_count ) ? $alm_post_count : 0,
							'totalposts' => isset( $alm_found_posts ) ? $alm_found_posts : 0,
							'debug'      => $debug,
						],
					];

					/**
					 * Cache Add-on.
					 * Create the cache file.
					 */
					if ( $cache_id && method_exists( 'ALMCache', 'create_cache_file' ) && $do_create_cache ) {
						ALMCache::create_cache_file( $cache_id, $cache_slug, $canonical_url, $data, $alm_post_count, $alm_found_posts );
					}
				}
			} else {
				// Role is empty.
				// Build return data.
				$return = [
					'html' => null,
					'meta' => [
						'postcount'  => 0,
						'totalposts' => 0,
						'debug'      => $false,
					],
				];
			}
			wp_send_json( $return );
		}

		/**
		 * Return the role query parameter.
		 *
		 * @see https://codex.wordpress.org/Class_Reference/WP_User_Query#User_Role_Parameter
		 *
		 * @param string $role The current role.
		 * @return string
		 * @since 1.1
		 */
		public static function alm_users_get_role_query_type( $role ) {
			return $role === 'all' ? 'role' : 'role__in';
		}

		/**
		 * Return the user role(s) as an array
		 * https://codex.wordpress.org/Class_Reference/WP_User_Query#User_Role_Parameter
		 *
		 * @param string $role array The array.
		 * @return array The roles as an array.
		 * @since 1.1
		 */
		public static function alm_users_get_role_as_array( $role = 'all' ) {
			if ( $role !== 'all' ) {
				$role = preg_replace( '/\s+/', '', $role ); // Remove whitespace from $role.
				$role = explode( ',', $role ); // Convert $role to Array.
			} else {
				$role = '';
			}

			return $role;
		}

		/**
		 * Build Users shortcode params and send back to core ALM.
		 *
		 * @param string $users_role The users role.
		 * @param string $users_include The include query param.
		 * @param string $users_exclude The exclude query param.
		 * @param string $users_per_page The per_page query param.
		 * @param string $users_order The order query param.
		 * @param string $users_orderby The orderby query param.
		 * @return string The HTML data attributes as a string.
		 * @since 1.0
		 */
		public function alm_users_shortcode( $users_role, $users_include, $users_exclude, $users_per_page, $users_order, $users_orderby ) {
			$return  = ' data-users="true"';
			$return .= ' data-users-role="' . alm_role_encrypt( $users_role ) . '"';
			$return .= ' data-users-include="' . $users_include . '"';
			$return .= ' data-users-exclude="' . $users_exclude . '"';
			$return .= ' data-users-per-page="' . $users_per_page . '"';
			$return .= ' data-users-order="' . $users_order . '"';
			$return .= ' data-users-orderby="' . $users_orderby . '"';
			return $return;
		}

		/**
		 * An empty function to determine if users is true.
		 *
		 * @return boolean
		 * @since 1.0
		 */
		public function alm_users_installed() {
			return true;
		}

		/**
		 * Create the Comments settings panel.
		 *
		 * @since 1.0
		 */
		public function alm_users_settings() {
			register_setting(
				'alm_users_license',
				'alm_users_license_key',
				'alm_users_sanitize_license'
			);
		}
	}

	/**
	 * Encrypt a user role.
	 *
	 * @param string $string The role as a string.
	 * @param int    $key The key length.
	 * @return string The encrypted user role.
	 */
	function alm_role_encrypt( $string, $key = 5 ) {
		$result = '';
		for ( $i = 0, $k = strlen( $string ); $i < $k; $i++ ) {
			$char    = substr( $string, $i, 1 );
			$keychar = substr( $key, ( $i % strlen( $key ) ) - 1, 1 );
			$char    = chr( ord( $char ) + ord( $keychar ) );
			$result .= $char;
		}
		return base64_encode( $result );  // phpcs:ignore
	}

	/**
	 * Decrypt a user role.
	 *
	 * @param string $string The role as a string.
	 * @param int    $key The key length.
	 * @return string The encrypted user role.
	 */
	function alm_role_decrypt( $string, $key = 5 ) {
		$result = '';
		$string = base64_decode( $string ); // phpcs:ignore
		for ( $i = 0,$k = strlen( $string ); $i < $k; $i++ ) {
			$char    = substr( $string, $i, 1 );
			$keychar = substr( $key, ( $i % strlen( $key ) ) - 1, 1 );
			$char    = chr( ord( $char ) - ord( $keychar ) );
			$result .= $char;
		}
		return $result;
	}

	/**
	 * The main function responsible for returning Ajax Load More Users.
	 *
	 * @since 1.0
	 */
	function alm_users() {
		global $alm_users;
		if ( ! isset( $alm_users ) ) {
			$alm_users = new ALMUsers();
		}
		return $alm_users;
	}
	alm_users();

endif;
