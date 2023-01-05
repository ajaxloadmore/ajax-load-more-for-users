=== Ajax Load More: Users ===

Contributors: dcooney
Author: Darren Cooney
Author URI: https://connekthq.com/
Plugin URI: https://connekthq.com/plugins/ajax-load-more/add-ons/users/
Requires at least: 3.6.1
Tested up to: 5.7
Stable tag: trunk
Homepage: https://connekthq.com/
Version: 1.1.2


== Copyright ==
Copyright 2019 Darren Cooney

This software is NOT to be distributed, but can be INCLUDED in WP themes: Premium or Contracted.
This software is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.


== Description ==

= The Users add-on will provide addintional functionality for lazy loading WordPress Users. =

Query and display a list of WordPress users by role using a WP_User_Query and Ajax Load More.

http://connekthq.com/plugins/ajax-load-more/add-ons/users/

== Installation ==

= Uploading in WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Navigate to the 'Upload' area
3. Select `ajax-load-more-users.zip` from your computer
4. Click 'Install Now'
5. Activate the plugin in the Plugin dashboard

= Using FTP =

1. Download `ajax-load-more-users.zip`.
2. Extract the `ajax-load-more-users` directory to your computer.
3. Upload the `ajax-load-more-users` directory to the `/wp-content/plugins/` directory.
4. Ensure Ajax Load More is installed prior to activating the plugin.
5. Activate the plugin in the WP plugin dashboard.

== Changelog ==

= 1.1.2 - March 31, 2021 =
* FIX - Fixed issue with `include` and `exclude` users by ID not working. This is because the IDs were not being passed in as an array.


= 1.1.1 - August 6, 2019 =
* Added new filter `alm_users_query_args_$id` filter to modify the core `WP_User_Query` used by Ajax Load More. [Docs](https://connekthq.com/plugins/ajax-load-more/docs/add-ons/users/).


= 1.1.0 - May 6, 2019 =
* UPGRADE NOTICE - This update requires Ajax Load More 5.1+.
* NEW - Users add-on now uses the REST API for Ajax queries. This change can be reverted in ALM Settings.
* NEW - Added support for Paging + Users addon.
* FIX - Fixed fatal error that can occur if core Ajax Load More is deactivated and the add-on remains active.


= 1.0.2 - November 3, 2018 =
* NEW - Added support for multiple user roles.
* NEW - Added support for custom field (Meta_Query) user queries.


= 1.0.1 - September 19, 2018 =
* Update - Added new return function to get total users found in `User_Query`.


= 1.0 - November 16, 2017 =
* Initial Release.
