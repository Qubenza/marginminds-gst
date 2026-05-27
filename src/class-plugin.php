<?php
/**
 * Main plugin controller class.
 *
 * @package Marginminds
 */

namespace Gst\Marginminds;

defined( 'ABSPATH' ) || exit;

/**
 * Main Plugin class.
 *
 * This class bootstraps all plugin components:
 *  - admin pages
 *  - AJAX endpoints
 *  - GST tax manager
 *  - checkout GSTIN field
 *  - order GST meta
 *  - frontend display
 *  - invoice generation and delivery
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Init plugin — called on `plugins_loaded`.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_admin_page();
		$this->register_ajax_endpoints();
		$this->register_tax_manager();
		$this->register_checkout_fields();
		$this->register_order_meta();
		$this->register_display();
		$this->register_invoice_manager();
		$this->register_cart_block_integration();
		$this->register_frontend_assets();
	}

	/**
	 * Register admin page.
	 *
	 * @return void
	 */
	private function register_admin_page(): void {
		$admin_class = 'Gst\\Marginminds\\Tax_Admin\\Admin_Page';
		if ( class_exists( $admin_class ) ) {
			$admin_page = new $admin_class();
			$admin_page->register();
		}
	}

	/**
	 * Register AJAX endpoints.
	 *
	 * All AJAX logic will be in dedicated endpoint class.
	 *
	 * @return void
	 */
	private function register_ajax_endpoints(): void {
		$ajax_class = 'Gst\\Marginminds\\Tax_Admin\\Ajax_Endpoint';

		if ( class_exists( $ajax_class ) ) {
			$ajax = new $ajax_class();
			$ajax->register();
		}
	}

	/**
	 * Register GST tax manager.
	 *
	 * @return void
	 */
	private function register_tax_manager(): void {
		$manager_class = 'Gst\\Marginminds\\GST\\Tax_Manager';

		if ( class_exists( $manager_class ) ) {
			$manager = new $manager_class();
			$manager->register();
		}
	}

	/**
	 * Register checkout GSTIN field.
	 *
	 * @return void
	 */
	private function register_checkout_fields(): void {
		$fields_class = 'Gst\\Marginminds\\Checkout\\Checkout_Fields';

		if ( class_exists( $fields_class ) ) {
			$fields = new $fields_class();
			$fields->register();
		}
	}

	/**
	 * Register order GST meta handler.
	 *
	 * @return void
	 */
	private function register_order_meta(): void {
		$meta_class = 'Gst\\Marginminds\\Orders\\Order_Meta';

		if ( class_exists( $meta_class ) ) {
			$meta = new $meta_class();
			$meta->register();
		}
	}

	/**
	 * Register frontend GST display.
	 *
	 * @return void
	 */
	private function register_display(): void {
		$display_class = 'Gst\\Marginminds\\Frontend\\Display';

		if ( class_exists( $display_class ) ) {
			$display = new $display_class();
			$display->register();
		}
	}

	/**
	 * Register invoice manager (URL routing, customer links, admin link).
	 *
	 * @return void
	 */
	private function register_invoice_manager(): void {
		$invoice_class = 'Gst\\Marginminds\\Invoice\\Invoice_Manager';

		if ( class_exists( $invoice_class ) ) {
			$invoice = new $invoice_class();
			$invoice->register();
		}
	}

	/**
	 * Register the WooCommerce Blocks cart integration (Store API extension).
	 *
	 * @return void
	 */
	private function register_cart_block_integration(): void {
		$integration_class = 'Gst\\Marginminds\\Blocks\\Cart_Block_Integration';

		if ( class_exists( $integration_class ) ) {
			$integration = new $integration_class();
			$integration->register();
		}
	}

	/**
	 * Enqueue the frontend stylesheet on all non-admin pages.
	 *
	 * @return void
	 */
	private function register_frontend_assets(): void {
		add_action(
			'wp_enqueue_scripts',
			static function () {
				wp_enqueue_style(
					'gst-marginminds-frontend',
					GST_MM_URL . 'assets/css/gst-marginminds-frontend.css',
					array(),
					defined( 'GST_MM_VERSION' ) ? GST_MM_VERSION : false
				);
			}
		);
	}
}
