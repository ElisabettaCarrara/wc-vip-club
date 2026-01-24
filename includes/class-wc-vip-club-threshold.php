<?php
/**
 * Handles the VIP spending threshold logic.
 *
 * @package WC_VIP_Club
 */

defined( 'ABSPATH' ) || exit;

final class WC_VIP_Club_Threshold {

    public function __construct() {
        $this->register_hooks();
    }

    /**
     * Register hooks related to order completion and user updates.
     */
    private function register_hooks(): void {
        // When an order is completed, check if the user reaches VIP
        add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_assign_vip_role' ), 10, 1 );
    }

    /**
     * Check if a user crosses the VIP threshold and assign role if needed.
     *
     * @param int $order_id WooCommerce order ID.
     */
    public function maybe_assign_vip_role( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $user_id = $order->get_user_id();
        if ( ! $user_id ) {
            return; // Guest checkout, ignore
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return;
        }

        $threshold = (int) get_option( WC_VIP_Club::OPTION_THRESHOLD, 1000 );
        $vip_role  = get_option( WC_VIP_Club::OPTION_ROLE_SLUG, 'vip_customer' );

        // Calculate lifetime spent
        $lifetime_spent = wc_get_customer_total_spent( $user_id );

        // Assign VIP role if threshold crossed
        if ( $lifetime_spent >= $threshold && ! in_array( $vip_role, $user->roles, true ) ) {
            $user->add_role( $vip_role );
        }
    }
}
