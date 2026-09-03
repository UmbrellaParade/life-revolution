=== Life Revolution ===
Contributors: Umbrella Parade
Tags: budgeting, savings, ledger, react
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPL-2.0-or-later

Life Revolution is the WordPress plugin package for the Umbrella Parade Life Revolution budgeting tool.

== Installation ==
1. Upload the plugin ZIP from the WordPress Plugins screen, or copy the yutori-ledger folder into wp-content/plugins.
2. Activate Life Revolution.
3. Open Life Revolution from the WordPress admin menu for private use.

Administrators can create a separate WordPress account with the "Life Revolution利用者" role. Each account sees the same app but keeps an independent private ledger.

Public users can use the GitHub Pages version at https://umbrellaparade.github.io/life-revolution/.

The optional public shortcode [life_revolution] and legacy shortcode [yutori_ledger] remain available for existing pages.

The plugin also adds a Life Revolution item to the WordPress admin menu.

== Data Storage ==
The public shortcode stores each visitor's data only in that visitor's browser. It never reads the administrator's WordPress data.

The private WordPress admin screen stores its data in the current user's WordPress user metadata. Browser-storage keys are also separated by WordPress user ID, so switching accounts on one device does not mix ledgers.

Use the app's JSON export/import controls before changing devices, browsers, or clearing browser data.

== Maintenance ==
This plugin is generated from the same React app used by the GitHub Pages version. When the app is changed, rebuild both the public app and the WordPress plugin package.

