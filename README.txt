=== Ajax Load More for Users ===

Contributors: dcooney, connekthq
Author: Darren Cooney
Author URI: https://connekthq.com/
Plugin URI: https://connekthq.com/ajax-load-more/extensions/users/
Donate link: https://connekthq.com/donate/
Tags: ajax load more, users, wordpress users, infinite scroll, lazy load users, WP_User_Query
Requires at least: 4.0
Tested up to: 6.1
Stable tag: 1..0
License: GPLv2 or later
License URI: http://gnu.org/licenses/gpl-2.0.html

Ajax Load More extension that adds compatibility for infinite scrolling WordPress users via WP_User_Query.

== Description ==

Query and display a list of WordPress users by role using a WP_User_Query and Ajax Load More.

[View Example](https://connekthq.com/plugins/ajax-load-more/examples/users/)

= Shortcode Parameters =

The following Ajax Load More shortcode parameters are available when the Advanced Custom Fields extension is activated.

*   **users** - Enable compatibility with Users. (true/false)
*   **users_role** - The user role to display.
*   **users_include	** - Show specific users.
*   **users_exclude** - Exclude specific users.
*   **users_per_page** - The number of users to display with each query.
*   **users_order** - The order of the displayed users.
*   **users_orderby** - Sort retrieved users by parameter.

= Example Shortcode =

    [ajax_load_more users="true" users_role="Subscriber" users_per_page="2"]


== Frequently Asked Questions ==


== Screenshots ==


== Installation ==

= Uploading in WordPress Dashboard =
1. Navigate to the 'Add New' in the plugins dashboard
2. Navigate to the 'Upload' area
3. Select `ajax-load-more-for-users.zip` from your computer
4. Click 'Install Now'
5. Activate the plugin in the Plugin dashboard

= Using FTP =
1. Download `ajax-load-more-users.zip`.
2. Extract the `ajax-load-more-for-users` directory to your computer.
3. Upload the `ajax-load-more-for-users` directory to the `/wp-content/plugins/` directory.
4. Ensure Ajax Load More is installed prior to activating the plugin.
5. Activate the plugin in the WP plugin dashboard.


== Changelog ==

= 1.0 - January 5, 2023 =
* Initial release.


== Upgrade Notice ==
* None
