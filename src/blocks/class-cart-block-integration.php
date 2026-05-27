<?php
/**
 * WooCommerce Blocks cart integration — exposes live GST breakdown via Store API.
 *
 * @package Marginminds
 */

namespace Marginminds\Gst\Blocks;

use Marginminds\Gst\GST\Tax_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a Store API extension that adds GST breakdown data to the cart
 * response under `extensions.marginminds`. The JS slot-fill reads this to render
 * IGST / CGST+SGST rows before the order total in the Blocks cart.
 */
class Cart_Block_Integration {

	/**
	 * Tax Manager.
	 *
	 * @var Tax_Manager
	 */
	private $tax_manager;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor — loads settings once.
	 */
	public function __construct() {
		$this->tax_manager = new Tax_Manager();
		$this->settings    = $this->tax_manager->get_settings();
	}

	/**
	 * Hook into woocommerce_blocks_loaded to register the Store API extension
	 * and enqueue the slot-fill script on cart/checkout pages.
	 * Bails if GST is disabled in settings.
	 */
	public function register(): void {
		if ( ! $this->settings['enable_gst'] ) {
			return;
		}
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_api_extension' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
	}

	/**
	 * Enqueue the blocks slot-fill script on cart and checkout pages only.
	 */
	public function enqueue_scripts(): void {
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		$version = defined( 'GST_MM_VERSION' ) ? GST_MM_VERSION : false;

		wp_enqueue_script(
			'marginminds-cart',
			GST_MM_URL . 'assets/js/marginminds_front.js',
			array(
				'wp-plugins',
				'wp-element',
				'wp-data',
				'wc-blocks-data-store',
				'wc-blocks-checkout',
				'wc-blocks-components',
				'wc-price-format',
			),
			$version,
			true
		);

		wp_localize_script(
			'marginminds-cart',
			'gstmarginmindsSettings',
			array(
				'showOnCart'     => ! empty( $this->settings['show_gst_on_cart'] ),
				'showOnCheckout' => ! empty( $this->settings['show_gst_on_checkout'] ),
				'isCart'         => is_cart(),
				'isCheckout'     => is_checkout(),
			)
		);
	}

	/**
	 * Register the endpoint data extension with the WooCommerce Store API.
	 */
	public function register_api_extension(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
				'namespace'       => 'marginminds',
				'data_callback'   => array( $this, 'get_gst_data' ),
				'schema_callback' => array( $this, 'get_gst_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Return the current cart's GST breakdown.
	 * All monetary amounts are integers in the currency's minor unit (e.g. paise).
	 *
	 * @return array
	 */
	public function get_gst_data(): array {
		if ( ! isset( WC()->cart ) || WC()->cart->is_empty() ) {
			return $this->empty_data();
		}

		if ( 'incl' === get_option( 'woocommerce_tax_display_cart' ) ) {
			return $this->empty_data();
		}

		$item_taxes = (array) WC()->cart->get_taxes();
		$minor      = (int) pow( 10, wc_get_price_decimals() );

		$cgst  = isset( $item_taxes[ Tax_Manager::KEY_CGST ] ) ? $item_taxes[ Tax_Manager::KEY_CGST ] : 0.0;
		$sgst  = isset( $item_taxes[ Tax_Manager::KEY_SGST ] ) ? $item_taxes[ Tax_Manager::KEY_SGST ] : 0.0;
		$igst  = isset( $item_taxes[ Tax_Manager::KEY_IGST ] ) ? $item_taxes[ Tax_Manager::KEY_IGST ] : 0.0;
		$total = $cgst + $sgst + $igst;
		$rate  = (float) $this->tax_manager->parse_rate();

		return array(
			'cgst'     => (int) round( $cgst * $minor ),
			'sgst'     => (int) round( $sgst * $minor ),
			'igst'     => (int) round( $igst * $minor ),
			'total'    => (int) round( $total * $minor ),
			'rate'     => $rate,
			'tax_type' => ( $cgst > 0 || $sgst > 0 ) ? 'cgst_sgst' : 'igst',
		);
	}

	/**
	 * JSON schema for the GST extension data.
	 *
	 * @return array
	 */
	public function get_gst_schema(): array {
		return array(
			'cgst'     => array(
				'description' => 'CGST amount in minor currency units',
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
			),
			'sgst'     => array(
				'description' => 'SGST amount in minor currency units',
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
			),
			'igst'     => array(
				'description' => 'IGST amount in minor currency units',
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
			),
			'total'    => array(
				'description' => 'Total GST in minor currency units',
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
			),
			'rate'     => array(
				'description' => 'GST rate as a percentage',
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
			),
			'tax_type' => array(
				'description' => 'Tax split type: "igst" or "cgst_sgst"',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
		);
	}

	/**
	 * Zero-value GST data returned when GST is not applicable.
	 *
	 * @return array
	 */
	private function empty_data(): array {
		return array(
			'cgst'     => 0,
			'sgst'     => 0,
			'igst'     => 0,
			'total'    => 0,
			'rate'     => 0.0,
			'tax_type' => 'igst',
		);
	}
}
