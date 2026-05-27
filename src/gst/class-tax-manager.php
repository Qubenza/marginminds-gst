<?php
/**
 * Tax Manager — bridges saved settings with GST_Calculator and WooCommerce hooks.
 *
 * @package Marginminds
 */

namespace Marginminds\Gst\GST;

use Marginminds\Gst\Tax_Admin\Ajax_Endpoint;

defined( 'ABSPATH' ) || exit;

/**
 * Reads plugin settings and drives all WooCommerce tax integration.
 *
 * Responsibilities:
 *  - Parse settings (e.g. strip '%' from "18%")
 *  - Resolve buyer state (billing vs shipping) from WooCommerce customer
 *  - Convert WooCommerce 2-letter state codes to full names for state comparison
 *  - Hook into woocommerce_calc_tax to apply CGST/SGST or IGST
 *  - Label tax line items correctly on cart and order totals
 *  - Apply GST to shipping charges when enabled
 */
class Tax_Manager {

	/**
	 * Tax line-item keys used in WooCommerce tax arrays.
	 */
	const KEY_CGST = 'gstmarginminds_cgst';
	const KEY_SGST = 'gstmarginminds_sgst';
	const KEY_IGST = 'gstmarginminds_igst';

	/**
	 * True while WC_Cart::calculate_totals() is running.
	 *
	 * WooCommerce calls woocommerce_calc_tax from two contexts with different $price formats:
	 *  - WC_Cart_Totals: $price is in WC internal number-precision format
	 *  - WC_Order_Item::calculate_taxes(): $price is the actual line total in rupees
	 *
	 * We track which context we are in so apply_gst knows whether to apply
	 * wc_remove_number_precision / wc_add_number_precision.
	 *
	 * @var bool
	 */
	private static bool $cart_calc_in_progress = false;

	/**
	 * Pure-math GST calculator instance.
	 *
	 * @var GST_Calculator
	 */
	private $calculator;

	/**
	 * Merged plugin settings (defaults + saved).
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor — loads settings and creates calculator once per request.
	 */
	public function __construct() {
		$this->calculator = new GST_Calculator();
		$this->settings   = Ajax_Endpoint::get_settings();
	}

