=== Better User Search ===
Contributors: dale3h
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=E8UETG65PQJC2
Tags: user, search, user search, admin, backend
Requires at least: 3.0
Tested up to: 4.1
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Improves the search for users in the admin significantly: Search for first name, last, email and more of users instead of only nicename.

== Description ==

This plugin is used to improve the user search functionality in the admin/backend.

WordPress by default only allows you to search for users by username/nicename. Using Better User Search, you will be able
to search by first name, last name, email address and any custom user meta field that already exists in the user meta table.

On top of that, there is an "OR" feature provided. Just include the word "or" (case-insensitive) in your search query
and the plugin will search for any user that matches at least 1 of the search terms.

"OR" Search Example:
Let's find all WooCommerce customers in Texas or Florida. To do this, first make sure that `billing_state` and `shipping_state`
are setup in the Better User Search settings. Now head to the Users page and type "TX or FL" into the search field.

It really is that simple!

== Installation ==

1. Upload the `better-user-search` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Visit 'Settings > User Search' and adjust your configuration.

== Other notes ==

Special characters like quotes are escaped, problems with the query may apper when trying to search for other non-alphanumeric characters.

== Frequently Asked Questions ==

= What is the answer to life, the universe and everything? =

42

== Screenshots ==

1. Settings page (Settings > User Search )
2. Users page

== Changelog ==

= 1.0 =
* Just getting started...

== Upgrade Notice ==

= 1.0 =
First release of the Better User Search plugin. Enjoy!
