<?php
/**
 * Handles the VIP tab on My Account page.
 *
 * @package WC_VIP_Club
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles adding the VIP tab and displaying its content.
 */
final class WC_VIP_Club_MyAccount {

	/**
	 * Constructor.
	 *
	 * Registers hooks.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register hooks related to My Account page.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Register VIP endpoint.
		add_action( 'init', array( $this, 'register_endpoint' ) );

		// Add VIP tab to My Account menu.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_vip_tab' ), 10, 1 );

		// Add endpoint content.
		add_action( 'woocommerce_account_vip_status_endpoint', array( $this, 'display_vip_tab_content' ) );
	}

	/**
	 * Register the VIP status endpoint.
	 *
	 * @return void
	 */
	public function register_endpoint(): void {
		add_rewrite_endpoint( 'vip_status', EP_PAGES );
	}

	/**
	 * Add VIP tab to My Account menu.
	 *
	 * @param array $items My Account menu items.
	 * @return array Modified menu items.
	 */
	public function add_vip_tab( array $items ): array {
		$new_items = array();

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;

			if ( 'dashboard' === $key ) {
				$new_items['vip_status'] = esc_html__( 'VIP Club', 'wc-vip-club' );
			}
		}

		return $new_items;
	}

	/**
	 * Display VIP tab content.
	 *
	 * @return void
	 */
	public function display_vip_tab_content(): void {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			echo '<p>' . esc_html__( 'You must be logged in to view this page.', 'wc-vip-club' ) . '</p>';
			return;
		}

		$user       = get_user_by( 'id', $user_id );
		$vip_role   = get_option( WC_VIP_Club::OPTION_ROLE_SLUG, 'vip_customer' );
		$role_name  = get_option( WC_VIP_Club::OPTION_ROLE_NAME, 'VIP Customer' );
		$threshold  = (int) get_option( WC_VIP_Club::OPTION_THRESHOLD, 1000 );
		$lifetime   = wc_get_customer_total_spent( $user_id );
		$progress   = min( 100, ( $lifetime / $threshold ) * 100 );

		echo '<div class="wc-vip-wrapper">';

		// Header: Role Name only.
		printf(
			'<h2 class="wc-vip-status-header">%s</h2>',
			esc_html( $role_name )
		);

		// VIP achieved message.
		if ( in_array( $vip_role, $user->roles, true ) && $progress >= 100 ) {
			echo '<div class="wc-vip-success">';
			echo esc_html__( 'Congratulations! You have reached VIP status.', 'wc-vip-club' );
			echo '</div>';
		}

		// Accessible text-based progress.
		echo '<div class="wc-vip-progress-text">';

		$progress_color = '#d23f3f'; // red
		if ( $progress >= 50 && $progress < 100 ) {
			$progress_color = '#f7b500'; // orange
		} elseif ( $progress >= 100 ) {
			$progress_color = '#2ecc71'; // green
		}

		printf(
			'<p>%1$s <strong style="color:%2$s">%3$s</strong> / <strong>%4$s</strong> → %5$s%%</p>',
			esc_html__( 'Progress:', 'wc-vip-club' ),
			esc_attr( $progress_color ),
			esc_html( wc_price( $lifetime ) ),
			esc_html( wc_price( $threshold ) ),
			esc_html( round( $progress ) )
		);

		// Motivational message if threshold not reached.
		$remaining = max( 0, $threshold - $lifetime );
		if ( $remaining > 0 ) {
			printf(
				'<p class="wc-vip-motivation">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s = amount remaining to reach VIP */
						__( 'You need %s more to reach VIP status. Keep shopping!', 'wc-vip-club' ),
						wc_price( $remaining )
					)
				)
			);
		}

		echo '</div>'; // .wc-vip-progress-text
		echo '</div>'; // .wc-vip-wrapper
	}
}
