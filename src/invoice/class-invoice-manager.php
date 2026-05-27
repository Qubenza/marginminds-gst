<?php
/**
 * Invoice Manager — routes invoice requests and injects invoice links.
 *
 * @package Marginminds
 */

namespace Marginminds\Gst\Invoice;

use Marginminds\Gst\GST\Tax_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Handles all invoice delivery touchpoints.
 *
 * Responsibilities:
 *  - Intercept ?gstmarginminds_invoice=N&key=K requests and serve the HTML invoice
 *  - Add a "Download Invoice" link on the customer order details page
 *  - Optionally add the invoice link inside order emails
 *  - Add a "View Invoice" link in the admin order billing block
 *
 * Security: the order key is required on every invoice request. Logged-in
 * non-admin users are additionally checked against the order's customer ID.
 */
class Invoice_Manager {

	/**
	 * Invoice generator instance.
	 *
	 * @var Invoice_Generator
	 */
	private $generator;

	/**
	 * Merged plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor — creates generator, loads settings.
	 */
	public function __construct() {
		$this->generator = new Invoice_Generator();
		$tax_manager     = new Tax_Manager();
		$this->settings  = $tax_manager->get_settings();
	}

	/**
	 * Register all invoice hooks.
	 *
	 * Bails when the master GST switch or the PDF invoices switch is off.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->settings['enable_gst'] ) {
			return;
		}

		if ( ! $this->settings['enable_pdf_invoices'] ) {
			return;
		}

		// Serve the invoice HTML when the request URL contains our query arg.
		add_action( 'template_redirect', array( $this, 'handle_invoice_request' ) );

		// Customer-facing invoice download link on order details / thank-you page.
		// Priority 30 — after Checkout_Fields GSTIN (10) and Display GST summary (20).
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_customer_invoice_link' ), 30, 1 );

		// Add invoice link inside order emails when the setting is on.
		if ( $this->settings['attach_invoice_to_emails'] ) {
			add_action( 'woocommerce_email_after_order_table', array( $this, 'render_invoice_link_in_email' ), 30, 4 );
		}

		// Admin order billing block — "View Invoice" link.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_admin_invoice_link' ), 10, 1 );
	}

	// -------------------------------------------------------------------------
	// Hook callbacks — public (required by add_action)
	// -------------------------------------------------------------------------

	/**
	 * Intercept invoice requests and output the HTML invoice document.
	 *
	 * Reads ?gstmarginminds_invoice and ?key from the request. Validates the order
	 * key, verifies the current user is allowed to view the invoice, then
	 * renders and exits.
	 *
	 * @return void
	 */
	public function handle_invoice_request(): void {
		if ( ! isset( $_GET['gstmarginminds_invoice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only invoice view; order key is the auth token
			return;
		}

		$order_id = absint( $_GET['gstmarginminds_invoice'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $order_id || '' === $key ) {
			wp_die( esc_html__( 'Invalid invoice request.', 'marginminds-gst' ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'marginminds-gst' ) );
		}

		// Order key is the primary auth token (covers guest customers).
		if ( ! hash_equals( $order->get_order_key(), $key ) ) {
			wp_die( esc_html__( 'Invalid order key.', 'marginminds-gst' ) );
		}

		// Logged-in non-admin users may only view their own orders.
		if ( is_user_logged_in() && ! current_user_can( 'edit_shop_orders' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- edit_shop_orders is a valid WooCommerce capability
			if ( (int) get_current_user_id() !== (int) $order->get_customer_id() ) {
				wp_die( esc_html__( 'You do not have permission to view this invoice.', 'marginminds-gst' ) );
			}
		}

		$html = $this->generator->render( $order );

		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );

		if ( isset( $_GET['download'] ) && '1' === $_GET['download'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filename = 'invoice-' . $this->generator->get_invoice_number( $order ) . '.html';
			header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fully escaped inside render()
		exit;
	}

	/**
	 * Render a "Download Invoice" link on the customer order details page.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return void
	 */
	public function render_customer_invoice_link( \WC_Order $order ): void {
		?>
		<p class="marginminds-invoice-download">
			<a href="<?php echo esc_url( $this->generator->get_invoice_url( $order ) ); ?>"
				target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Download Invoice', 'marginminds-gst' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Add an invoice download link inside a WooCommerce order email.
	 *
	 * Fires after the order table in customer-facing emails. The link uses
	 * the same secure URL as the order details page link.
	 *
	 * @param \WC_Order $order         WooCommerce order.
	 * @param bool      $sent_to_admin True when the email goes to the store admin.
	 * @param bool      $plain_text    True for plain-text email format.
	 * @param \WC_Email $email         The WooCommerce email object.
	 * @return void
	 */
	public function render_invoice_link_in_email( \WC_Order $order, bool $sent_to_admin, bool $plain_text, \WC_Email $email ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$url = $this->generator->get_invoice_url( $order );

		if ( $plain_text ) {
			/* translators: %s: Invoice download URL */
			echo "\n" . esc_html( sprintf( __( 'Download Invoice: %s', 'marginminds-gst' ), $url ) ) . "\n";
			return;
		}
		?>
		<p style="margin:16px 0;font-family:Arial,sans-serif;font-size:14px;">
			<a href="<?php echo esc_url( $url ); ?>"
				style="color:#96588a;text-decoration:underline;"
				target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Download Invoice', 'marginminds-gst' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Add a "View Invoice" link inside the admin order billing address block.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return void
	 */
	public function render_admin_invoice_link( \WC_Order $order ): void {
		?>
		<p>
			<a href="<?php echo esc_url( $this->generator->get_invoice_url( $order ) ); ?>"
				target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View Invoice', 'marginminds-gst' ); ?>
			</a>
		</p>
		<?php
	}
}
