<?php
/**
 * Plugin Name: MarginMinds – GST & Tax Invoice for WooCommerce
 * Plugin URI: https://marginminds.com/
 * Description: Generate GST-compliant tax invoices, CGST/SGST/IGST breakdowns, and advanced tax summaries for WooCommerce stores.
 * Version: 1.0.0
 * Author: Qubenza
 * Author URI: https://qubenza.com/
 * Text Domain: marginminds-gst
 * Domain Path: /languages/
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Marginminds
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Plugin version.
 */
if ( ! defined( 'MMSGST_VERSION' ) ) {
	define( 'MMSGST_VERSION', '1.0.0' );
}

/**
 * Plugin directory.
 */
if ( ! defined( 'MMSGST_DIR' ) ) {
	define( 'MMSGST_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Plugin URL.
 */
if ( ! defined( 'MMSGST_URL' ) ) {
	define( 'MMSGST_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Composer autoloader (preferred).
 */
$marginminds_gst_autoloader = MMSGST_DIR . 'vendor/autoload.php';
if ( file_exists( $marginminds_gst_autoloader ) ) {
	require_once $marginminds_gst_autoloader;
} else {
	/**
	 * Fallback PSR-4 autoloader for the plugin namespace.
	 *
	 * This will only be used when Composer's autoloader is not available.
	 *
	 * @param string $class_name Fully-qualified class name.
	 *
	 * @return void
	 */
	spl_autoload_register(
		function ( $class_name ) {
			$prefix = 'Marginminds\\Gst\\';

			/* Only handle classes in our namespace. */
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}

			// Strip the namespace prefix.
			$relative = substr( $class_name, strlen( $prefix ) );
			$parts    = explode( '\\', $relative );

			// bail early.
			if ( empty( $parts ) ) {
				return;
			}

			// Extract the class segment (last part).
			$class_segment = array_pop( $parts );

			// Map intermediate namespace parts to folder names (lowercase).
			$folders = array_map(
				function ( $p ) {
					return strtolower( $p );
				},
				$parts
			);

			// Convert class segment to kebab-case filename: - replace underscores with hyphen - lower-case - prefix with "class-".
			$filename = 'class-' . str_replace( '_', '-', strtolower( $class_segment ) ) . '.php';
			$path     = MMSGST_DIR . 'src/';
			if ( ! empty( $folders ) ) {
				$path .= implode( '/', $folders ) . '/';
			}
			$path .= $filename;
			// Require if file exists.
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	);
}

/**
 * Show an admin notice when WooCommerce is not active.
 *
 * @return void
 */
function marginminds_gst_missing_woocommerce_notice(): void {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Marginminds GST requires WooCommerce to be installed and active.', 'marginminds-gst' ) .
		'</p></div>';
}

/**
 * Initiate the plugin.
 *
 * @return void
 */
function marginminds_gst_bootstrap(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'marginminds_gst_missing_woocommerce_notice' );
		return;
	}

	if ( class_exists( 'Marginminds\\Gst\\Plugin' ) ) {
		Marginminds\Gst\Plugin::instance()->init();
	}
}

add_action( 'plugins_loaded', 'marginminds_gst_bootstrap', 10 );
