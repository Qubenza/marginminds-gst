<?php
/**
 * Checkout Fields — GSTIN field at WooCommerce checkout.
 *
 * @package Marginminds
 */

namespace Gst\Marginminds\Checkout;

use Gst\Marginminds\Tax_Admin\Ajax_Endpoint;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a GSTIN input field to the WooCommerce checkout billing section,
 * validates the format on submit, saves the value to the order, and
 * displays it on the thank-you page and admin order screen.
 *
 * Controlled by three settings:
 *  - show_gstin_at_checkout  : master switch for this feature
 *  - gstin_field_required    : makes the field mandatory
 *  - save_gstin_to_orders    : persist GSTIN in order meta
 */
class Checkout_Fields {

	/**
	 * Order meta key used to store the customer GSTIN.
	 */
	const META_KEY = '_billing_gstin';

	/**
	 * Merged plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor — load settings once.
	 */
	public function __construct() {
		$this->settings = Ajax_Endpoint::get_settings();
	}

	/**
	 * WooCommerce Blocks stores additional billing address fields under this meta key prefix.
	 */
	const BLOCKS_META_KEY = '_wc_billing/marginminds/billing_gstin';

	/**
	 * Register all hooks.
	 *
	 * Bails early when show_gstin_at_checkout is off.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->settings['show_gstin_at_checkout'] ) {
			return;
		}

		// Classic checkout: add field, validate, and optionally save.
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_gstin_field' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_gstin_field' ) );

		if ( $this->settings['save_gstin_to_orders'] ) {
			add_action( 'woocommerce_checkout_create_order', array( $this, 'save_gstin_to_order' ), 10, 2 );
		}

		// Blocks checkout: defer to init so __() calls happen after textdomain loads.
		add_action( 'init', array( $this, 'register_blocks_field' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'save_blocks_gstin_to_order' ), 10, 2 );

		// Front-end order details / thank-you page.
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_on_order_page' ), 10, 1 );

		// Admin order screen — below billing address block.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_in_admin' ), 10, 1 );
	}

	/**
	 * Register the GSTIN field for the WooCommerce Blocks checkout.
	 *
	 * Uses the official WC 8.6+ additional checkout field API so the field
	 * appears in the Blocks-powered checkout alongside the standard billing
	 * address fields.
	 *
	 * @return void
	 */
	public function register_blocks_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => 'marginminds/billing_gstin',
				'label'             => __( 'GSTIN', 'marginminds-gst' ),
				'optionalLabel'     => __( 'GSTIN (optional)', 'marginminds-gst' ),
				'location'          => 'address',
				'type'              => 'text',
				'required'          => (bool) $this->settings['gstin_field_required'],
				'attributes'        => array(
					'maxLength' => 15,
				),
				'sanitize_callback' => array( $this, 'sanitize_blocks_gstin' ),
				'validate_callback' => array( $this, 'validate_blocks_gstin' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Checkout hooks
	// -------------------------------------------------------------------------

	/**
	 * Inject the GSTIN field into WooCommerce billing fields.
	 *
	 * Priority 110 places it after the standard billing fields (which end at 100).
	 *
	 * @param array $fields All checkout field groups.
	 * @return array
	 */
	public function add_gstin_field( array $fields ): array {
		$fields['billing']['billing_gstin'] = array(
			'type'        => 'text',
			'label'       => __( 'GSTIN', 'marginminds-gst' ),
			'placeholder' => __( 'e.g. 33ABCDE1234F1Z5', 'marginminds-gst' ),
			'required'    => (bool) $this->settings['gstin_field_required'],
			'class'       => array( 'form-row-wide' ),
			'clear'       => true,
			'priority'    => 110,
		);
		return $fields;
	}

	/**
	 * Validate the GSTIN field during checkout submission.
	 *
	 * Adds a WooCommerce checkout error notice when:
	 *  - the field is required and empty, or
	 *  - a value was entered but does not match the GSTIN format.
	 *
	 * @return void
	 */
	public function validate_gstin_field(): void {
		$gstin = isset( $_POST['billing_gstin'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_gstin'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before this action fires.

		if ( '' === $gstin ) {
			if ( $this->settings['gstin_field_required'] ) {
				wc_add_notice(
					__( 'Please enter your GSTIN.', 'marginminds-gst' ),
					'error'
				);
			}
			return;
		}

		if ( ! Ajax_Endpoint::validate_gstin( $gstin ) ) {
			wc_add_notice(
				__( 'The GSTIN you entered is not valid. Please check the format and try again.', 'marginminds-gst' ),
				'error'
			);
		}
	}

	/**
	 * Save the GSTIN value to the WooCommerce order meta.
	 *
	 * Fires during order creation. Uses the $data array that WooCommerce
	 * built from get_posted_data() so we avoid reading $_POST directly.
	 *
	 * @param \WC_Order $order WooCommerce order being created.
	 * @param array     $data  Processed checkout POST data.
	 * @return void
	 */
	public function save_gstin_to_order( \WC_Order $order, array $data ): void {
		$gstin = isset( $data['billing_gstin'] )
			? sanitize_text_field( $data['billing_gstin'] )
			: '';

		if ( '' !== $gstin ) {
			$order->update_meta_data( self::META_KEY, strtoupper( $gstin ) );
		}
	}

	// -------------------------------------------------------------------------
	// Blocks checkout hooks
	// -------------------------------------------------------------------------

	/**
	 * Sanitize the GSTIN value submitted via the Blocks checkout.
	 *
	 * @param string $value Raw value from the REST request.
	 * @param array  $field Field definition (unused).
	 * @return string
	 */
	public function sanitize_blocks_gstin( string $value, array $field ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return strtoupper( sanitize_text_field( $value ) );
	}

	/**
	 * Validate the GSTIN value submitted via the Blocks checkout.
	 *
	 * Returns a WP_Error when the value is non-empty but fails the GSTIN
	 * format check so WooCommerce surfaces the error in the checkout UI.
	 *
	 * @param string $value Sanitized value from the REST request.
	 * @param array  $field Field definition (unused).
	 * @return bool|\WP_Error
	 */
	public function validate_blocks_gstin( string $value, array $field ): bool|\WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( '' === $value ) {
			return true;
		}

		if ( ! Ajax_Endpoint::validate_gstin( $value ) ) {
			return new \WP_Error(
				'invalid_gstin',
				__( 'The GSTIN you entered is not valid. Please check the format and try again.', 'marginminds-gst' )
			);
		}

		return true;
	}

	/**
	 * Copy the GSTIN saved by the Blocks checkout into our canonical meta key.
	 *
	 * Fires after WooCommerce Blocks has already persisted billing address
	 * additional fields (including marginminds/billing_gstin) onto the order,
	 * so get_meta() is safe to call here.
	 *
	 * @param \WC_Order        $order   WooCommerce order.
	 * @param \WP_REST_Request $request Full REST request (unused).
	 * @return void
	 */
	public function save_blocks_gstin_to_order( \WC_Order $order, \WP_REST_Request $request ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$gstin = $order->get_meta( self::BLOCKS_META_KEY, true );

		if ( ! is_string( $gstin ) || '' === $gstin ) {
			return;
		}

		$order->update_meta_data( self::META_KEY, strtoupper( $gstin ) );
	}

	// -------------------------------------------------------------------------
	// Display hooks
	// -------------------------------------------------------------------------

	/**
	 * Show the GSTIN on the front-end order details / thank-you page.
	 *
	 * Only renders if a GSTIN was saved to the order.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return void
	 */
	public function display_on_order_page( \WC_Order $order ): void {
		$gstin = $this->get_order_gstin( $order );
		if ( '' === $gstin ) {
			return;
		}
		?>
		<section class="woocommerce-customer-details marginminds-gstin-details">
			<h2><?php esc_html_e( 'GST Details', 'marginminds-gst' ); ?></h2>
			<table class="woocommerce-table shop_table">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'GSTIN', 'marginminds-gst' ); ?></th>
						<td><?php echo esc_html( $gstin ); ?></td>
					</tr>
				</tbody>
			</table>
		</section>
		<?php
	}

	/**
	 * Show the GSTIN inside the admin order billing address block.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return void
	 */
	public function display_in_admin( \WC_Order $order ): void {
		$gstin = $this->get_order_gstin( $order );
		if ( '' === $gstin ) {
			return;
		}
		echo '<p><strong>' . esc_html__( 'GSTIN', 'marginminds-gst' ) . ':</strong> ' . esc_html( $gstin ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Public helper
	// -------------------------------------------------------------------------

	/**
	 * Retrieve the GSTIN stored on a WooCommerce order.
	 *
	 * Checks the canonical meta key first, then falls back to the key that
	 * WooCommerce Blocks writes for additional billing address fields.  This
	 * means GSTIN is always readable regardless of whether the order was
	 * placed through the classic or Blocks checkout.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return string
	 */
	public function get_order_gstin( \WC_Order $order ): string {
		$gstin = $order->get_meta( self::META_KEY, true );

		if ( ! is_string( $gstin ) || '' === $gstin ) {
			$gstin = $order->get_meta( self::BLOCKS_META_KEY, true );
		}

		return is_string( $gstin ) ? $gstin : '';
	}
}
