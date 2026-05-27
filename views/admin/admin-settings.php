<?php
/**
 * Admin Settings.
 *
 * Variables:
 *  - $initial_data : array The server-fetched (sanitized) data snapshot.
 *  - $this         : Gst\Marginminds\Tax_Admin\Admin_Page instance (so $this->render_table() is available).
 *
 * Note: This template is intentionally presentational only — no business logic here.
 *
 * @var array $initial_data
 * @var Gst\Marginminds\Tax_Admin\Admin_Page $this
 *
 * @package Marginminds
 */

use Gst\Marginminds\Tax_Admin\Ajax_Endpoint;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap marginminds-admin">
	<div class="postbox">
		<div class="inside">
			<h2 class="hndle"><?php esc_html_e( 'Gst-Marginminds Settings', 'marginminds-gst' ); ?></h2>
			<div
				id="marginminds-settings"
				data-save-action="<?php echo esc_attr( Ajax_Endpoint::SAVE_ACTION ); ?>"
				data-settings="<?php echo esc_attr( wp_json_encode( Ajax_Endpoint::get_settings() ) ); ?>"
				aria-live="polite"
			></div>
		</div>
	</div>
</div>