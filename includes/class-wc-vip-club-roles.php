final class WC_VIP_Club_Roles {

    public function __construct() {
        $this->register_hooks();
    }

    private function register_hooks(): void {
        add_action( 'init', array( $this, 'maybe_create_role' ) );

        add_action( 'update_option_wc_vip_club_role_name', array( $this, 'update_role_name_handler' ), 10, 2 );
        add_action( 'update_option_wc_vip_club_role_slug', array( $this, 'update_role_slug_handler' ), 10, 2 );
    }

    public function maybe_create_role(): void {
        $slug = get_option( WC_VIP_Club::OPTION_ROLE_SLUG, 'vip_customer' );
        $name = get_option( WC_VIP_Club::OPTION_ROLE_NAME, 'VIP Customer' );

        $customer_role = get_role( 'customer' );
        if ( null === get_role( $slug ) && $customer_role ) {
            add_role( $slug, $name, $customer_role->capabilities );
        }
    }

    // Handle display name change only
    public function update_role_name_handler( $old, $new ): void {
        $slug = get_option( WC_VIP_Club::OPTION_ROLE_SLUG, 'vip_customer' );
        $this->update_role_name( $slug, (string) $new );
    }

    // Handle slug change only
    public function update_role_slug_handler( $old_slug, $new_slug ): void {
        $old_slug_option = get_option( '_wc_vip_club_old_slug', $old_slug );
        $new_slug = (string) $new_slug;
        if ( $old_slug_option !== $new_slug ) {
            $name = get_option( WC_VIP_Club::OPTION_ROLE_NAME, 'VIP Customer' );
            $this->update_role_slug( (string) $old_slug_option, $new_slug, $name );
            update_option( '_wc_vip_club_old_slug', $new_slug );
        }
    }

    private function update_role_name( string $slug, string $new_name ): void {
        $role = get_role( $slug );
        if ( $role ) {
            global $wp_roles;
            if ( isset( $wp_roles->roles[ $slug ] ) ) {
                $wp_roles->roles[ $slug ]['name'] = $new_name;
                $wp_roles->role_names[ $slug ] = $new_name;
            }
        }
    }

    private function update_role_slug( string $old_slug, string $new_slug, string $new_name ): void {
        if ( empty( $old_slug ) || empty( $new_slug ) || $old_slug === $new_slug ) {
            return;
        }

        $old_role = get_role( $old_slug );
        $customer_role = get_role( 'customer' );
        $caps = $old_role ? $old_role->capabilities : ( $customer_role ? $customer_role->capabilities : array( 'read' => true ) );

        if ( ! get_role( $new_slug ) ) {
            add_role( $new_slug, $new_name, $caps );
        }

        $users = get_users( array( 'role' => $old_slug ) );
        foreach ( $users as $user ) {
            $user->remove_role( $old_slug );
            $user->add_role( $new_slug );
        }

        if ( $old_role ) {
            remove_role( $old_slug );
        }
    }
}
