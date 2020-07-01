=== Paid Memberships Pro - GetResponse Add On ===
Contributors: strangerstudios
Tags: pmpro, paid memberships pro, getresponse, get response, email marketing
Requires at least: 3.1
Tested up to: 5.1.1
Stable tag: .5

Sync your WordPress users and members with GetResponse lists.

If Paid Memberships Pro is installed you can sync users by membership level, otherwise all users can be synced to one or more lists.

== Description ==

Note: This plugin was developed to support an older version of the third-party API and will not work with the latest version of GetResponse. We are keeping it here for archive reasons and in case future development occurs. If you would like to add a vote to have this worked on, please use our [contact form](https://www.paidmembershipspro.com/contact/). As an alternative, you can consider using the [Zapier Integration](https://www.paidmembershipspro.com/add-ons/pmpro-zapier/).

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
= .5 - 2019-03-22 =
* BUG FIX: Making sure there is a user to update in pmprogr_pmpro_after_change_membership_level().
* BUG FIX: Avoiding errors when PMPro is deactivated.
* FEATURE: Localized strings for translation and generated a .pot file.

= .4.2 =
* BUG: Didn't include the level data in list of campaigns to subscribe a user to
* ENHANCEMENT: Incomplete API documentation

= .4.1 =
* BUG: Include MemberOrder (2nd argument) when adding hook for `pmpro_after_checkout` action

= .4 =
* BUG: Fixed a PHP Warning and improving code readability
* ENHANCEMENT: More consistent I18N support & using 'pmprogr' textdomain

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

== Upgrade Notice ==
= .5 =
* Please update to version .5 to avoid a serious bug where users could be erroneously unsubscribed from your lists.
