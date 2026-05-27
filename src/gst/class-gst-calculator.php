<?php
/**
 * GST Calculator — pure calculation engine, no WordPress or WooCommerce dependencies.
 *
 * @package Marginminds
 */

namespace Marginminds\Gst\GST;

defined( 'ABSPATH' ) || exit;

/**
 * Handles all GST arithmetic: exclusive/inclusive tax, CGST/SGST split, IGST.
 *
 * Usage:
 *   $calc   = new GST_Calculator();
 *   $type   = $calc->determine_tax_type( 'Tamil Nadu', 'Karnataka' ); // 'igst'
 *   $result = $calc->calculate( 1000.00, 18, $type );
 *   // $result['cgst'] = 0, $result['igst'] = 180, $result['total'] = 1180
 */
class GST_Calculator {

	/**
	 * Tax type: intra-state (CGST + SGST).
	 */
	const TYPE_CGST_SGST = 'cgst_sgst';

	/**
	 * Tax type: inter-state (IGST).
	 */
	const TYPE_IGST = 'igst';

	/**
	 * Determine whether to apply CGST/SGST or IGST.
	 *
	 * Indian GST rule: same state → CGST + SGST; different states → IGST.
	 *
	 * @param string $seller_state Two-letter state code for the store (e.g. 'KA').
	 * @param string $buyer_state  Two-letter state code for the customer (e.g. 'TN').
	 * @return string One of TYPE_CGST_SGST or TYPE_IGST.
	 */
	public function determine_tax_type( string $seller_state, string $buyer_state ): string {
		$seller = strtolower( trim( $seller_state ) );
		$buyer  = strtolower( trim( $buyer_state ) );

		if ( '' === $seller || '' === $buyer ) {
			return self::TYPE_IGST;
		}

		return ( $seller === $buyer ) ? self::TYPE_CGST_SGST : self::TYPE_IGST;
	}

	/**
	 * Calculate full GST breakdown for a line amount.
	 *
	 * @param float  $amount        The base amount (price of item).
	 * @param float  $rate_percent  GST rate as a percentage (e.g. 18 for 18%).
	 * @param string $tax_type      TYPE_CGST_SGST or TYPE_IGST.
	 * @param bool   $inclusive     True if $amount already includes GST.
	 * @param bool   $should_round  True to round tax to nearest rupee (0 decimal places).
	 * @return array {
	 *     @type float  $taxable_amount  Amount before tax.
	 *     @type float  $tax_amount      Total GST (cgst + sgst OR igst).
	 *     @type float  $cgst            CGST component (0 when IGST applies).
	 *     @type float  $sgst            SGST component (0 when IGST applies).
	 *     @type float  $igst            IGST component (0 when CGST/SGST applies).
	 *     @type float  $total           taxable_amount + tax_amount.
	 *     @type float  $rate            Rate used for calculation.
	 *     @type string $tax_type        Tax type used.
	 * }
	 */
	public function calculate(
		float $amount,
		float $rate_percent,
		string $tax_type,
		bool $inclusive = false,
		bool $should_round = false
	): array {
		$rate_percent = max( 0.0, $rate_percent );

		if ( $inclusive ) {
			$taxable_amount = $this->taxable_from_inclusive( $amount, $rate_percent );
			$tax_amount     = $amount - $taxable_amount;
		} else {
			$taxable_amount = $amount;
			$tax_amount     = $this->tax_on_exclusive( $amount, $rate_percent );
		}

		$cgst = 0.0;
		$sgst = 0.0;
		$igst = 0.0;

		if ( self::TYPE_CGST_SGST === $tax_type ) {
			// Round each half independently from the raw amount so both CGST and
			// SGST are whole rupees when rounding is on (e.g. 2.62 → 3 each = ₹6),
			// keeping the displayed rows consistent with the WooCommerce cart total.
			$cgst = $this->round_tax( $tax_amount / 2, $should_round );
			$sgst = $this->round_tax( $tax_amount / 2, $should_round );
		} else {
			$igst = $this->round_tax( $tax_amount, $should_round );
		}

		$tax_amount = $cgst + $sgst + $igst;

		return array(
			'taxable_amount' => $this->round_tax( $taxable_amount, $should_round ),
			'tax_amount'     => $tax_amount,
			'cgst'           => $cgst,
			'sgst'           => $sgst,
			'igst'           => $igst,
			'total'          => $this->round_tax( $taxable_amount + $tax_amount, $should_round ),
			'rate'           => $rate_percent,
			'tax_type'       => $tax_type,
		);
	}

	/**
	 * Round a tax amount to 2 decimal places.
	 *
	 * When $should_round is false the value is still constrained to 2 decimal
	 * places via PHP_ROUND_HALF_UP to avoid floating-point drift.
	 *
	 * @param float $amount       Raw tax amount.
	 * @param bool  $should_round Whether to apply GST rounding rules.
	 * @return float
	 */
	private function round_tax( float $amount, bool $should_round = false ): float {
		if ( $amount <= 0 ) {
			return 0.0;
		}
		if ( $should_round ) {
			return round( $amount, 0, PHP_ROUND_HALF_UP );
		}
		return round( $amount, 2, PHP_ROUND_HALF_UP );
	}

	/**
	 * Calculate tax amount for a tax-exclusive price.
	 *
	 * @param float $amount       Price before tax.
	 * @param float $rate_percent GST rate percentage.
	 * @return float
	 */
	private function tax_on_exclusive( float $amount, float $rate_percent ): float {
		return ( $amount * $rate_percent ) / 100;
	}

	/**
	 * Derive the taxable (pre-tax) amount from a tax-inclusive price.
	 *
	 * Formula: taxable = ( amount × 100 ) / ( 100 + rate )
	 *
	 * @param float $amount       Price including tax.
	 * @param float $rate_percent GST rate percentage.
	 * @return float
	 */
	private function taxable_from_inclusive( float $amount, float $rate_percent ): float {
		if ( 0.0 === $rate_percent ) {
			return $amount;
		}
		return ( $amount * 100 ) / ( 100 + $rate_percent );
	}
}
