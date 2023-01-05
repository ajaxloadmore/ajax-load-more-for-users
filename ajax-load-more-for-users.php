<?php
/**
 * Plugin Name: Ajax Load More for Users
 * Plugin URI: https://connekthq.com/plugins/ajax-load-more/extensions/users/
 * Description: Ajax Load More extension to infinite scroll WordPress users.
 * Author: Darren Cooney
 * Twitter: @KaptonKaos
 * Author URI: https://connekthq.com
 * Version: 1.0
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
			add_action( 'alm_users_installed', array( &$this, 'alm_users_installed' ) );
			add_action( 'wp_ajax_alm_users', array( &$this, 'alm_users_query' ) );
			add_action( 'wp_ajax_nopriv_alm_users', array( &$this, 'alm_users_query' ) );
			add_filter( 'alm_users_shortcode', array( &$this, 'alm_users_shortcode' ), 10, 7 );
			add_filter( 'alm_users_preloaded', array( &$this, 'alm_users_preloaded' ), 10, 4 );
			add_action( 'alm_users_settings', array( &$this, 'alm_users_settings' ) );
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
				$preloaded_args = array(
					$role_query => $role,
					'number'    => $preloaded_amount,
					'order'     => $order,
					'orderby'   => $orderby,
					'offset'    => $offset,
				);

				// Search.
				if ( $search ) {
					$preloaded_args['search']         = $search;
					$preloaded_args['search_columns'] = apply_filters( 'alm_users_query_search_columns_' . $id, array( 'user_login', 'display_name', 'user_nicename' ) );
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
					$preloaded_args['meta_query'] = array(
						'relation' => $meta_relation,
					);

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

			$results = array(
				'data'  => $data,
				'total' => $alm_found_posts,
			);

			return $results;
		}

		/**
		 * Query users via wp_user_query, send results via ajax.
		 *
		 * @see https://codex.wordpress.org/Class_Reference/WP_User_Query
		 *
		 * @return $return   JSON
		 * @since 1.0
		 */
		public function alm_users_query() {
			$params = filter_input_array( INPUT_GET, FILTER_SANITIZE_STRING );

			if ( ! isset( $params ) ) {
				// Bail early if not an Ajax request.
				return;
			}

			$id             = isset( $params['id'] ) ? $params['id'] : '';
			$post_id        = isset( $params['post_id'] ) ? $params['post_id'] : '';
			$page           = isset( $params['page'] ) ? $params['page'] : 0;
			$offset         = isset( $params['offset'] ) ? $params['offset'] : 0;
			$repeater       = isset( $params['repeater'] ) ? $params['repeater'] : 'default';
			$type           = alm_get_repeater_type( $repeater );
			$theme_repeater = isset( $params['theme_repeater'] ) ? $params['theme_repeater'] : 'null';
			$canonical_url  = isset( $params['canonical_url'] ) ? $params['canonical_url'] : $_SERVER['HTTP_REFERER'];
			$query_type     = isset( $params['query_type'] ) ? $params['query_type'] : 'standard'; // Ajax Query Type.
			$search         = isset( $params['search'] ) ? $params['search'] : '';

			// Users data array - from ajax-load-more.js.
			$data = isset( $params['users'] ) ? $params['users'] : '';
			if ( $data ) {
				$role           = isset( $data['role'] ) ? $data['role'] : '';
				$users_per_page = isset( $data['per_page'] ) ? $data['per_page'] : 5;
				$order          = isset( $data['order'] ) ? $data['order'] : 5;
				$orderby        = isset( $data['orderby'] ) ? $data['orderby'] : 'login';
				$include        = isset( $data['include'] ) ? $data['include'] : false;
				$exclude        = isset( $data['exclude'] ) ? $data['exclude'] : false;
			}

			// Custom Fields.
			$meta_key     = isset( $params['meta_key'] ) ? $params['meta_key'] : '';
			$meta_value   = isset( $params['meta_value'] ) ? $params['meta_value'] : '';
			$meta_compare = isset( $params['meta_compare'] ) ? $params['meta_compare'] : '';
			if ( empty( $meta_compare ) ) {
				$meta_compare = 'IN';
			}
			if ( $meta_compare === 'lessthan' ) {
				$meta_compare = '<'; // do_shortcode fix (shortcode was rendering as HTML).
			}
			if ( $meta_compare === 'lessthanequalto' ) {
				$meta_compare = '<='; // do_shortcode fix (shortcode was rendering as HTML).
			}
			$meta_relation = ( isset( $params['meta_relation'] ) ) ? $params['meta_relation'] : '';
			if ( empty( $meta_relation ) ) {
				$meta_relation = 'AND';
			}
			$meta_type = ( isset( $params['meta_type'] ) ) ? $params['meta_type'] : '';
			if ( empty( $meta_type ) ) {
				$meta_type = 'CHAR';
			}

			// Cache Add-on.
			$cache_id = isset( $params['cache_id'] ) ? $params['cache_id'] : '';
			$is_cache = ! empty( $cache_id ) && has_action( 'alm_cache_installed' );

			// Preload Add-on.
			$preloaded        = isset( $params['preloaded'] ) ? $params['preloaded'] : false;
			$preloaded_amount = isset( $params['preloaded_amount'] ) ? $params['preloaded_amount'] : '5';
			if ( has_action( 'alm_preload_installed' ) && $preloaded === 'true' ) {
				$old_offset     = $preloaded_amount;
				$offset         = $offset + $preloaded_amount;
				$alm_loop_count = $old_offset;
			} else {
				$alm_loop_count = 0;
			}

			// SEO Add-on.
			$seo_start_page = isset( $params['seo_start_page'] ) ? $params['seo_start_page'] : 1;

			if ( ! empty( $role ) ) { // Role Defined.

				// Get decrypted role.
				$role = alm_role_decrypt( $role );

				// Get query type.
				$role_query = self::alm_users_get_role_query_type( $role );

				// Get user role array.
				$role = self::alm_users_get_role_as_array( $role, $role_query );

				// User Query Args.
				$args = array(
					$role_query => $role,
					'number'    => $users_per_page,
					'order'     => $order,
					'orderby'   => $orderby,
					'offset'    => $offset + ( $users_per_page * $page ),
				);

				// Search.
				if ( $search ) {
					$args['search']         = $search;
					$args['search_columns'] = apply_filters( 'alm_users_query_search_columns_' . $id, array( 'user_login', 'display_name', 'user_nicename' ) );
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
					$meta_query_total = count( explode( ':', $meta_key ) ); // Total meta_query objects
					$meta_keys        = explode( ':', $meta_key ); // convert to array
					$meta_value       = explode( ':', $meta_value ); // convert to array
					$meta_compare     = explode( ':', $meta_compare ); // convert to array
					$meta_type        = explode( ':', $meta_type ); // convert to array

					// Loop Meta Query.
					$args['meta_query'] = array(
						'relation' => $meta_relation,
					);
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
				$debug = apply_filters( 'alm_debug', false ) && ! $is_cache ? $args : false;

				// WP_User_Query.
				$user_query = new WP_User_Query( $args );

				if ( $query_type === 'totalposts' ) {
					$return = array(
						'totalposts' => ! empty( $user_query->results ) ? $user_query->total_users : 0,
					);

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

						/**
						 * Cache Add-on hook.
						 * If Cache is enabled, check the cache file.
						 */
						if ( $is_cache ) {
							apply_filters( 'alm_cache_file', $cache_id, $page, $seo_start_page, $data, $preloaded );
						}
					}

					// Build return data.
					$return = array(
						'html' => $data,
						'meta' => array(
							'postcount'  => isset( $alm_post_count ) ? $alm_post_count : 0,
							'totalposts' => isset( $alm_found_posts ) ? $alm_found_posts : 0,
							'debug'      => $debug,
						),
					);
				}
			} else {
				// Role is empty.
				// Build return data.
				$return = array(
					'html' => null,
					'meta' => array(
						'postcount'  => 0,
						'totalposts' => 0,
						'debug'      => $false,
					),
				);
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
		return base64_encode( $result );
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
		$string = base64_decode( $string );
		for ( $i = 0,$k = strlen( $string ); $i < $k; $i++ ) {
			$char    = substr( $string, $i, 1 );
			$keychar = substr( $key, ( $i % strlen( $key ) ) - 1, 1 );
			$char    = chr( ord( $char ) - ord( $keychar ) );
			$result .= $char;
		}
		return $result;
	}

	/**
	 * Sanitize the license activation
	 *
	 * @param string $new The new license key.
	 * @since 1.0.0
	 */
	function alm_users_sanitize_license( $new ) {
		$old = get_option( 'alm_users_license_key' );
		if ( $old && $old !== $new ) {
			delete_option( 'alm_users_license_status' ); // new license has been entered, so must reactivate.
		}
		return $new;
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
