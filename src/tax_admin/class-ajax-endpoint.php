<?php
/**
 * AJAX endpoint for returning for all data in admin
 *
 * @package Marginminds
 */

namespace Gst\Marginminds\Tax_Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page handler.
 */
class Ajax_Endpoint {

	/**
	 * AJAX action name for saving settings.
	 */
	public const SAVE_ACTION = 'gst_marginminds_save_settings';

	/**
	 * WordPress option key for plugin settings.
	 */
	private const OPTION_KEY = 'gst_marginminds_settings';

	/**
	 * Register hooks for AJAX.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::SAVE_ACTION, array( $this, 'handle_save' ) );
	}


	/**
	 * Handle the save-settings AJAX request.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! check_ajax_referer( 'gst_marginminds_form_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'marginminds-gst' ) ), 403 );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to save settings.', 'marginminds-gst' ) ), 403 );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-field inside sanitize_settings() after decoding
		$raw = isset( $_POST['settings'] ) ? json_decode( wp_unslash( $_POST['settings'] ), true ) : array();

		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid settings data.', 'marginminds-gst' ) ), 400 );
			return;
		}

		$existing = self::get_settings();
		$settings = $this->sanitize_settings( $raw );
		update_option( self::OPTION_KEY, $settings );

		// Sync WooCommerce "Prices entered with tax" only when prices_include_gst changes.
		if ( $settings['prices_include_gst'] !== $existing['prices_include_gst'] ) {
			update_option( 'woocommerce_prices_include_tax', $settings['prices_include_gst'] ? 'yes' : 'no' );
		}

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'marginminds-gst' ) ) );
	}

	/**
	 * Sanitize raw settings from the request.
	 *
	 * @param array $raw Raw settings array.
	 * @return array Sanitized settings.
	 */
	private function sanitize_settings( array $raw ): array {
		$bool_keys = array(
			'enable_gst',
			'enable_cgst_sgst',
			'enable_igst',
			'prices_include_gst',
			'apply_gst_based_on_shipping_state',
			'round_tax_amounts',
			'show_gstin_at_checkout',
			'gstin_field_required',
			'save_gstin_to_orders',
			'display_gst_in_order_emails',
			'enable_gst_on_shipping',
			'display_gst_below_product_price',
			'enable_pdf_invoices',
			'show_gstin_on_invoice',
			'attach_invoice_to_emails',
			'show_gst_on_cart',
			'show_gst_on_checkout',
			'show_gst_in_order_summary',
		);

		$text_keys = array(
			'business_legal_name',
			'store_gstin',
			'invoice_prefix',
			'business_state',
			'default_gst_rate',
		);

		$textarea_keys = array(
			'business_address',
			'invoice_footer_text',
		);

		$settings = array();

		foreach ( $bool_keys as $key ) {
			$settings[ $key ] = ! empty( $raw[ $key ] );
		}

		foreach ( $text_keys as $key ) {
			$settings[ $key ] = isset( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : '';
		}

		foreach ( $textarea_keys as $key ) {
			$settings[ $key ] = isset( $raw[ $key ] ) ? sanitize_textarea_field( $raw[ $key ] ) : '';
		}

		return $settings;
	}

	/**
	 * Validate a GSTIN number format.
	 *
	 * Format: 2-digit state code + 5 alpha + 4 digit + 1 alpha + 1 alphanumeric + Z + 1 alphanumeric
	 *
	 * @param string $gstin The GSTIN to validate.
	 * @return bool True if valid format, false otherwise.
	 */
	public static function validate_gstin( string $gstin ): bool {
		$pattern = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';
		return (bool) preg_match( $pattern, strtoupper( $gstin ) );
	}

	/**
	 * Get all saved settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		$defaults = array(
			'enable_gst'                        => false,
			'business_legal_name'               => '',
			'store_gstin'                       => '',
			'business_address'                  => '',
			'business_state'                    => '',
			'enable_cgst_sgst'                  => false,
			'enable_igst'                       => false,
			'default_gst_rate'                  => '',
			'prices_include_gst'                => 'yes' === get_option( 'woocommerce_prices_include_tax', 'no' ),
			'apply_gst_based_on_shipping_state' => false,
			'round_tax_amounts'                 => false,
			'show_gstin_at_checkout'            => false,
			'gstin_field_required'              => false,
			'save_gstin_to_orders'              => false,
			'display_gst_in_order_emails'       => false,
			'enable_gst_on_shipping'            => false,
			'display_gst_below_product_price'   => false,
			'enable_pdf_invoices'               => false,
			'invoice_prefix'                    => 'INV-',
			'show_gstin_on_invoice'             => false,
			'attach_invoice_to_emails'          => false,
			'invoice_footer_text'               => '',
			'show_gst_on_cart'                  => false,
			'show_gst_on_checkout'              => false,
			'show_gst_in_order_summary'         => false,
		);

		$saved = get_option( self::OPTION_KEY, array() );

		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}
}
