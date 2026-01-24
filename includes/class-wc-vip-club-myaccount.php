<?php
/**
 * Handles the VIP tab on My Account page.
 *
 * @package WC_VIP_Club
 */

defined( 'ABSPATH' ) || exit;

final class WC_VIP_Club_MyAccount {

    public function __construct() {
        $this->register_hooks();
    }

    private function register_hooks(): void {
        // Add tab to My Account menu
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_vip_tab' ), 10, 1 );

        // Add endpoint content
        add_action( 'woocommerce_account_vip_status_endpoint', array( $this, 'display_vip_tab_content' ) );
    }

    /**
     * Add VIP tab to My Account menu
     */
    public function add_vip_tab( array $items ): array {
        $user = wp_get_current_user();
        $vip_role = get_option( WC_VIP_Club::OPTION_ROLE_SLUG, 'vip_customer' );

        if ( in_array( $vip_role, $user->roles, true ) || ! empty( $user->ID ) ) {
            $items['vip_status'] = esc_html__( 'VIP Status', 'wc-vip-club' );
        }

        return $items;
    }

    /**
     * Display VIP tab content
     */
    public function display_vip_tab_content(): void {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        $user = get_user_by( 'id', $user_id );
        $vip_role = get_option( WC_VIP_Club::OPTION_ROLE_SLUG, 'vip_customer' );
        $threshold = (int) get_option( WC_VIP_Club::OPTION_THRESHOLD, 1000 );
        $lifetime_spent = wc_get_customer_total_spent( $user_id );

        $progress = min( 100, ( $lifetime_spent / $threshold ) * 100 );

        if ( $progress >= 100 ) {
            $star_class = 'vip-star-filled';
        } elseif ( $progress >= 50 ) {
            $star_class = 'vip-star-half';
        } else {
            $star_class = 'vip-star-empty';
        }

        $role_name = get_option( WC_VIP_Club::OPTION_ROLE_NAME, 'VIP Customer' );
        echo '<div class="wc-vip-status-header">';
        echo '<span class="vip-star ' . esc_attr( $star_class ) . '"></span> ';
        echo esc_html( $role_name );
        echo '</div>';

        if ( in_array( $vip_role, $user->roles, true ) ) {
            echo '<p>' . esc_html__( 'Congratulations! You have reached VIP status.', 'wc-vip-club' ) . '</p>';
        } else {
            $remaining = max( 0, $threshold - $lifetime_spent );
            echo '<div class="wc-vip-progress-container">';
            echo '<div class="wc-vip-progress-bar" style="width:' . esc_attr( $progress ) . '%"></div>';
            echo '</div>';
            echo '<p>' . sprintf(
                esc_html__( 'You need %s more to reach VIP status.', 'wc-vip-club' ),
                wc_price( $remaining )
            ) . '</p>';
        }
    }
}
