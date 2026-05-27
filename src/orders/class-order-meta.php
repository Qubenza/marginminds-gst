<?php
/**
 * Order Meta — saves and reads the GST breakdown on WooCommerce orders.
 *
 * @package Marginminds
 */

namespace Marginminds\Gst\Orders;

use Marginminds\Gst\GST\GST_Calculator;
use Marginminds\Gst\GST\Tax_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Central data layer for order-level GST information.
 *
 * Responsibilities:
 *  - Calculate and save a full GST breakdown snapshot at order creation
 *  - Provide a clean read API so invoice, email, and display classes
 *    never touch order meta keys directly
 *  - Show the GST breakdown rows in the admin order totals metabox
 *
 * Why a snapshot?
 *  Settings (rate, state) can change after an order is placed.
 *  Storing the breakdown at creation time means invoices and reports
 *  always reflect what the customer was actually charged.
 */
class Order_Meta {

	/**
	 * Order meta key for the full GST breakdown array.
	 */
	const META_KEY = '_gstmarginminds_gst_breakdown';

	/**
	 * Tax_Manager instance (constructor-only — register() never called here).
	 *
	 * @var Tax_Manager
	 */
	private $tax_manager;

	/**
	 * Merged plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor — creates a Tax_Manager for calculation without hooking WC.
	 */
	public function __construct() {
		$this->tax_manager = new Tax_Manager();
		$this->settings    = $this->tax_manager->get_settings();
	}

	/**
	 * Register WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->settings['enable_gst'] ) {
			return;
		}

		// Save breakdown at order creation (priority 20 = after checkout-fields hook at 10).
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_breakdown' ), 20, 2 );

		// Blocks checkout uses the Store API — wire up the same logic there.
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'save_breakdown_blocks' ), 20, 2 );

		// Show breakdown rows in admin order totals metabox.
		add_action( 'woocommerce_admin_order_totals_after_tax', array( $this, 'display_breakdown_in_admin' ), 10, 1 );
	}

	// -------------------------------------------------------------------------
	// Write
	// -------------------------------------------------------------------------

	/**
	 * Entry point for Blocks (Store API) checkout — delegates to save_breakdown.
	 *
	 * @param \WC_Order        $order   WooCommerce order.
	 * @param \WP_REST_Request $request Full REST request (unused).
	 * @return void
	 */
	public function save_breakdown_blocks( \WC_Order $order, \WP_REST_Request $request ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$this->save_breakdown( $order, array() );
	}

	/**
	 * Calculate the GST breakdown from order line items and save to order meta.
	 *
	 * WooCommerce stores item line totals as pre-tax amounts in both inclusive
	 * and exclusive modes, so we always calculate with inclusive = false here.
	 *
	 * @param \WC_Order $order WooCommerce order being created.
	 * @param array     $data  Processed checkout POST data (unused but required by hook).
	 * @return void
	 */
	public function save_breakdown( \WC_Order $order, array $data ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$buyer_state = $this->resolve_buyer_state( $order );
		$rate        = $this->tax_manager->parse_rate();

		$total_taxable = 0.0;
		$total_cgst    = 0.0;
		$total_sgst    = 0.0;
		$total_igst    = 0.0;
		$tax_type      = GST_Calculator::TYPE_IGST;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			if ( 'none' === $product->get_tax_status() ) {
				continue;
			}

			$line_total = (float) $item->get_total();
			if ( $line_total <= 0 ) {
				continue;
			}

			/*
			 * Read the taxes WooCommerce stored from our woocommerce_calc_tax output.
			 * Recalculating from line_total gives the wrong answer when round_tax_amounts
			 * + inclusive pricing are both on: WC stores line_total = inclusive_price −
			 * rounded_tax, so the recalculated base differs slightly, producing a different
			 * rounded result than what the cart displayed.
			 */
			$item_taxes = $item->get_taxes();
			$tax_totals = isset( $item_taxes['total'] ) ? (array) $item_taxes['total'] : array();
			$item_cgst  = (float) ( $tax_totals[ Tax_Manager::KEY_CGST ] ?? 0 );
			$item_sgst  = (float) ( $tax_totals[ Tax_Manager::KEY_SGST ] ?? 0 );
			$item_igst  = (float) ( $tax_totals[ Tax_Manager::KEY_IGST ] ?? 0 );

			if ( $item_cgst > 0 || $item_sgst > 0 ) {
				$tax_type = GST_Calculator::TYPE_CGST_SGST;
			} elseif ( $item_igst > 0 ) {
				$tax_type = GST_Calculator::TYPE_IGST;
			}

			$total_taxable += $line_total;
			$total_cgst    += $item_cgst;
			$total_sgst    += $item_sgst;
			$total_igst    += $item_igst;
		}

