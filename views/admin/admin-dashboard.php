<?php
/**
 * Admin Dashboard view.
 *
 * Variables provided by Admin_Page::render_dashboard():
 *
 * @var array $dashboard_data  Array with 'settings' and 'version' keys.
 *
 * @package Marginminds
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap marginminds-admin">
	<div class="postbox">
		<div class="inside">
			<h2 class="hndle"><?php esc_html_e( 'Marginminds - Gst Dashboard', 'marginminds-gst' ); ?></h2>
			<div
				id="marginminds-dashboard"
				data-dashboard="<?php echo esc_attr( wp_json_encode( $dashboard_data ) ); ?>"
				aria-live="polite"
			></div>
		</div>
	</div>
</div>
