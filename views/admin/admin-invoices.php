<?php
/**
 * Admin Invoices view — renders the GST Invoices list table.
 *
 * @package Marginminds
 */

defined( 'ABSPATH' ) || exit;

use Gst\Marginminds\Tax_Admin\Invoices_List_Table;

// Verify nonce if present (submitted via the list table form).
if ( isset( $_GET['marginminds_gst_invoices_nonce'] ) ) {
	wp_verify_nonce(
		sanitize_text_field( wp_unslash( $_GET['marginminds_gst_invoices_nonce'] ) ),
		'marginminds_gst_invoices_action'
	);
}

$mm_gst_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

$mm_gst_table = new Invoices_List_Table();
$mm_gst_table->prepare_items();
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'GST Invoices', 'marginminds-gst' ); ?></h1>
	<hr class="wp-header-end">
	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( $mm_gst_page ); ?>">
		<?php wp_nonce_field( 'marginminds_gst_invoices_action', 'marginminds_gst_invoices_nonce' ); ?>
		<?php $mm_gst_table->display(); ?>
	</form>
</div>