		// Shipping GST — stored separately so the invoice can display them independently.
		$shipping_taxable = 0.0;
		$shipping_cgst    = 0.0;
		$shipping_sgst    = 0.0;
		$shipping_igst    = 0.0;

		if ( $this->settings['enable_gst_on_shipping'] ) {
			$shipping_total = (float) $order->get_shipping_total();
			if ( $shipping_total > 0 ) {
				$sr               = $this->tax_manager->calculate_for_price( $shipping_total, $buyer_state, false );
				$shipping_taxable = $sr['taxable_amount'];
				$shipping_cgst    = $sr['cgst'];
				$shipping_sgst    = $sr['sgst'];
				$shipping_igst    = $sr['igst'];
				$tax_type         = $sr['tax_type'];
			}
		}

		$tax_precision = $this->settings['round_tax_amounts'] ? 0 : 2;

		$order->update_meta_data(
			self::META_KEY,
			array(
				'taxable_amount'          => round( $total_taxable, 2 ),
				'tax_total'               => round( $total_cgst + $total_sgst + $total_igst, $tax_precision ),
				'cgst'                    => round( $total_cgst, $tax_precision ),
				'sgst'                    => round( $total_sgst, $tax_precision ),
				'igst'                    => round( $total_igst, $tax_precision ),
				'shipping_taxable_amount' => round( $shipping_taxable, 2 ),
				'shipping_tax_total'      => round( $shipping_cgst + $shipping_sgst + $shipping_igst, $tax_precision ),
				'shipping_cgst'           => round( $shipping_cgst, $tax_precision ),
				'shipping_sgst'           => round( $shipping_sgst, $tax_precision ),
				'shipping_igst'           => round( $shipping_igst, $tax_precision ),
				'rate'                    => $rate,
				'tax_type'                => $tax_type,
				'buyer_state'             => $buyer_state,
				'seller_state'            => $this->tax_manager->get_seller_state(),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Read — public API for invoice, email, display classes
	// -------------------------------------------------------------------------

	/**
	 * Return the full GST breakdown saved on an order.
	 *
	 * Returns an empty-value breakdown array when no data was saved
	 * (e.g. orders placed before the plugin was activated).
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return array
	 */
	public function get_breakdown( \WC_Order $order ): array {
		$saved = $order->get_meta( self::META_KEY );

		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return $this->empty_breakdown();
		}

		return array_merge( $this->empty_breakdown(), $saved );
	}

	/**
	 * Return the CGST amount for an order.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return float
	 */
	public function get_cgst( \WC_Order $order ): float {
		return (float) $this->get_breakdown( $order )['cgst'];
	}

	/**
	 * Return the SGST amount for an order.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return float
	 */
	public function get_sgst( \WC_Order $order ): float {
		return (float) $this->get_breakdown( $order )['sgst'];
	}

	/**
	 * Return the IGST amount for an order.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return float
	 */
	public function get_igst( \WC_Order $order ): float {
		return (float) $this->get_breakdown( $order )['igst'];
	}

	/**
	 * Return the total GST amount for an order.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return float
	 */
	public function get_tax_total( \WC_Order $order ): float {
		return (float) $this->get_breakdown( $order )['tax_total'];
	}

	/**
	 * Return the tax type (cgst_sgst or igst) used for an order.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return string
	 */
	public function get_tax_type( \WC_Order $order ): string {
		return (string) $this->get_breakdown( $order )['tax_type'];
	}

	/**
	 * Return true when the order has a saved GST breakdown.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return bool
	 */
	public function has_breakdown( \WC_Order $order ): bool {
		$saved = $order->get_meta( self::META_KEY );
		return is_array( $saved ) && ! empty( $saved );
	}

	// -------------------------------------------------------------------------
	// Admin display
	// -------------------------------------------------------------------------

	/**
	 * Output GST breakdown rows inside the admin order totals metabox.
	 *
	 * Fires after the standard "Tax" row. Each component (CGST/SGST or IGST)
	 * gets its own row so the admin can see the split clearly.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function display_breakdown_in_admin( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$breakdown = $this->get_breakdown( $order );

		if ( $breakdown['tax_total'] <= 0 ) {
			return;
		}

		$rate = $breakdown['rate'];
		$half = $rate / 2;

		if ( GST_Calculator::TYPE_CGST_SGST === $breakdown['tax_type'] ) {
			/* translators: %s: GST half-rate as a number, e.g. 9 */
			$label_cgst = esc_html( sprintf( __( 'CGST (%s%%)', 'marginminds-gst' ), $half ) );
			/* translators: %s: GST half-rate as a number, e.g. 9 */
			$label_sgst = esc_html( sprintf( __( 'SGST (%s%%)', 'marginminds-gst' ), $half ) );
			?>
			<tr>
				<td class="label"><?php echo $label_cgst; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?></td>
				<td width="1%"></td>
				<td class="total"><?php echo wp_kses_post( wc_price( $breakdown['cgst'] ) ); ?></td>
			</tr>
			<tr>
				<td class="label"><?php echo $label_sgst; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?></td>
				<td width="1%"></td>
				<td class="total"><?php echo wp_kses_post( wc_price( $breakdown['sgst'] ) ); ?></td>
			</tr>
			<?php
		} else {
			/* translators: %s: GST full rate as a number, e.g. 18 */
			$label_igst = esc_html( sprintf( __( 'IGST (%s%%)', 'marginminds-gst' ), $rate ) );
			?>
			<tr>
				<td class="label"><?php echo $label_igst; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?></td>
				<td width="1%"></td>
				<td class="total"><?php echo wp_kses_post( wc_price( $breakdown['igst'] ) ); ?></td>
			</tr>
			<?php
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Determine the buyer's state code from the order's address (e.g. 'KA').
	 *
	 * Uses shipping state when apply_gst_based_on_shipping_state is on,
	 * falls back to billing state if shipping is empty.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return string Two-letter WooCommerce state code, e.g. 'KA'.
	 */
	private function resolve_buyer_state( \WC_Order $order ): string {
		if ( $this->settings['apply_gst_based_on_shipping_state'] ) {
			$code = $order->get_shipping_state();
			if ( '' === $code ) {
				$code = $order->get_billing_state();
			}
		} else {
			$code = $order->get_billing_state();
		}

		return strtoupper( $code );
	}

	/**
	 * Return a zero-value breakdown array used as a safe default.
	 *
	 * @return array
	 */
	private function empty_breakdown(): array {
		return array(
			'taxable_amount'          => 0.0,
			'tax_total'               => 0.0,
			'cgst'                    => 0.0,
			'sgst'                    => 0.0,
			'igst'                    => 0.0,
			'shipping_taxable_amount' => 0.0,
			'shipping_tax_total'      => 0.0,
			'shipping_cgst'           => 0.0,
			'shipping_sgst'           => 0.0,
			'shipping_igst'           => 0.0,
			'rate'                    => 0.0,
			'tax_type'                => GST_Calculator::TYPE_IGST,
			'buyer_state'             => '',
			'seller_state'            => '',
		);
	}
}