	/**
	 * Register all WooCommerce hooks.
	 *
	 * Only fires when the master GST switch is on and WooCommerce is active.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->settings['enable_gst'] ) {
			return;
		}

		// Core tax calculation.
		add_filter( 'woocommerce_calc_tax', array( $this, 'apply_gst' ), 20, 5 );

		// Track when $price in woocommerce_calc_tax is WC-precision-scaled (cart path)
		// vs. actual rupees (order-item recalculation path).
		add_action(
			'woocommerce_before_calculate_totals',
			static function () {
				self::$cart_calc_in_progress = true;
			}
		);
		add_action(
			'woocommerce_after_calculate_totals',
			static function () {
				self::$cart_calc_in_progress = false;
			}
		);

		// Label CGST / SGST / IGST in cart and order totals.
		add_filter( 'woocommerce_cart_tax_totals', array( $this, 'label_gst_totals' ), 10, 2 );
		add_filter( 'woocommerce_order_get_tax_totals', array( $this, 'label_order_gst_totals' ), 10, 2 );

		// GST on shipping.
		if ( $this->settings['enable_gst_on_shipping'] ) {
			add_filter(
				'transient_shipping-transient-version',
				function ( $value, $name ) { //phpcs:ignore
					return false;
				},
				10,
				2
			);
			add_filter( 'woocommerce_package_rates', array( $this, 'apply_gst_to_shipping' ), 20, 2 );

			// Fallback: ensure order shipping items always carry GST even when the
			// shipping method is marked non-taxable or WC clears taxes for other reasons.
			add_action( 'woocommerce_order_item_shipping_after_calculate_taxes', array( $this, 'ensure_order_item_shipping_gst' ), 20, 2 );
		}
	}

	// -------------------------------------------------------------------------
	// WooCommerce filter callbacks
	// -------------------------------------------------------------------------

	/**
	 * Intercept WooCommerce tax calculation and return GST breakdown.
	 *
	 * WooCommerce calls this filter for every price it taxes. We ignore the
	 * $rates WooCommerce found and return our own CGST/SGST or IGST amounts.
	 *
	 * WooCommerce passes $price with internal number precision applied
	 * (wc_add_number_precision). We convert to actual price for calculation,
	 * then convert the result back so WC's wc_remove_number_precision produces
	 * the correct final rupee value (including whole-rupee rounding when enabled).
	 *
	 * @param array $taxes               Current tax array (key => amount).
	 * @param float $price               Price being taxed (WC internal precision).
	 * @param array $rates               WooCommerce tax rates (ignored).
	 * @param bool  $price_includes_tax  Whether $price includes tax.
	 * @param bool  $suppress_compound   Whether compound tax is suppressed.
	 * @return array
	 */
	public function apply_gst( array $taxes, float $price, array $rates, bool $price_includes_tax, bool $suppress_compound ): array {
		if ( $price <= 0 ) {
			return $taxes;
		}

		$rate = $this->parse_rate();
		if ( $rate <= 0 ) {
			return $taxes;
		}

		/*
		 * woocommerce_calc_tax is called from two different contexts:
		 *
		 * 1. WC_Cart_Totals (cart/checkout display): $price is in WC internal
		 *    number-precision format (actual rupees x 10^(decimals+2)).
		 *    We must remove precision before calculating, then add it back so
		 *    WC_Cart_Totals can apply wc_remove_number_precision on the result.
		 *
		 * 2. WC_Order_Item::calculate_taxes() (order recalculation): $price is
		 *    the actual line total in rupees. Calling wc_remove_number_precision()
		 *    here would divide by the precision factor (e.g. 100 or 10000),
		 *    producing a near-zero price and therefore zero tax.
		 *
		 * We track which context is active via $cart_calc_in_progress (set by
		 * woocommerce_before/after_calculate_totals hooks on WC_Cart).
		 */
		$use_precision = $this->settings['round_tax_amounts'] && self::$cart_calc_in_progress;
		$actual_price  = $use_precision ? wc_remove_number_precision( $price ) : $price;

		// Use WooCommerce's $price_includes_tax — it reflects the actual state
		// of the price passed in, which may differ from our settings if the merchant
		// has not aligned WooCommerce's "Prices entered with tax" with our setting.
		$result = $this->calculate_for_price( $actual_price, '', $price_includes_tax );

		if ( GST_Calculator::TYPE_CGST_SGST === $result['tax_type'] ) {
			return array(
				self::KEY_CGST => $use_precision ? wc_add_number_precision( $result['cgst'] ) : $result['cgst'],
				self::KEY_SGST => $use_precision ? wc_add_number_precision( $result['sgst'] ) : $result['sgst'],
			);
		}

		return array(
			self::KEY_IGST => $use_precision ? wc_add_number_precision( $result['igst'] ) : $result['igst'],
		);
	}

	/**
	 * Set readable labels on GST line items in the cart totals block.
	 *
	 * WooCommerce creates a generic stdClass for each tax key; this filter
	 * replaces the label with "CGST (9%)", "SGST (9%)", or "IGST (18%)".
	 *
	 * @param array    $tax_totals Cart tax totals (key => stdClass{label,amount}).
	 * @param \WC_Cart $cart       WooCommerce cart instance.
	 * @return array
	 */
	public function label_gst_totals( array $tax_totals, \WC_Cart $cart ): array {
		return $this->apply_gst_labels( $tax_totals );
	}

	/**
	 * Set readable labels on GST line items in order totals.
	 *
	 * @param array     $tax_totals Order tax totals.
	 * @param \WC_Order $order      WooCommerce order instance.
	 * @return array
	 */
	public function label_order_gst_totals( array $tax_totals, \WC_Order $order ): array {
		return $this->apply_gst_labels( $tax_totals );
	}

