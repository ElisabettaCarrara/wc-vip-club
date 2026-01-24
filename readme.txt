=== WC VIP Club ===
Contributors: elisabettacarrara
Tags: woocommerce, vip, loyalty, customer-role, spending
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.2
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatic VIP role assignment based on customer lifetime spending in WooCommerce. Includes a visual progress tracker in the My Account area.

== Description ==

WC VIP Club monitors customer spending and automatically upgrades your most loyal shoppers to a VIP role. It provides a visual progress tracker in the "My Account" area, gamifying the shopping experience to encourage repeat purchases.

This plugin is designed to be theme-safe, multisite-ready, and developer-extensible.

= User Guide =

1. Admin Setup: Navigate to WooCommerce > Settings > VIP Club. Set your "VIP Role Name" and the "Spending Threshold" required to reach it.
2. The Customer Experience: Customers see a new "VIP Club" tab in their dashboard showing a visual progress bar of their lifetime spend versus the goal.
3. Automatic Promotion: When an order is marked as Processing or Completed, the plugin checks if the threshold is met and instantly upgrades the user role.

= Key Features =

* Automatic VIP role assignment based on total customer spending.
* Configurable VIP role name and optional role slug override.
* Visual progress bar in the My Account area using standard WooCommerce colors.
* Native settings page integrated into WooCommerce.
* Multisite compatible (per-site roles and settings).
* Translation ready.

= Technical Standards =

* Fully compliant with WordPress Coding Standards (WPCS).
* Optimized for PHP 8.2+ with strict typing and property types.
* Security hardened with late-escaping and nonce verification.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate "WC VIP Club" through the 'Plugins' menu in WordPress.
3. Configure your settings under WooCommerce > Settings > VIP Club.

== Frequently Asked Questions ==

= Does this plugin replace the Customer role? =

No. The VIP role is added to the user's existing roles.

= Can VIP users be downgraded automatically? =

No. VIP status is permanent by default to ensure customer satisfaction. Downgrades can be implemented via developer hooks if needed.

= Can the plugin be used without WooCommerce? =

No. The plugin relies on WooCommerce order data and hooks.

== Screenshots ==

1. WC VIP Club settings page showing threshold configuration.
2. Customer progress bar and star icons in the My Account dashboard.
3. VIP status confirmation message for achieved goals.

== Changelog ==

= 1.2.0 =
* Enhancement: Full refactor for WordPress Coding Standards (WPCS) compliance.
* Update: Added PHP 8.2 strict typing and return type hints.
* Improvement: Enhanced My Account tab reordering and documentation.

= 1.1.0 =
* Added support for role slug overrides.
* Initial My Account tab logic.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.0 =
This version improves security and performance for PHP 8.2 environments. Highly recommended for all users.

== Developer Hooks ==

= Filters =
* `vip_club_role_name` - Modify the display name of the VIP role.
* `vip_club_role_slug` - Modify the slug used for the role.
* `vip_club_threshold` - Dynamically change the spending threshold.

= Actions =
* `vip_club_role_synced` - Fires after the VIP role is updated in the database.
* `vip_club_customer_promoted` - Fires when a customer is upgraded to VIP.

= Code Example: Prevent upgrade for specific roles =
`
add_filter( 'vip_club_should_upgrade_user', function ( $allow, $user ) {
    return ! in_array( 'blocked_role', (array) $user->roles, true );
}, 10, 2 );
`

== Multisite Support ==

The plugin is fully compatible with WordPress Multisite. VIP roles and settings are handled per-site to prevent network-wide role pollution.
