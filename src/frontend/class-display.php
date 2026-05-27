<?php
/**
 * Frontend Display — GST notes on product prices, cart, checkout, and order summary.
 *
 * @package Marginminds
 */

namespace Marginminds\Gst\Frontend;

use Marginminds\Gst\GST\GST_Calculator;
use Marginminds\Gst\GST\Tax_Manager;
use Marginminds\Gst\Orders\Order_Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Handles all customer-facing GST display:
 *
 *  - display_gst_below_product_price : appends "+18% GST" or "(incl. 18% GST)"
 *    to product price HTML on shop and single-product pages.
 *
 *  - show_gst_on_cart : adds CGST/SGST (or IGST) breakdown rows plus a Total GST
 *    row after the cart totals table, independent of WooCommerce's tax display setting.
 *
 *  - show_gst_on_checkout : same breakdown in the checkout order-review block.
 *
 *  - show_gst_in_order_summary : shows the full CGST/SGST or IGST breakdown on the
 *    thank-you page and My Account > View Order page (priority 20, after
 *    Checkout_Fields shows the GSTIN at priority 10).
 */
class Display {

	/**
	 * CSS class applied to the price note span.
	 */
	const PRICE_NOTE_CLASS = 'marginminds-price-note';

	/**
	 * Tax_Manager instance used for rate parsing and state resolution.
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
	 * Constructor — loads settings once.
	 */
	public function __construct() {
		$this->tax_manager = new Tax_Manager();
		$this->settings    = $this->tax_manager->get_settings();
	}

	/**
	 * Register all frontend hooks.
	 *
	 * Bails immediately when the master GST switch is off.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->settings['enable_gst'] ) {
			return;
		}

		if ( $this->settings['display_gst_below_product_price'] ) {
			add_filter( 'woocommerce_get_price_html', array( $this, 'append_gst_note_to_price' ), 10, 2 );
		}

		if ( $this->settings['show_gst_on_cart'] ) {
			add_action( 'woocommerce_cart_totals_before_order_total', array( $this, 'render_cart_gst_total' ) );
		}

		if ( $this->settings['show_gst_on_checkout'] ) {
			add_action( 'woocommerce_review_order_before_order_total', array( $this, 'render_checkout_gst_total' ) );
		}

		if ( $this->settings['show_gst_in_order_summary'] ) {
			add_filter( 'woocommerce_get_order_item_totals', array( $this, 'inject_gst_into_order_totals' ), 10, 2 );
		}
	}

	// -------------------------------------------------------------------------
	// Hook callbacks — public (required by add_filter / add_action)
	// -------------------------------------------------------------------------

	/**
	 * Append a GST rate note to the product price HTML.
	 *
	 * Shows "+18% GST" for exclusive prices or "(incl. 18% GST)" for inclusive.
	 * Skips admin, empty price strings, and non-taxable products.
	 *
	 * @param string      $price_html Existing price HTML from WooCommerce.
	 * @param \WC_Product $product    Product being displayed.
	 * @return string
	 */
	public function append_gst_note_to_price( string $price_html, \WC_Product $product ): string {
		if ( is_admin() ) {
			return $price_html;
		}

		if ( '' === $price_html ) {
			return $price_html;
		}

		if ( ! $product->is_taxable() || 'none' === $product->get_tax_status() ) {
			return $price_html;
		}

		$rate = $this->tax_manager->parse_rate();
		if ( $rate <= 0 ) {
			return $price_html;
		}

		if ( $this->settings['prices_include_gst'] ) {
			$note = sprintf(
				/* translators: %s: GST rate percentage, e.g. 18 */
				__( '(incl. %s%% GST)', 'marginminds-gst' ),
				$rate
			);
		} else {
			$note = sprintf(
				/* translators: %s: GST rate percentage, e.g. 18 */
				__( '+%s%% GST', 'marginminds-gst' ),
				$rate
			);
		}

		return $price_html
			. '<br><small class="' . esc_attr( self::PRICE_NOTE_CLASS ) . '">'
			. esc_html( $note )
			. '</small>';
	}

	/**
	 * Output a "Total GST" row after the cart totals table.
	 *
	 * Fires inside <tbody> of the cart totals table. Returns early when the
	 * cart is empty or no GST has been calculated.
	 *
	 * @return void
	 */
	public function render_cart_gst_total(): void {
		if ( ! $this->cart_is_available() ) {
			return;
		}
		$gst_display_tax = get_option( 'woocommerce_tax_display_cart' );
		if ( 'incl' === $gst_display_tax ) {
			return;
		}
		$this->render_gst_total_row( $this->get_cart_gst_amounts() );
	}

	/**
	 * Output a "Total GST" row in the checkout order-review block.
	 *
	 * Fires inside <tbody> of the checkout review totals table.
	 *
	 * @return void
	 */
	public function render_checkout_gst_total(): void {
		if ( ! $this->cart_is_available() ) {
			return;
		}
		$gst_display_tax = get_option( 'woocommerce_tax_display_cart' );
		if ( 'incl' === $gst_display_tax ) {
			return;
		}
		$this->render_gst_total_row( $this->get_cart_gst_amounts() );
	}