	/**
	 * Add GST tax to shipping rates.
	 *
	 * Iterates available shipping rates, calculates GST on the shipping cost,
	 * and adds it as a tax line on each rate.
	 *
	 * @param \WC_Shipping_Rate[] $rates   Available shipping rates.
	 * @param array               $package WooCommerce shipping package.
	 * @return \WC_Shipping_Rate[]
	 */
	public function apply_gst_to_shipping( array $rates, array $package ): array {
		foreach ( $rates as $rate ) {
			$cost = (float) $rate->get_cost();
			if ( $cost <= 0 ) {
				continue;
			}

			$result = $this->calculate_for_price( $cost );
			if ( GST_Calculator::TYPE_CGST_SGST === $result['tax_type'] ) {
				$rate->set_taxes(
					array(
						self::KEY_CGST => $result['cgst'],
						self::KEY_SGST => $result['sgst'],
					)
				);
			} else {
				$rate->set_taxes(
					array(
						self::KEY_IGST => $result['igst'],
					)
				);
			}
		}
		return $rates;
	}

	/**
	 * Fallback: apply GST to a shipping order item after WC's own tax calculation.
	 *
	 * Called via woocommerce_order_item_shipping_after_calculate_taxes. When the
	 * shipping method is marked non-taxable (tax_status = 'none') or WC clears
	 * taxes for any other reason, this re-applies our GST so the order's
	 * shipping_tax field is never left at 0.
	 *
	 * @param \WC_Order_Item_Shipping $item               Shipping order item.
	 * @param array                   $_calculate_tax_for Location data passed to calculate_taxes() (unused).
	 * @return void
	 */
	public function ensure_order_item_shipping_gst( \WC_Order_Item_Shipping $item, array $_calculate_tax_for ): void { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$cost = (float) $item->get_total();
		if ( $cost <= 0 ) {
			return;
		}

		// Only act when no taxes were set — avoid interfering with already-correct values.
		$existing = $item->get_taxes();
		if ( ! empty( $existing['total'] ) ) {
			return;
		}

		$result = $this->calculate_for_price( $cost );

		if ( GST_Calculator::TYPE_CGST_SGST === $result['tax_type'] ) {
			$item->set_taxes(
				array(
					'total' => array(
						self::KEY_CGST => $result['cgst'],
						self::KEY_SGST => $result['sgst'],
					),
				)
			);
		} else {
			$item->set_taxes(
				array(
					'total' => array(
						self::KEY_IGST => $result['igst'],
					),
				)
			);
		}
	}

	// -------------------------------------------------------------------------
	// Public API — used by other plugin classes (checkout, invoice, display)
	// -------------------------------------------------------------------------

	/**
	 * Calculate GST breakdown for a given price and optional buyer state.
	 *
	 * Other classes (checkout, invoice, display) call this instead of touching
	 * the calculator or settings directly.
	 *
	 * @param float     $price       The price to calculate GST on.
	 * @param string    $buyer_state Two-letter state code (e.g. 'KA'). Leave empty to read from WC customer.
	 * @param bool|null $inclusive   Override inclusive flag. Null uses the plugin setting.
	 * @return array                 Same shape as GST_Calculator::calculate().
	 */
	public function calculate_for_price( float $price, string $buyer_state = '', ?bool $inclusive = null ): array {
		$resolved_buyer = '' !== $buyer_state ? $buyer_state : $this->get_buyer_state();

		if ( $this->settings['enable_cgst_sgst'] && $this->settings['enable_igst'] ) {
			$tax_type = $this->calculator->determine_tax_type( $this->get_seller_state(), $resolved_buyer );
		} elseif ( $this->settings['enable_cgst_sgst'] ) {
			$tax_type = GST_Calculator::TYPE_CGST_SGST;
		} else {
			$tax_type = GST_Calculator::TYPE_IGST;
		}

		$is_inclusive = ( null !== $inclusive ) ? (bool) $inclusive : $this->settings['prices_include_gst'];

		return $this->calculator->calculate(
			$price,
			$this->parse_rate(),
			$tax_type,
			$is_inclusive,
			$this->settings['round_tax_amounts']
		);
	}

