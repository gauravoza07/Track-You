<?php
/**
 * Plugin Name: Track You - User Activity Logger
 * Plugin URI: https://wordpress.org/plugins/user-activity-tracker/
 * Description: Tracks user activities including image uploads and ACF field changes with IP address logging
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: Gaurav Oza
 * Author URI: https://profiles.wordpress.org/gaurav-oza/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: track-you-user-activity-logger
 * Domain Path: /languages
 *
 * @package UserActivityTracker
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'UAT_VERSION', '1.0.0' );
define( 'UAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once UAT_PLUGIN_DIR . 'includes/class-user-activity-tracker-logger.php';
require_once UAT_PLUGIN_DIR . 'includes/class-user-activity-tracker-admin.php';

/**
 * Initialize the plugin.
 *
 * @return void
 */
function uat_init() {
	// Initialize activity logger
	new User_Activity_Tracker_Logger();
	
	// Initialize admin interface
	if ( is_admin() ) {
		new User_Activity_Tracker_Admin();
	}

	// Translations are auto-loaded by WordPress.org; no manual load needed.
}
add_action( 'plugins_loaded', 'uat_init' );

/**
 * Create necessary database tables.
 *
 * @return void
 */
function uat_create_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'user_activity_logs';

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		user_id bigint(20) NOT NULL,
		activity_type varchar(50) NOT NULL,
		activity_details text NOT NULL,
		ip_address varchar(45) NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

/**
 * Plugin activation hook.
 */
register_activation_hook( __FILE__, 'uat_activate' );

/**
 * Plugin activation function.
 *
 * @return void
 */
function uat_activate() {
	// Create database tables
	uat_create_tables();

	// Add plugin version to options
	add_option( 'uat_version', UAT_VERSION );

	// Flush rewrite rules
	flush_rewrite_rules();
}

/**
 * Plugin deactivation hook.
 */
register_deactivation_hook( __FILE__, 'uat_deactivate' );

/**
 * Plugin deactivation function.
 *
 * @return void
 */
function uat_deactivate() {
	// Flush rewrite rules
	flush_rewrite_rules();
} 