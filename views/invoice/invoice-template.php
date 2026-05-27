<?php
/**
 * Invoice HTML template.
 *
 * Variables provided by Invoice_Generator::render():
 *
 * @var \WC_Order $order           WooCommerce order.
 * @var array     $settings        Merged plugin settings.
 * @var array     $breakdown       GST breakdown from Order_Meta.
 * @var string    $invoice_number  Formatted invoice number, e.g. "INV-123".
 * @var string    $customer_gstin  Customer GSTIN or empty string.
 *
 * @package Marginminds
 */

use Marginminds\Gst\GST\GST_Calculator;

defined( 'ABSPATH' ) || exit;

$marginminds_gst_order_date       = $order->get_date_created();
$marginminds_gst_formatted_date   = $marginminds_gst_order_date instanceof \WC_DateTime ? $marginminds_gst_order_date->date_i18n( wc_date_format() ) : '';
$marginminds_gst_billing_address  = $order->get_formatted_billing_address();
$marginminds_gst_shipping_address = $order->get_formatted_shipping_address();

$marginminds_gst_total              = (float) ( $breakdown['cgst'] + $breakdown['sgst'] + $breakdown['igst'] );
$marginminds_gst_shipping_gst_total = (float) ( $breakdown['shipping_cgst'] + $breakdown['shipping_sgst'] + $breakdown['shipping_igst'] );
$marginminds_gst_rate               = (float) $breakdown['rate'];
$marginminds_gst_half               = $marginminds_gst_rate / 2;
$marginminds_gst_tax_type           = (string) $breakdown['tax_type'];

$marginminds_gst_calculator   = new GST_Calculator();
$marginminds_gst_has_gst_cols = $marginminds_gst_total > 0;

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: Invoice number, e.g. INV-123 */
			__( 'Invoice %s', 'marginminds-gst' ),
			$invoice_number
		)
	);
	?>
