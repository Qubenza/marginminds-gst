<?php
/**
 * Admin page: Marginminds admin page
 *
 * @package Marginminds
 */

namespace Marginminds\Gst\Tax_Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page handler.
 */
class Admin_Page {

	/**
	 * Menu slug.
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'marginminds-gst';

	/**
	 * Capability required to view the page.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Init hooks for the admin page.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add top-level admin menu page.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'Marginminds - Woocommerce', 'marginminds-gst' ),
			__( 'MM - GST', 'marginminds-gst' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-money-alt',
			26
		);
		// Dashboard submenu.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'marginminds-gst' ),
			__( 'Dashboard', 'marginminds-gst' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);
		// Invoices.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Invoices', 'marginminds-gst' ),
			__( 'Invoices', 'marginminds-gst' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-invoices',
			array( $this, 'render_invoices' )
		);
		// Settings submenu.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'marginminds-gst' ),
			__( 'Settings', 'marginminds-gst' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-settings',
			array( $this, 'render_settings' )
		);
		// Premium Features.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Premium Features', 'marginminds-gst' ),
			__( 'Premium Features', 'marginminds-gst' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-features',
			array( $this, 'render_features' )
		);
	}

	/**
	 * Enqueue admin scripts/styles only for our page.
	 *
	 * @param string $hook_suffix Hook suffix for the current admin page.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( strpos( $hook_suffix, 'marginminds-gst' ) === false ) {
			return;
		}

		$version = defined( 'MMSGST_VERSION' ) ? MMSGST_VERSION : false;
		wp_enqueue_style(
			'marginminds-gst-admin-css',
			MMSGST_URL . 'assets/css/marginminds-gst-admin.css',
			array(),
			$version
		);
		wp_enqueue_script(
			'marginminds-vendors-admin',
			MMSGST_URL . 'assets/js/vendors-admin.js',
			array(),
			$version,
			true
		);
		wp_enqueue_script(
			'marginminds-gst-admin-js',
			MMSGST_URL . 'assets/js/marginminds_admin.js',
			array( 'marginminds-vendors-admin' ),
			$version,
			true
		);

		// Localize settings for the script.
		wp_localize_script(
			'marginminds-gst-admin-js',
			'MMSGSTAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'marginminds_gst_form_nonce' ),
			)
		);
	}

	/**
	 * Render the admin dashboard page.
	 *
	 * Passes current settings and plugin version to the React Dashboard
	 * component via a data attribute on the mount element.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have access to this page.', 'marginminds-gst' ) );
		}

		$dashboard_data = array(
			'settings' => Ajax_Endpoint::get_settings(),
			'version'  => defined( 'MMSGST_VERSION' ) ? MMSGST_VERSION : '',
		);

		$view = MMSGST_DIR . 'views/admin/admin-dashboard.php';
		if ( file_exists( $view ) ) {
			include $view;
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Due to some technical error page failed to render contact support', 'marginminds-gst' ) . '</h1>';
			echo '</div>';
		}
	}

	/**
	 * Render the admin settings.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have access to this page.', 'marginminds-gst' ) );
		}
		$view = MMSGST_DIR . 'views/admin/admin-settings.php';
		if ( file_exists( $view ) ) {
			include $view;
		} else {
			// Fallback simple output if view missing.
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Due to some technical error page failed to render contact support', 'marginminds-gst' ) . '</h1>';
			echo '</div>';
		}
	}


	/**
	 * Render the GST Invoices list page.
	 *
	 * @return void
	 */
	public function render_invoices(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have access to this page.', 'marginminds-gst' ) );
		}

		$view = MMSGST_DIR . 'views/admin/admin-invoices.php';
		if ( file_exists( $view ) ) {
			include $view;
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Due to some technical error page failed to render contact support', 'marginminds-gst' ) . '</h1>';
			echo '</div>';
		}
	}

	/**
	 * Render the Premium Features upgrade page.
	 *
	 * @return void
	 */
	public function render_features(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have access to this page.', 'marginminds-gst' ) );
		}

		$upgrade_url = 'https://marginminds.com/';
		$view        = MMSGST_DIR . 'views/admin/admin-premium.php';

		if ( file_exists( $view ) ) {
			include $view;
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Due to some technical error page failed to render contact support', 'marginminds-gst' ) . '</h1>';
			echo '</div>';
		}
	}
}
