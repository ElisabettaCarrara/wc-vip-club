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
        // Register VIP endpoint
        add_action( 'init', array( $this, 'register_endpoint' ) );

        // Add tab to My Account menu
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_vip_tab' ), 10, 1 );

        // Add endpoint content
        add_action( 'woocommerce_account_vip_status_endpoint', array( $this, 'display_vip_tab_content' ) );
    }

    /**
     * Register the VIP status endpoint
     */
    public function register_endpoint(): void {
        add_rewrite_endpoint( 'vip_status', EP_PAGES );
    }

    /**
     * Add VIP tab to My Account menu
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
     * Display VIP tab content
     */
    public function display_vip_tab_content(): void {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            echo '<p>' . esc_html__( 'You must be logged in to view this page.', 'wc-vip-club' ) . '</p>';
            return;
        }

        $user = get_user_by( 'id', $user_id );
        $vip_role = get_option( WC_VIP_Club::OPTION_ROLE_SLUG, 'vip_customer' );
        $role_name = get_option( WC_VIP_Club::OPTION_ROLE_NAME, 'VIP Customer' );
        $threshold = (int) get_option( WC_VIP_Club::OPTION_THRESHOLD, 1000 );

        $lifetime_spent = wc_get_customer_total_spent( $user_id );
        $progress = min( 100, ( $lifetime_spent / $threshold ) * 100 );

        $star_class = $progress >= 100 || in_array( $vip_role, $user->roles, true )
            ? 'wc-vip-club-star-full'
            : ( $progress >= 50 ? 'wc-vip-club-star-half' : 'wc-vip-club-star-empty' );

        echo '<div class="wc-vip-wrapper">';

        // Header: Star + Role Name
        echo '<h2 class="wc-vip-status-header">';
        echo '<span class="wc-vip-club-star ' . esc_attr( $star_class ) . '"></span>';
        echo esc_html( $role_name );
        echo '</h2>';

        // VIP achieved
        if ( in_array( $vip_role, $user->roles, true ) && $progress >= 100 ) {
            echo '<div class="wc-vip-success">';
            echo esc_html__( 'Congratulations! You have reached VIP status.', 'wc-vip-club' );
            echo '</div>';
        }

        // Accessible text-based progress
        echo '<div class="wc-vip-progress-text">';
        $progress_color = '#d23f3f'; // red
        if ( $progress >= 50 && $progress < 100 ) {
            $progress_color = '#f7b500'; // orange
        } elseif ( $progress >= 100 ) {
            $progress_color = '#2ecc71'; // green
        }

        echo '<p>';
        echo esc_html__( 'Progress:', 'wc-vip-club' ) . ' ';
        echo '<strong style="color:' . esc_attr( $progress_color ) . '">'
            . wc_price( $lifetime_spent ) . '</strong>';
        echo ' / ';
        echo '<strong>' . wc_price( $threshold ) . '</strong>';
        echo ' → ' . esc_html( round( $progress ) ) . '%';
        echo '</p>';

        $remaining = max( 0, $threshold - $lifetime_spent );
        if ( $remaining > 0 ) {
            echo '<p class="wc-vip-motivation">';
            echo sprintf(
                esc_html__( 'You need %s more to reach VIP status. Keep shopping!', 'wc-vip-club' ),
                wc_price( $remaining )
            );
            echo '</p>';
        }
        echo '</div>'; // end .wc-vip-progress-text

        echo '</div>'; // end .wc-vip-wrapper
    }
}
