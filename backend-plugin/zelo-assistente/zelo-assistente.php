<?php
/**
 * Plugin Name: Zelo Assistente
 * Description: Backend plugin for Zelo PWA. Manages Locations and Event Info.
 * Version: 2.9.3
 * Author: Zelo Team
 * Text Domain: zelo-assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'ZELO_VERSION', '2.9.3' );
define( 'ZELO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Include required files
require_once ZELO_PLUGIN_DIR . 'inc/categories.php';
require_once ZELO_PLUGIN_DIR . 'inc/post-types.php';
require_once ZELO_PLUGIN_DIR . 'inc/meta-boxes.php';
require_once ZELO_PLUGIN_DIR . 'inc/volunteer-ops-catalogs.php';
require_once ZELO_PLUGIN_DIR . 'inc/volunteer-ops.php';
require_once ZELO_PLUGIN_DIR . 'inc/volunteer-ops-export.php';
require_once ZELO_PLUGIN_DIR . 'inc/zelo-volunteer-registration.php';
require_once ZELO_PLUGIN_DIR . 'inc/weather.php';
require_once ZELO_PLUGIN_DIR . 'inc/api-routes.php';
require_once ZELO_PLUGIN_DIR . 'inc/zelo-auth-api.php'; // Auth API
require_once ZELO_PLUGIN_DIR . 'inc/zelo-volunteer-commitments.php';
require_once ZELO_PLUGIN_DIR . 'inc/zelo-volunteer-link-requests.php';
require_once ZELO_PLUGIN_DIR . 'inc/zelo-volunteer-notifications.php';
require_once ZELO_PLUGIN_DIR . 'inc/zelo-volunteer-swaps.php';
require_once ZELO_PLUGIN_DIR . 'inc/volunteer-ops-admin-ui.php';
if ( is_admin() ) {
	require_once ZELO_PLUGIN_DIR . 'inc/admin-categories.php';
	require_once ZELO_PLUGIN_DIR . 'inc/admin-settings.php';
	require_once ZELO_PLUGIN_DIR . 'inc/admin-importer.php';
	require_once ZELO_PLUGIN_DIR . 'inc/admin-importer-csv.php';
	require_once ZELO_PLUGIN_DIR . 'inc/admin-importer-csv-cnes.php';
	require_once ZELO_PLUGIN_DIR . 'inc/admin-importer-places.php';
}
