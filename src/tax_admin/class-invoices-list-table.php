<?php
/**
 * Invoices List Table — WP_List_Table subclass for the GST Invoices admin page.
 *
 * @package Marginminds
 */

namespace Gst\Marginminds\Tax_Admin;

use Gst\Marginminds\Invoice\Invoice_Generator;
use Gst\Marginminds\Orders\Order_Meta;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders a paginated table of WooCommerce orders that have GST data,
 * with a "View Invoice" button on every row.
 */
class Invoices_List_Table extends \WP_List_Table {

	/**
	 * Invoice generator — builds URLs and numbers.
	 *
	 * @var Invoice_Generator
	 */
	private $generator;

	/**
	 * Order meta reader.
	 *
	 * @var Order_Meta
	 */
	private $order_meta;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'invoice',
				'plural'   => 'invoices',
				'ajax'     => false,
			)
		);

		$this->generator  = new Invoice_Generator();
		$this->order_meta = new Order_Meta();
	}

	/**
	 * Define table columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'invoice_number' => __( 'Invoice #', 'marginminds-gst' ),
			'order'          => __( 'Order', 'marginminds-gst' ),
			'customer'       => __( 'Customer', 'marginminds-gst' ),
			'date'           => __( 'Date', 'marginminds-gst' ),
			'status'         => __( 'Status', 'marginminds-gst' ),
			'total'          => __( 'Order Total', 'marginminds-gst' ),
			'gst_total'      => __( 'GST Total', 'marginminds-gst' ),
			'actions'        => __( 'Invoice', 'marginminds-gst' ),
		);
	}

	/**
	 * Define sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return array(
			'date' => array( 'date', true ),
		);
	}

	/**
	 * Render the date-range filter controls above the table.
	 *
	 * @param string $which 'top' or 'bottom'.
	 * @return void
	 */
	public function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		?>
		<div class="alignleft actions">
			<label for="marginminds-date-from" class="screen-reader-text">
				<?php esc_html_e( 'From date', 'marginminds-gst' ); ?>
			</label>
			<input type="date" id="marginminds-date-from" name="date_from"
				value="<?php echo esc_attr( $date_from ); ?>"
				placeholder="<?php esc_attr_e( 'From', 'marginminds-gst' ); ?>">

			<label for="marginminds-date-to" class="screen-reader-text">
				<?php esc_html_e( 'To date', 'marginminds-gst' ); ?>
			</label>
			<input type="date" id="marginminds-date-to" name="date_to"
				value="<?php echo esc_attr( $date_to ); ?>"
				placeholder="<?php esc_attr_e( 'To', 'marginminds-gst' ); ?>">

			<?php submit_button( __( 'Filter', 'marginminds-gst' ), 'button', 'filter_action', false ); ?>

			<?php if ( $date_from || $date_to ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( array( 'date_from', 'date_to', 'paged' ) ) ); ?>"
					class="button">
					<?php esc_html_e( 'Clear', 'marginminds-gst' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Query orders and set up pagination.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$order     = ( isset( $_GET['order'] ) && 'asc' === $_GET['order'] ) ? 'ASC' : 'DESC';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$query_args = array(
			'limit'      => $per_page,
			'paged'      => $current_page,
			'orderby'    => 'date',
			'order'      => $order,
			'paginate'   => true,
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => Order_Meta::META_KEY,
					'compare' => 'EXISTS',
				),
			),
		);

		if ( $date_from ) {
			$query_args['date_after'] = $date_from . ' 00:00:00';
		}
		if ( $date_to ) {
			$query_args['date_before'] = $date_to . ' 23:59:59';
		}

		$result = wc_get_orders( $query_args );

		$this->items = $result->orders;

		$this->set_pagination_args(
			array(
				'total_items' => $result->total,
				'per_page'    => $per_page,
				'total_pages' => $result->max_num_pages,
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	/**
	 * Render each column cell.
	 *
	 * @param \WC_Order $order       The order object for this row.
	 * @param string    $column_name Column key.
	 * @return string
	 */
	public function column_default( $order, $column_name ): string {
		switch ( $column_name ) {

			case 'invoice_number':
				return '<strong>' . esc_html( $this->generator->get_invoice_number( $order ) ) . '</strong>';

			case 'order':
				return '<a href="' . esc_url( $order->get_edit_order_url() ) . '">'
					. esc_html(
						sprintf(
							/* translators: %s: order number */
							__( '#%s', 'marginminds-gst' ),
							$order->get_order_number()
						)
					)
					. '</a>';

			case 'customer':
				$name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
				$email = $order->get_billing_email();
				if ( $name ) {
					return esc_html( $name ) . ( $email ? '<br><small>' . esc_html( $email ) . '</small>' : '' );
				}
				return esc_html( $email ?: __( 'Guest', 'marginminds-gst' ) );

			case 'date':
				$date = $order->get_date_created();
				return $date ? esc_html( $date->date_i18n( get_option( 'date_format' ) ) ) : '&mdash;';

			case 'status':
				return '<mark class="order-status status-' . esc_attr( $order->get_status() ) . '"><span>'
					. esc_html( wc_get_order_status_name( $order->get_status() ) )
					. '</span></mark>';

			case 'total':
				return wp_kses_post( $order->get_formatted_order_total() );

			case 'gst_total':
				$gst = $this->order_meta->get_tax_total( $order );
				return $gst > 0 ? wp_kses_post( wc_price( $gst ) ) : '&mdash;';

			case 'actions':
				return '<a href="' . esc_url( $this->generator->get_invoice_url( $order ) ) . '"'
					. ' class="button button-small"'
					. ' target="_blank" rel="noopener noreferrer">'
					. esc_html__( 'View', 'marginminds-gst' )
					. '</a>'
					. '&nbsp;<a href="' . esc_url( $this->generator->get_download_url( $order ) ) . '"'
					. ' class="button button-small">'
					. esc_html__( 'Download', 'marginminds-gst' )
					. '</a>';
		}

		return '';
	}

	/**
	 * Output message when the table has no rows.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No GST invoices found. Orders with GST data will appear here.', 'marginminds-gst' );
	}
}
