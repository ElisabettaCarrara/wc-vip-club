WC VIP Club
=======================

![WC VIP Club](images/wc-vip-club.png)

**Plugin Name:** WC VIP Club  
**Plugin URI:** https://elica-webservices.it  
**Description:** Automatic VIP role assignment based on customer lifetime spending in Woo.  
**Version:** 1.1.0  
**Requires at least:** 4.9.15  
**Requires PHP:** 8.2  
**Author:** Elisabetta Carrara  
**Author URI:** https://elica-webservices.it  
**License:** GPL v2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  
**Text Domain:** wc-vip-club  
**Domain Path:** /languages  
**Tested up to:** 6.6  
**Requires Plugins:** woocommerce  
**WC requires at least:** 6.0  
**WC tested up to:** 9.5  
**Tags:** woocommerce, vip, customer-loyalty, roles, membership  
**Update URI:** https://elica-webservices.it

Automatic VIP role assignment based on customer lifetime spending in WooCommerce. Theme-safe, multisite-ready customer loyalty solution.

---

## Description

WC VIP Club automatically upgrades customers to a VIP role when they reach a configurable spending threshold. The plugin adds a VIP Club section to the account area and shows real-time progress toward VIP status on the My Account page.

This plugin is designed to be theme-safe, multisite-ready, and developer-extensible.

### Features

- **Automatic VIP role assignment** based on total customer spending
- **Configurable VIP role name** and optional advanced role slug override
- **Visual progress bar** in the My Account area
- **Native settings page** integrated with WooCommerce
- **WooCommerce standard colors** are used
- **Multisite compatible** (per-site roles and settings)
- **Hooks and filters** for third-party extensions
- **Admin preview** of VIP role configuration
- **Translation ready**

### Requirements

- WordPress 5.8 or later
- WooCommerce 6.0 or later / Classic Commerce v2 or later
- PHP 8.2 or later

---

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate "WC VIP Club" from the Plugins screen
3. Go to **WooCommerce > Settings > VIP Club**
4. Configure the VIP role name, threshold, and optional role slug

The plugin is now active.

---

## Configuration

### VIP Role Name

Defines the display name of the VIP role (for example "VIP Customer" or "Gold Member").

### Advanced Role Slug Override

Optional. If provided, this value will be used internally instead of automatically generating the slug from the role name. This is recommended for developers who want stable role identifiers.

### Spending Threshold

The total lifetime amount a customer must spend to become a VIP.

### Customer Experience

Customers will see a **"VIP Club"** tab in:

**My Account > VIP Club**

The page shows:

- Current progress toward VIP status
- Remaining amount needed to reach VIP
- Confirmation message once VIP status is achieved

### How VIP Status Is Assigned

A customer is upgraded to VIP when:

- An order reaches the "Processing" or "Completed" status
- Total lifetime spending meets or exceeds the configured threshold
- The customer does not already have the VIP role

**Note:** VIP status is not removed automatically.

---

## Frequently Asked Questions

### Does this plugin replace the Customer role?

No. The VIP role is added to the user. Existing roles are not removed.

### Can VIP users be downgraded automatically?

No. VIP status is permanent by default. Downgrades can be implemented via hooks if needed.

### Can the plugin be used without WooCommerce?

No. The plugin relies on WooCommerce order data.

---

## Hooks and Filters

### Available Filters

- `vip_club_role_name`
- `vip_club_role_slug`
- `vip_club_threshold`
- `vip_club_is_user_vip`
- `vip_club_should_upgrade_user`

### Available Actions

- `vip_club_user_upgraded`
- `vip_club_role_synced`

### Example: Prevent VIP upgrade for a specific user role

```php
add_filter( 'vip_club_should_upgrade_user', function ( $allow, $user ) {
    return ! in_array( 'blocked_vip', $user->roles, true );
}, 10, 2 );
```

---

## Multisite Support

The plugin is fully compatible with WordPress Multisite.

- VIP roles are created per site
- Settings are stored per site
- Safe for network activation
- No network-wide role pollution

---

## Translations

The plugin is fully translation-ready.

**Text domain:** `wc-vip-club`  
**Domain Path:** `/languages`

---

## Screenshots

1. WC VIP Club settings page
2. Customer progress bar in My Account
3. VIP status confirmation message

---

## Changelog

### 1.0.0

- Initial release

---

## Upgrade Notice

### 1.0.0

Initial release. No upgrade required.

---

## Roadmap

- Email notifications
- REST API support

### PRO VERSION ADD-ON (Coming Soon)

- VIP tiers (Silver, Gold, Platinum)
- Time-limited VIP memberships

---

## Contributing

Contributions, bug reports, and feature requests are welcome.

Please open an issue to discuss major changes before submitting a pull request.

---

## Credits

Developed for WooCommerce store owners and developers by Elisabetta Carrara.
