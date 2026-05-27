<?php
/**
 * Invoice Generator — builds the HTML invoice for a WooCommerce order.
 *
 * @package Marginminds
 */

namespace Marginminds\Gst\Invoice;

use Marginminds\Gst\Checkout\Checkout_Fields;
use Marginminds\Gst\GST\Tax_Manager;
use Marginminds\Gst\Orders\Order_Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and delivers the HTML invoice document.
 *
 * Responsibilities:
 *  - Compute the invoice number from the configured prefix + order ID
 *  - Build the secure invoice URL (order key as auth token)
 *  - Render the full invoice HTML via the invoice template
 *
 * The rendered HTML is a self-contained document suitable for browser
 * printing or saving as PDF via the browser's native print dialog.
 */
class Invoice_Generator {

	/**
	 * Merged plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Order_Meta reader instance.
	 *
	 * @var Order_Meta
	 */
	private $order_meta;

	/**
	 * Checkout_Fields instance for reading the customer GSTIN.
	 *
	 * @var Checkout_Fields
	 */
	private $checkout_fields;

	/**
	 * Tax_Manager instance for state code resolution.
	 *
	 * @var Tax_Manager
	 */
	private $tax_manager;

	/**
	 * Constructor — creates dependencies without registering any hooks.
	 */
	public function __construct() {
		$this->tax_manager     = new Tax_Manager();
		$this->settings        = $this->tax_manager->get_settings();
		$this->order_meta      = new Order_Meta();
		$this->checkout_fields = new Checkout_Fields();
	}

	/**
	 * Return the invoice number for an order.
	 *
	 * Format: {prefix}{order_id}, e.g. "INV-1234".
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return string
	 */
	public function get_invoice_number( \WC_Order $order ): string {
		$prefix = '' !== $this->settings['invoice_prefix'] ? $this->settings['invoice_prefix'] : 'INV-';
		return $prefix . $order->get_id();
	}

	/**
	 * Return the secure URL for viewing/downloading the invoice.
	 *
	 * The order key acts as the auth token so no login is required for
	 * guest customers who follow the link from their confirmation email.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return string
	 */
	public function get_invoice_url( \WC_Order $order ): string {
		return add_query_arg(
			array(
				'gstmarginminds_invoice' => $order->get_id(),
				'key'               => $order->get_order_key(),
			),
			home_url( '/' )
		);
	}

	/**
	 * Return the secure URL that triggers a file download of the invoice.
	 *
	 * Identical to get_invoice_url() but adds download=1 so the server
	 * sends Content-Disposition: attachment.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return string
	 */
	public function get_download_url( \WC_Order $order ): string {
		return add_query_arg(
			array(
				'gstmarginminds_invoice' => $order->get_id(),
				'key'               => $order->get_order_key(),
				'download'          => '1',
			),
			home_url( '/' )
		);
	}

	/**
	 * Render the full invoice HTML document for an order.
	 *
	 * Uses output buffering so the template can use plain PHP/HTML without
	 * returning a string.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return string Complete HTML document.
	 */
	public function render( \WC_Order $order ): string {
		// Register the invoice stylesheet so the template can output it via
		// wp_styles()->do_items() without going through wp_head().
		wp_register_style(
			'gst-mm-invoice',
			GST_MM_URL . 'views/invoice/invoice.css',
			array(),
			GST_MM_VERSION
		);
		wp_enqueue_style( 'gst-mm-invoice' );

		// Variables made available inside the template.
		$settings            = $this->settings;
		$breakdown           = $this->order_meta->get_breakdown( $order );
		$customer_gstin      = $this->checkout_fields->get_order_gstin( $order );
		$invoice_number      = $this->get_invoice_number( $order );
		$business_state_name = $this->tax_manager->state_name_from_code( $settings['business_state'] );

		ob_start();
		include GST_MM_DIR . 'views/invoice/invoice-template.php';
		return (string) ob_get_clean();
	}
}
