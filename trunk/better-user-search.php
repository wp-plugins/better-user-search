<?php
/**
 * Plugin Name: Better User Search
 * Plugin URI: https://wordpress.org/plugins/better-user-search/
 * Description: Improves the search for users in the backend significantly: Search for first name, last, email and more of users instead of only nicename.
 * Version: 1.0
 * Author: Dale Higgs
 * Author URI: mailto:dale3h@gmail.com
 * Requires at least: 3.0
 * Tested up to: 4.1
 *
 * This plugin is based on David Stöckl's Improved User Search in Backend plugin.
 * Although it has been completely rewritten, this notice is here to provide
 * credit to the original and inspiring author.
 *
 * Original Author: David Stöckl - http://www.blackbam.at/
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Run the init function when it's time to load the plugin
add_action( 'plugins_loaded', array( 'Better_User_Search', 'init' ) );

// This is here to prevent redeclaration of the class
if ( ! class_exists( 'Better_User_Search' ) ) {
	// This is where the magic happens!
	class Better_User_Search {
		// Plugin version
		public static $version = '1.0';

		// Instance of the class
		protected static $instance;

		// This function is called when loading our plugin
		public static function init() {
			// Check to see if an instance already exists
			if ( is_null( self::$instance ) ) {
				// Create a new instance
				self::$instance = new self;
			}

			// Return the instance
			return self::$instance;
		}

		// Class constructor
		public function __construct() {
			// This plugin is for the backend only
			if ( ! is_admin() ) {
				return;
			}

			// Add the overwrite actions for the search
			add_action( 'pre_user_query', array( $this, 'pre_user_query' ) );

			// Add the backend menu page
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );

			// Add a link to the Settings page on the Plugins page
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		}

		// Add the options page
		public function admin_menu() {
			// Add the options page
			$page = add_options_page(
				__( 'Better User Search Settings', 'better-user-search' ),
				__( 'User Search', 'better-user-search' ),
				'manage_options',
				'bu-search',
				array( $this, 'options_page' )
			);

			// Hooks for our scripts and stylesheets
			add_action( 'admin_print_styles-' . $page, array( $this, 'admin_styles' ) );
			add_action( 'admin_print_scripts-' . $page, array( $this, 'admin_scripts' ) );

			// This is so we can register our scripts and stylesheets
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}

		// Plugin initialization
		public function admin_init() {
			// Version so that the browser doesn't cache when we release new versions
			$version = self::$version;

			// Register stylesheets and scripts
			wp_register_style( 'bu-search-chosen', plugins_url( 'css/chosen.min.css', __FILE__ ), array(), $version );
			wp_register_script( 'bu-search-chosen', plugins_url( 'js/chosen.jquery.min.js', __FILE__ ), array( 'jquery' ), $version );
			wp_register_script( 'bu-search', plugins_url( 'js/bu-search.js', __FILE__ ), array( 'jquery' ), $version, true );

			// Register our setting so that it's automatically managed
			register_setting( 'bu-search-settings', 'bu_search_meta_keys' );
		}

		// Add our stylesheets
		public function admin_styles() {
			wp_enqueue_style( 'bu-search-chosen' );
		}

		// Add our scripts
		public function admin_scripts() {
			wp_enqueue_script( 'bu-search-chosen' );
			wp_enqueue_script( 'bu-search' );
		}

		// Add Settings link on Plugins page
		public function plugin_action_links( $actions ) {
			// Define our custom action link
			$custom_actions = array(
				sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php?page=bu-search' ), __( 'Settings' ) ),
			);

			// Merge our custom actions with the existing actions
			return array_merge( $custom_actions, $actions );
		}

		// The actual improvement of the query
		public function pre_user_query( $wp_user_query ) {
			/**
			 * Only modify the query if we meet certain conditions:
			 *   MUST NOT be searching for email
			 *   MUST be on users.php page
			 *   MUST specify search query
			 */
			if ( strpos( $wp_user_query->query_where, '@' ) === false
				&& stripos( $_SERVER['REQUEST_URI'], 'users.php' ) !== false
				&& ! empty( $_GET['s'] ) ) {
				global $wpdb;

				// Get the data we need from helper methods
				$terms       = $this->get_search_terms();
				$meta_keys   = $this->get_meta_keys();

				// Are we performing an AND (default) or an OR?
				$search_with_or = array_search( 'or', $terms );

				if ( $search_with_or !== false ) {
					// Remove the OR keyword from the terms
					unset( $terms[ $search_with_or ] );

					// Reset the array keys
					$terms = array_values( $terms );
				}

				// Set our @meta_keys MySQL user variable
				$wpdb->query( $sql = $wpdb->prepare( "SET @meta_keys := %s;", implode( ',', $meta_keys ) ) );

				// Build our data for $wpdb->prepare
				$values = array();

				foreach ( $terms as $term ) {
					$values[] = "%{$term}%";
				}

				// Our last value is for HAVING COUNT(*), so let's add that
				// Note the min count is 1 if we found OR in the terms
				$values[] = ( $search_with_or !== false ? 1 : count( $values ) );

				// Query for matching users
				$user_ids = $wpdb->get_col( $sql = $wpdb->prepare( "
					SELECT user_id
					FROM (" . implode( 'UNION ALL', array_fill( 0, count( $terms ), "
						SELECT DISTINCT u.ID AS user_id
						FROM {$wpdb->users} u
						INNER JOIN {$wpdb->usermeta} um
						ON um.user_id = u.ID
						WHERE (
							(@term := '%s') IS NOT NULL
							AND FIND_IN_SET(um.meta_key, @meta_keys)
							AND LOWER(um.meta_value) LIKE @term
						)
						OR LOWER(u.user_login) LIKE @term
						OR LOWER(u.user_nicename) LIKE @term
						OR LOWER(u.user_email) LIKE @term
						OR LOWER(u.user_url) LIKE @term
						OR LOWER(u.display_name) LIKE @term
					" ) ) . ") AS user_search_union
					GROUP BY user_id
					HAVING COUNT(*) >= %d;
				", $values ) );

				// Change query to include our new user IDs
				if ( is_array( $user_ids ) && count( $user_ids ) ) {
					$id_string = implode( ',', $user_ids );

					$extra_sql = " OR ID IN ({$id_string})";

					if ( substr( $wp_user_query->query_where, -1 ) == ')' ) {
						// Put our additional query before the closing )
						$wp_user_query->query_where = substr( $wp_user_query->query_where, 0, -1 ) . $extra_sql . substr( $wp_user_query->query_where, -1 );
					} else {
						// Put our additional query at the end
						$wp_user_query->query_where .= $extra_sql;
					}
				}
			}

			// Return the query
			return $wp_user_query;
		}

		// Get array of user search terms
		public function get_search_terms() {
			// Get the search term(s)
			$terms = trim( strtolower( stripslashes( $_GET['s'] ) ) );

			// Split terms by space into an array
			$terms = explode( ' ', $terms );

			// Remove empty terms
			foreach ( $terms as $key => $term ) {
				if ( empty( $term ) ) {
					unset( $terms[ $key ] );
				}
			}

			// Reset the array keys
			$terms = array_values( $terms );

			return $terms;
		}

		// Get user-defined meta keys
		public function get_meta_keys() {
			// Get the meta keys from the settings
			$meta_keys = get_option( 'bu_search_meta_keys', array( 'first_name', 'last_name' ) );

			// Make it an array if it isn't one already
			if ( ! is_array( $meta_keys ) ) {
				$meta_keys = ! empty( $meta_keys ) ? array( $meta_keys ) : array();
			}

			// Return the meta keys
			return $meta_keys;
		}

		// Get all searchable meta keys from the wp_usermeta table
		public function get_all_meta_keys() {
			global $wpdb;

			// Query for all meta keys from the user meta table
			return $wpdb->get_col( $sql = "
				SELECT DISTINCT meta_key
				FROM {$wpdb->usermeta}
				WHERE meta_key IS NOT NULL
				AND meta_key != ''
				ORDER BY meta_key;
			" );
		}

		// Add the options page
		public function options_page() {
			// Get the user-defined meta keys
			$meta_keys     = $this->get_meta_keys();

			// Get all of the meta keys (for our list)
			$all_meta_keys = $this->get_all_meta_keys();

			// Output the form
			?>
			<div class="wrap">
				<h2><?php _e( 'Settings: Better User Search', 'better-user-search' ); ?></h2>

				<form method="post" action="options.php">
					<?php settings_fields( 'bu-search-settings' ); ?>
					<?php do_settings_sections( 'bu-search' ); ?>

					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php _e( 'Searchable Meta Fields', 'better-user-search' ); ?></th>
							<td>
								<select data-placeholder="Choose some meta fields..." name="bu_search_meta_keys[]" multiple class="chosen-select">
									<?php foreach ( $all_meta_keys as $meta_key ): ?><option<?php selected( in_array( $meta_key, $meta_keys ), true ); ?>><?php echo esc_html( $meta_key ); ?></option><?php endforeach ?>
								</select>
								<p><a class="chosen-select-all button" href="#"><?php _e( 'Select all', 'better-user-search' ); ?></a> <a class="chosen-select-none button" href="#"><?php _e( 'Select none', 'better-user-search' ); ?></a></p>
								<p class="description"><?php printf( __( 'Use this list to configure which meta fields are searchable on the <a href="%s">Users</a> page.', 'better-user-search' ), admin_url( 'users.php' ) ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}
	}
}