</title>
<?php wp_styles()->do_items( array( 'gst-mm-invoice' ) ); ?>
</head>
<body>
<div class="inv-wrap">

	<!-- Header: store info + invoice meta -->
	<div class="inv-header">
		<div class="inv-store">
			<h1><?php echo esc_html( '' !== $settings['business_legal_name'] ? $settings['business_legal_name'] : get_bloginfo( 'name' ) ); ?></h1>
			<?php if ( '' !== $settings['store_gstin'] && $settings['show_gstin_on_invoice'] ) : ?>
			<p><?php echo esc_html( sprintf( /* translators: %s: Store GSTIN */ __( 'GSTIN: %s', 'marginminds-gst' ), strtoupper( $settings['store_gstin'] ) ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $settings['business_address'] ) : ?>
			<p>
				<?php echo nl2br( esc_html( $settings['business_address'] ) ); ?>
				<?php if ( '' !== $business_state_name ) : ?>
				<br><?php echo esc_html( $business_state_name ); ?>
				<?php endif; ?>
			</p>
			<?php endif; ?>
		</div>

		<div class="inv-meta">
			<div class="inv-title"><?php esc_html_e( 'Tax Invoice', 'marginminds-gst' ); ?></div>
			<table>
				<tr>
					<td><?php esc_html_e( 'Invoice No.', 'marginminds-gst' ); ?></td>
					<td><?php echo esc_html( $invoice_number ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Invoice Date', 'marginminds-gst' ); ?></td>
					<td><?php echo esc_html( $marginminds_gst_formatted_date ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Order No.', 'marginminds-gst' ); ?></td>
					<td><?php echo esc_html( $order->get_order_number() ); ?></td>
				</tr>
			</table>
		</div>
	</div>

	<!-- Billing & Shipping addresses -->
	<div class="inv-parties">
		<div class="inv-party">
			<h3><?php esc_html_e( 'Billed To', 'marginminds-gst' ); ?></h3>
			<address>
				<?php echo wp_kses_post( '' !== $marginminds_gst_billing_address ? $marginminds_gst_billing_address : esc_html__( '—', 'marginminds-gst' ) ); ?>
				<?php if ( '' !== $customer_gstin && $settings['show_gstin_on_invoice'] ) : ?>
				<br>
					<?php echo esc_html( sprintf( /* translators: %s: Customer GSTIN */ __( 'GSTIN: %s', 'marginminds-gst' ), $customer_gstin ) ); ?>
				<?php endif; ?>
			</address>
		</div>

		<?php if ( '' !== $marginminds_gst_shipping_address && $marginminds_gst_shipping_address !== $marginminds_gst_billing_address ) : ?>
		<div class="inv-party">
			<h3><?php esc_html_e( 'Shipped To', 'marginminds-gst' ); ?></h3>
			<address><?php echo wp_kses_post( $marginminds_gst_shipping_address ); ?></address>
		</div>
		<?php endif; ?>
	</div>

	<!-- Line items -->
	<table class="inv-items">
		<thead>
			<tr>
				<th class="col-num">#</th>
				<th><?php esc_html_e( 'Item', 'marginminds-gst' ); ?></th>
				<th class="col-qty"><?php esc_html_e( 'Qty', 'marginminds-gst' ); ?></th>
				<th class="col-rate"><?php esc_html_e( 'Rate', 'marginminds-gst' ); ?></th>
				<th class="col-taxable"><?php esc_html_e( 'Taxable Amt', 'marginminds-gst' ); ?></th>
				<?php if ( $marginminds_gst_has_gst_cols ) : ?>
					<?php if ( GST_Calculator::TYPE_CGST_SGST === $marginminds_gst_tax_type ) : ?>
					<th class="col-cgst">
						<?php echo esc_html( sprintf( /* translators: %s: half GST rate */ __( 'CGST (%s%%)', 'marginminds-gst' ), $marginminds_gst_half ) ); ?>
					</th>
					<th class="col-sgst">
						<?php echo esc_html( sprintf( /* translators: %s: half GST rate */ __( 'SGST (%s%%)', 'marginminds-gst' ), $marginminds_gst_half ) ); ?>
					</th>
					<?php else : ?>
					<th class="col-igst">
						<?php echo esc_html( sprintf( /* translators: %s: full GST rate */ __( 'IGST (%s%%)', 'marginminds-gst' ), $marginminds_gst_rate ) ); ?>
					</th>
					<?php endif; ?>
				<?php endif; ?>
				<th class="col-total"><?php esc_html_e( 'Amount', 'marginminds-gst' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$marginminds_gst_row_num = 0;
			foreach ( $order->get_items() as $marginminds_gst_item_id => $marginminds_gst_item ) :
				$marginminds_gst_product = $marginminds_gst_item->get_product();
				++$marginminds_gst_row_num;
				$marginminds_gst_qty        = $marginminds_gst_item->get_quantity();
				$marginminds_gst_line_total = (float) $marginminds_gst_item->get_total();
				$marginminds_gst_unit_price = $marginminds_gst_qty > 0 ? $marginminds_gst_line_total / $marginminds_gst_qty : 0.0;

				$marginminds_gst_item_is_taxable = $marginminds_gst_product instanceof \WC_Product && 'none' !== $marginminds_gst_product->get_tax_status();
				$marginminds_gst_item_gst        = array(
					'taxable_amount' => $marginminds_gst_line_total,
					'cgst'           => 0.0,
					'sgst'           => 0.0,
					'igst'           => 0.0,
					'tax_amount'     => 0.0,
				);
				if ( $marginminds_gst_has_gst_cols && $marginminds_gst_item_is_taxable && $marginminds_gst_line_total > 0 ) {
					$marginminds_gst_item_gst = $marginminds_gst_calculator->calculate( $marginminds_gst_line_total, $marginminds_gst_rate, $marginminds_gst_tax_type, false );
				}
				?>
			<tr>
				<td><?php echo esc_html( $marginminds_gst_row_num ); ?></td>
				<td>
					<?php echo esc_html( $marginminds_gst_item->get_name() ); ?>
					<?php if ( $marginminds_gst_product instanceof \WC_Product && '' !== $marginminds_gst_product->get_sku() ) : ?>
					<br><small><?php echo esc_html( sprintf( /* translators: %s: Product SKU */ __( 'SKU: %s', 'marginminds-gst' ), $marginminds_gst_product->get_sku() ) ); ?></small>
					<?php endif; ?>
				</td>
				<td class="col-qty"><?php echo esc_html( $marginminds_gst_qty ); ?></td>
				<td class="col-rate"><?php echo wp_kses_post( wc_price( $marginminds_gst_unit_price ) ); ?></td>
				<td class="col-taxable"><?php echo wp_kses_post( wc_price( $marginminds_gst_item_gst['taxable_amount'] ) ); ?></td>
				<?php if ( $marginminds_gst_has_gst_cols ) : ?>
					<?php if ( GST_Calculator::TYPE_CGST_SGST === $marginminds_gst_tax_type ) : ?>
					<td class="col-cgst"><?php echo wp_kses_post( wc_price( $marginminds_gst_item_gst['cgst'] ) ); ?></td>
					<td class="col-sgst"><?php echo wp_kses_post( wc_price( $marginminds_gst_item_gst['sgst'] ) ); ?></td>
					<?php else : ?>
					<td class="col-igst"><?php echo wp_kses_post( wc_price( $marginminds_gst_item_gst['igst'] ) ); ?></td>
					<?php endif; ?>
				<?php endif; ?>
				<td class="col-total"><?php echo wp_kses_post( wc_price( $marginminds_gst_line_total + $marginminds_gst_item_gst['tax_amount'] ) ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Totals block -->
	<div class="inv-totals-wrap">
		<table class="inv-totals">
			<tr>
				<td><?php esc_html_e( 'Subtotal', 'marginminds-gst' ); ?></td>
				<td><?php echo wp_kses_post( wc_price( (float) $order->get_subtotal() ) ); ?></td>
			</tr>

			<?php if ( (float) $order->get_shipping_total() > 0 ) : ?>
			<tr>
				<td><?php esc_html_e( 'Shipping', 'marginminds-gst' ); ?></td>
				<td><?php echo wp_kses_post( wc_price( (float) $order->get_shipping_total() ) ); ?></td>
			</tr>
			<?php endif; ?>

			<?php if ( $marginminds_gst_total > 0 ) : ?>
				<tr>
					<td><?php esc_html_e( 'Taxable Amount', 'marginminds-gst' ); ?></td>
					<td><?php echo wp_kses_post( wc_price( (float) $breakdown['taxable_amount'] ) ); ?></td>
				</tr>
				<?php if ( GST_Calculator::TYPE_CGST_SGST === $marginminds_gst_tax_type ) : ?>
				<tr>
					<td>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: GST half-rate as a number, e.g. 9 */
								__( 'CGST (%s%%)', 'marginminds-gst' ),
								$marginminds_gst_half
							)
						);
						?>
					</td>
					<td><?php echo wp_kses_post( wc_price( (float) $breakdown['cgst'] ) ); ?></td>
				</tr>
				<tr>
					<td>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: GST half-rate as a number, e.g. 9 */
								__( 'SGST (%s%%)', 'marginminds-gst' ),
								$marginminds_gst_half
							)
						);
						?>
					</td>
					<td><?php echo wp_kses_post( wc_price( (float) $breakdown['sgst'] ) ); ?></td>
				</tr>
				<?php else : ?>
				<tr>
					<td>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: GST rate as a number, e.g. 18 */
								__( 'IGST (%s%%)', 'marginminds-gst' ),
								$marginminds_gst_rate
							)
						);
						?>
					</td>
					<td><?php echo wp_kses_post( wc_price( (float) $breakdown['igst'] ) ); ?></td>
				</tr>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $marginminds_gst_shipping_gst_total > 0 ) : ?>
				<tr>
					<td><?php esc_html_e( 'Taxable Amount (Shipping)', 'marginminds-gst' ); ?></td>
					<td><?php echo wp_kses_post( wc_price( (float) $breakdown['shipping_taxable_amount'] ) ); ?></td>
				</tr>
				<?php if ( GST_Calculator::TYPE_CGST_SGST === $marginminds_gst_tax_type ) : ?>
				<tr>
					<td>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: GST half-rate as a number, e.g. 9 */
								__( 'CGST on Shipping (%s%%)', 'marginminds-gst' ),
								$marginminds_gst_half
							)
						);
						?>
					</td>
					<td><?php echo wp_kses_post( wc_price( (float) $breakdown['shipping_cgst'] ) ); ?></td>
				</tr>
				<tr>
					<td>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: GST half-rate as a number, e.g. 9 */
								__( 'SGST on Shipping (%s%%)', 'marginminds-gst' ),
								$marginminds_gst_half
							)
						);
						?>
					</td>
					<td><?php echo wp_kses_post( wc_price( (float) $breakdown['shipping_sgst'] ) ); ?></td>
				</tr>
				<?php else : ?>
				<tr>
					<td>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: GST rate as a number, e.g. 18 */
								__( 'IGST on Shipping (%s%%)', 'marginminds-gst' ),
								$marginminds_gst_rate
							)
						);
						?>
					</td>
					<td><?php echo wp_kses_post( wc_price( (float) $breakdown['shipping_igst'] ) ); ?></td>
				</tr>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( (float) $order->get_discount_total() > 0 ) : ?>
			<tr>
				<td><?php esc_html_e( 'Discount', 'marginminds-gst' ); ?></td>
				<td>-<?php echo wp_kses_post( wc_price( (float) $order->get_discount_total() ) ); ?></td>
			</tr>
			<?php endif; ?>

			<tr class="inv-grand-total">
				<td><?php esc_html_e( 'Total', 'marginminds-gst' ); ?></td>
				<td><?php echo wp_kses_post( wc_price( (float) $order->get_total() ) ); ?></td>
			</tr>
		</table>
	</div>

	<?php if ( '' !== $settings['invoice_footer_text'] ) : ?>
	<div class="inv-footer">
		<?php echo wp_kses_post( wpautop( $settings['invoice_footer_text'] ) ); ?>
	</div>
	<?php endif; ?>

	<button class="inv-print-btn" onclick="window.print()">
		<?php esc_html_e( 'Print / Save as PDF', 'marginminds-gst' ); ?>
	</button>

</div>
</body>
</html>