	/**
	 * Return the seller (store) state code from settings (e.g. 'KA').
	 *
	 * @return string
	 */
	public function get_seller_state(): string {
		return strtoupper( $this->settings['business_state'] );
	}

	/**
	 * Return the buyer state code from the active WooCommerce customer (e.g. 'KA').
	 *
	 * WooCommerce already stores customer states as 2-letter codes, so no
	 * conversion is needed — both sides are now compared in the same format.
	 * Reads shipping state when "apply GST based on shipping state" is on,
	 * falls back to billing state if shipping state is empty.
	 *
	 * @return string
	 */
	public function get_buyer_state(): string {
		if ( ! function_exists( 'WC' ) || null === WC()->customer ) {
			return '';
		}

		if ( $this->settings['apply_gst_based_on_shipping_state'] ) {
			$code = WC()->customer->get_shipping_state();
			if ( '' === $code ) {
				$code = WC()->customer->get_billing_state();
			}
		} else {
			$code = WC()->customer->get_billing_state();
		}

		return strtoupper( $code );
	}

	/**
	 * Parse the default_gst_rate setting to a plain float.
	 *
	 * Converts the stored "18%" string to 18.0.
	 *
	 * @param string $rate_string Optional override. Defaults to settings value.
	 * @return float
	 */
	public function parse_rate( string $rate_string = '' ): float {
		$raw = '' !== $rate_string ? $rate_string : $this->settings['default_gst_rate'];
		return (float) str_replace( '%', '', $raw );
	}

	/**
	 * Return the full settings array.
	 *
	 * Allows other classes to read settings without a second DB call.
	 *
	 * @return array
	 */
	public function get_settings(): array {
		return $this->settings;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Apply GST labels to a tax totals array (cart or order).
	 *
	 * @param array $tax_totals Array of stdClass objects keyed by tax code.
	 * @return array
	 */
	private function apply_gst_labels( array $tax_totals ): array {
		$rate = $this->parse_rate();
		$half = $rate / 2;

		$labels = array(
			self::KEY_CGST => sprintf(
				/* translators: %s: GST rate as a number, e.g. 9 */
				__( 'CGST (%s%%)', 'marginminds-gst' ),
				$half
			),
			self::KEY_SGST => sprintf(
				/* translators: %s: GST rate as a number, e.g. 9 */
				__( 'SGST (%s%%)', 'marginminds-gst' ),
				$half
			),
			self::KEY_IGST => sprintf(
				/* translators: %s: GST rate as a number, e.g. 18 */
				__( 'IGST (%s%%)', 'marginminds-gst' ),
				$rate
			),
		);

		foreach ( $labels as $key => $label ) {
			if ( isset( $tax_totals[ $key ] ) ) {
				$tax_totals[ $key ]->label = esc_html( $label );
			}
		}

		return $tax_totals;
	}

	/**
	 * Convert a WooCommerce India state code to the full state name.
	 *
	 * WooCommerce stores customer states as 2-letter codes (e.g. 'KA').
	 * Our settings store the seller state as a full name ('Karnataka').
	 * This converts the buyer code to a full name so both sides match.
	 *
	 * @param string $code Two-letter WooCommerce state code.
	 * @return string Full state name, or empty string if not found.
	 */
	public function state_name_from_code( string $code ): string {
		if ( '' === $code || ! function_exists( 'WC' ) ) {
			return '';
		}

		$states = WC()->countries->get_states( 'IN' );

		if ( ! is_array( $states ) ) {
			return '';
		}

		$upper = strtoupper( $code );
		return isset( $states[ $upper ] ) ? $states[ $upper ] : '';
	}
}
