=== Paid Memberships Pro - GetResponse Add On ===
Contributors: strangerstudios
Tags: pmpro, paid memberships pro, getresponse, get response, email marketing
Requires at least: 3.1
Tested up to: 4.4
Stable tag: .3

Sync your WordPress users and members with GetResponse lists.

If Paid Memberships Pro is installed you can sync users by membership level, otherwise all users can be synced to one or more lists.

== Description ==

Sync your WordPress users and members with GetResponse lists.

If Paid Memberships Pro is installed you can sync users by membership level, otherwise all users can be synced to one or more lists.


== Installation ==

1. Upload the `pmpro-getresponse` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. The settings page is at Settings --> PMPro GetResponse in the WP dashboard.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-getresponse/issues

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium support site at http://www.paidmembershipspro.com for more documentation and our support forums.

== Changelog ==
= .3 =
* BUG: Fixed some warnings and notices.
* ENHANCEMENT: Added $campaign_user as the second parameter to the pmpro_getresponse_custom_fields filter.
* ENHANCEMENT: Updated the GetResponse library.

= .2 =
* Added option to better manage campaign subscriptions during level changes.

= .1.3 =
* Fixed "Get one." link. Now goes to GetResponse instead of MailChimp
* Now passing display_name if user does not have first and last names filled out.

= .1.2 =
* Removed MailChimp references in readme.

= .1.1 =
* Removed some unused code.
* Fixed ping check on the settings page.

= .1 =
* Initial version.
