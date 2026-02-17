<?php
/**
 * Plugin Name: Zelo Assistente
 * Description: Backend plugin for Zelo PWA. Manages Locations and Event Info.
 * Version: 1.0.0
 * Author: Zelo Team
 * Text Domain: zelo-assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'ZELO_VERSION', '1.0.0' );
define( 'ZELO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Include required files
require_once ZELO_PLUGIN_DIR . 'inc/post-types.php';
require_once ZELO_PLUGIN_DIR . 'inc/meta-boxes.php';
require_once ZELO_PLUGIN_DIR . 'inc/api-routes.php';
if ( is_admin() ) {
	require_once ZELO_PLUGIN_DIR . 'inc/admin-settings.php';
	require_once ZELO_PLUGIN_DIR . 'inc/admin-importer.php';
}
