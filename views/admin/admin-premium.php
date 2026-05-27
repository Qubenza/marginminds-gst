<?php
/**
 * Admin Premium Features view — mounts the PremiumFeatures React component.
 *
 * Variables provided by Admin_Page::render_features():
 *
 * @var string $upgrade_url  URL of the upgrade/purchase page.
 *
 * @package Marginminds
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap marginminds-admin">
	<div class="postbox">
		<div class="inside">
			<h2 class="hndle"><?php esc_html_e( 'Premium Features', 'marginminds-gst' ); ?></h2>
			<div
				id="marginminds-premium"
				data-upgrade-url="<?php echo esc_attr( $upgrade_url ); ?>"
				aria-live="polite"
			></div>
		</div>
	</div>
</div>