	/**
	 * Inject GST breakdown rows into the order totals table.
	 *
	 * Filters woocommerce_get_order_item_totals so that CGST/SGST or IGST
	 * rows appear inside the existing <tfoot> of the order-details table,
	 * on both the thank-you page and the My Account view-order page.
	 *
	 * Reads from the saved meta snapshot; falls back to a live calculation
	 * from the order items when the meta is absent (e.g. older orders).
	 *
	 * @param array     $total_rows Existing totals rows from WooCommerce.
	 * @param \WC_Order $order      WooCommerce order.
	 * @return array
	 */
	public function inject_gst_into_order_totals( array $total_rows, \WC_Order $order ): array {
		// woocommerce_get_order_item_totals also fires inside email templates.
		// Skip GST injection in emails unless the setting is explicitly enabled.
		if ( doing_action( 'woocommerce_email_order_details' ) && ! $this->settings['display_gst_in_order_emails'] ) {
			return $total_rows;
		}

		$breakdown = $order->get_meta( Order_Meta::META_KEY );

		if ( ! is_array( $breakdown ) || empty( $breakdown ) ) {
			$breakdown = $this->compute_breakdown_from_order( $order );
		}

		if ( empty( $breakdown ) ) {
			return $total_rows;
		}

		$total = (float) ( $breakdown['cgst'] + $breakdown['sgst'] + $breakdown['igst'] );
		if ( $total <= 0 ) {
			return $total_rows;
		}

		$rate     = (float) $breakdown['rate'];
		$half     = $rate / 2;
		$tax_type = (string) $breakdown['tax_type'];

		// Collect shipping GST from the order's shipping line items.
		$ship_cgst = 0.0;
		$ship_sgst = 0.0;
		$ship_igst = 0.0;
		foreach ( $order->get_items( 'shipping' ) as $ship_item ) {
			$taxes      = $ship_item->get_taxes();
			$tax_totals = isset( $taxes['total'] ) ? (array) $taxes['total'] : array();
			$ship_cgst += (float) ( $tax_totals[ Tax_Manager::KEY_CGST ] ?? 0 );
			$ship_sgst += (float) ( $tax_totals[ Tax_Manager::KEY_SGST ] ?? 0 );
			$ship_igst += (float) ( $tax_totals[ Tax_Manager::KEY_IGST ] ?? 0 );
		}

		$gst_rows = array();
		if ( GST_Calculator::TYPE_CGST_SGST === $tax_type ) {
			$gst_rows['gstmarginminds_cgst'] = array(
				/* translators: %s: GST half-rate as a number, e.g. 9 */
				'label' => sprintf( __( 'CGST (%s%%)', 'marginminds-gst' ), $half ) . ':',
				'value' => wc_price( (float) $breakdown['cgst'] ),
			);
			$gst_rows['gstmarginminds_sgst'] = array(
				/* translators: %s: GST half-rate as a number, e.g. 9 */
				'label' => sprintf( __( 'SGST (%s%%)', 'marginminds-gst' ), $half ) . ':',
				'value' => wc_price( (float) $breakdown['sgst'] ),
			);
			if ( $ship_cgst > 0 ) {
				$gst_rows['gstmarginminds_shipping_cgst'] = array(
					/* translators: %s: GST half-rate as a number, e.g. 9 */
					'label' => sprintf( __( 'Shipping CGST (%s%%)', 'marginminds-gst' ), $half ) . ':',
					'value' => wc_price( $ship_cgst ),
				);
			}
			if ( $ship_sgst > 0 ) {
				$gst_rows['gstmarginminds_shipping_sgst'] = array(
					/* translators: %s: GST half-rate as a number, e.g. 9 */
					'label' => sprintf( __( 'Shipping SGST (%s%%)', 'marginminds-gst' ), $half ) . ':',
					'value' => wc_price( $ship_sgst ),
				);
			}
		} else {
			$gst_rows['gstmarginminds_igst'] = array(
				/* translators: %s: GST rate as a number, e.g. 18 */
				'label' => sprintf( __( 'IGST (%s%%)', 'marginminds-gst' ), $rate ) . ':',
				'value' => wc_price( (float) $breakdown['igst'] ),
			);
			if ( $ship_igst > 0 ) {
				$gst_rows['gstmarginminds_shipping_igst'] = array(
					/* translators: %s: GST rate as a number, e.g. 18 */
					'label' => sprintf( __( 'Shipping IGST (%s%%)', 'marginminds-gst' ), $rate ) . ':',
					'value' => wc_price( $ship_igst ),
				);
			}
		}

		// Insert before the order_total row.
		$pos = array_search( 'order_total', array_keys( $total_rows ), true );
		if ( false === $pos ) {
			return array_merge( $total_rows, $gst_rows );
		}

		return array_slice( $total_rows, 0, $pos, true )
			+ $gst_rows
			+ array_slice( $total_rows, $pos, null, true );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Read combined CGST, SGST, and IGST amounts from the live cart.
	 *
	 * Merges item taxes and shipping taxes so the total reflects all
	 * charged amounts including shipping GST when enabled.
	 *
	 * @return array Keys: Tax_Manager::KEY_CGST, KEY_SGST, KEY_IGST.
	 */
	private function get_cart_gst_amounts(): array {
		$item_taxes = (array) WC()->cart->get_taxes();
		return array(
			Tax_Manager::KEY_CGST => isset( $item_taxes[ Tax_Manager::KEY_CGST ] ) ? $item_taxes[ Tax_Manager::KEY_CGST ] : 0.0,
			Tax_Manager::KEY_SGST => isset( $item_taxes[ Tax_Manager::KEY_SGST ] ) ? $item_taxes[ Tax_Manager::KEY_SGST ] : 0.0,
			Tax_Manager::KEY_IGST => isset( $item_taxes[ Tax_Manager::KEY_IGST ] ) ? $item_taxes[ Tax_Manager::KEY_IGST ] : 0.0,
		);
	}

	/**
	 * Output CGST/SGST or IGST breakdown rows plus a Total GST row.
	 *
	 * Renders nothing when the total is zero or negative. Outputs the full
	 * breakdown independently so it works even when WooCommerce's own tax
	 * display is disabled.
	 *
	 * @param array $amounts CGST/SGST/IGST amounts from get_cart_gst_amounts().
	 * @return void
	 */
	private function render_gst_total_row( array $amounts ): void {
		$total = array_sum( $amounts );
		if ( $total <= 0 ) {
			return;
		}

		$rate = $this->tax_manager->parse_rate();
		$half = $rate / 2;
		$cgst = (float) ( isset( $amounts[ Tax_Manager::KEY_CGST ] ) ? $amounts[ Tax_Manager::KEY_CGST ] : 0.0 );
		$sgst = (float) ( isset( $amounts[ Tax_Manager::KEY_SGST ] ) ? $amounts[ Tax_Manager::KEY_SGST ] : 0.0 );
		$igst = (float) ( isset( $amounts[ Tax_Manager::KEY_IGST ] ) ? $amounts[ Tax_Manager::KEY_IGST ] : 0.0 );
		?>
		<?php if ( $cgst > 0 || $sgst > 0 ) : ?>
		<tr>
			<th><?php echo esc_html( sprintf( /* translators: %s: GST half-rate, e.g. 9 */ __( 'CGST (%s%%)', 'marginminds-gst' ), $half ) ); ?></th>
			<td><?php echo wp_kses_post( wc_price( $cgst ) ); ?></td>
		</tr>
		<tr>
			<th><?php echo esc_html( sprintf( /* translators: %s: GST half-rate, e.g. 9 */ __( 'SGST (%s%%)', 'marginminds-gst' ), $half ) ); ?></th>
			<td><?php echo wp_kses_post( wc_price( $sgst ) ); ?></td>
		</tr>
		<?php elseif ( $igst > 0 ) : ?>
		<tr>
			<th><?php echo esc_html( sprintf( /* translators: %s: GST rate, e.g. 18 */ __( 'IGST (%s%%)', 'marginminds-gst' ), $rate ) ); ?></th>
			<td><?php echo wp_kses_post( wc_price( $igst ) ); ?></td>
		</tr>
			<?php
			endif;
	}

	/**
	 * Return true when the WooCommerce cart is initialised and not empty.
	 *
	 * @return bool
	 */
	private function cart_is_available(): bool {
		return isset( WC()->cart ) && ! WC()->cart->is_empty();
	}

	/**
	 * Compute a GST breakdown array directly from order line items.
	 *
	 * Used as a fallback when the meta snapshot is absent (orders placed
	 * before the plugin was activated, or during testing without the hook).
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return array Breakdown keys: cgst, sgst, igst, rate, tax_type. Empty on no GST.
	 */
	private function compute_breakdown_from_order( \WC_Order $order ): array {
		$rate        = $this->tax_manager->parse_rate();
		$buyer_state = strtoupper( $order->get_billing_state() );
		$total_cgst  = 0.0;
		$total_sgst  = 0.0;
		$total_igst  = 0.0;
		$tax_type    = GST_Calculator::TYPE_IGST;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product instanceof \WC_Product || 'none' === $product->get_tax_status() ) {
				continue;
			}
			$line_total = (float) $item->get_total();
			if ( $line_total <= 0 ) {
				continue;
			}
			$result      = $this->tax_manager->calculate_for_price( $line_total, $buyer_state, false );
			$total_cgst += $result['cgst'];
			$total_sgst += $result['sgst'];
			$total_igst += $result['igst'];
			$tax_type    = $result['tax_type'];
		}

		if ( ( $total_cgst + $total_sgst + $total_igst ) <= 0 ) {
			return array();
		}

		return array(
			'cgst'     => $total_cgst,
			'sgst'     => $total_sgst,
			'igst'     => $total_igst,
			'rate'     => $rate,
			'tax_type' => $tax_type,
		);
	}
}
