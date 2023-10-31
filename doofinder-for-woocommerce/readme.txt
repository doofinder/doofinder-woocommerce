=== Doofinder WP & WooCommerce Search ===
Contributors: Doofinder
Tags: search, autocomplete
Version: 2.1
Requires at least: 5.6
Tested up to: 6.3.1
Requires PHP: 7.0
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin integrates the Doofinder search service with your WordPress site.

== Description ==

Doofinder provides fast, accurate results based on your website contents. Results appear in your search box at an incredible speed as the user types.

Doofinder can be installed in any website with very little configuration.

This extension allows you to easily populate the data Doofinder needs to be able to search your database and to insert the Doofinder layer script into your WordPress site.

With Doofinder you are confident that your visitors are finding what they are looking for.

These are some advantages of using Doofinder in your site:

- Instant, relevant results.
- Tolerant of misspellings.
- Search filters.
- Increases the conversion rates.
- No technical knowledge are required.
- Allows the use of labels and synonyms.
- Installs in minutes.
- Provides statistical information.
- Doofinder brings back the control over the searches in your site to you.

When users start typing in the search box, Doofinder displays the best results for their search. If users make typos, our algorithms will detect them and will perform the search as if the term were correctly typed.

Furthermore, Doofinder sorts the results displaying the most relevant first.

More info: <http://www.doofinder.com>


== Requirements==

__Important:__ To use this plugin you need to have an account at Doofinder. If you don't have one you can signup [here](http://www.doofinder.com/signup) to get your 30 day free trial period.

The minimum technical requirements are basically the same as the WordPress ones. Take a look at their [server requirements](https://docs.WordPress.com/document/server-requirements/) for more info.

== Installation ==

__Important__: If you're upgrading to v0.4.x or greater from v0.3.x or lower, deactivate the plugin and activate again to migrate settings.

Doofinder installation and activation is made [as in any other plugin](https://codex.wordpress.org/Managing_Plugins).

These are two ways you can install the plugin:

1. In the WordPress admin panel go to Plugins / Add New. Click "Upload Plugin". Choose the *.zip file containing the plugin, and click "Install Now". or...
2. Unpack the contents of the *.zip file containing the plugin to the plugins folder. In the typical WordPress installation that will be "/wp-contents/plugins" folder.

== Configuration ==

Once activated, you will see a new entry in the main menu called _Doofinder_ with two sub-menus:

- **Doofinder:** To access the main settings page of the module.

**NOTICE:** Doofinder for WordPress has built-in support for [WPML](https://wpml.org/es/). In case you are using it, ensure you've switched _context_ to one of the defined languages. In _All Languages_ context you won't be able to configure anything. You will have to configure as many search engines as languages you have in your site.

Doofinder Settings
==================

General Settings
--------------

- **API Key:** This is the secret token you use to index contents (in ML environments you can share the same key). Your API key can be found in the Doofinder Control Panel. Click on your profile name (in the header) and then on *API Keys*. Make sure you're using a _Management_ API key and not a _Search_ API key.
- **Search Engine HashID:** Id of the search engine that will index your contents. Can be found in the Doofinder Control Panel. Click on *Search Engines* in the header. Hash ID will be visible next to the name of your Search Engine. Remember to use different search engines for different languages if you're in a ML environment.
- **Update on Save:** The period of time that must elapse before the posts / products are updated after making a change.
- **JS Layer Script:** Here you can modify the Layer Javascript Code. It is required for the Javascript Layer to work.

== I have any other problem with your plugin. What can I do? ==

Just send your questions to <mailto:support@doofinder.com> and we will try to answer as fast as possible with a working solution for you.

== Changelog ==

= 2.1 =
New functionality added: Conversion pages.

= 2.0.32 =
Adjusted minimum requirements to install the plugin.

= 2.0.31 =
Fixed a bug while processing the indices normalization response.

= 2.0.30 =
Fix issues while migrating api-host.

= 2.0.29 =
Fix issues with zero prices.

= 2.0.28 =
Fix store payload in wordpress case

= 2.0.27 =
Changes required for the new functionality "Indexable customs posts types".

= 2.0.26 =
Fix cleaning logs in update script

= 2.0.25 =
Fix of several problems with migration when it is multi-language

= 2.0.24.1 =
Fix log cleaning

= 2.0.24 =
Added Log cleaning and normalization.

= 2.0.23 =
Added changes to return prices applying base location taxes.

= 2.0.22 =
Fix migration update in old clients.

= 2.0.21 =
Fix data indexing completion loading.

= 2.0.20 =
Fix issues while obtaining the intermediate image.

= 2.0.19 =
Fixed a bug while registering custom cron_schedules.

= 2.0.18 =
Fix bug that was saving non-indexable post types in the database in doofinder_update_on_save

= 2.0.17 =
Fix visual bug in Product Data Settings.

= 2.0.16 =
Added Image Size selector to Product Data Settingsa and fixed 'update on save' issue.

= 2.0.15 =
Improvements in REST custom fields.

= 2.0.14 =
Fix init issues.

= 2.0.13 =
Added custom fields settings.

= 2.0.12 =
Fixed relative image urls.

= 2.0.11 =
Added some improvements in REST API Handler

= 2.0.10 =
Fixed an issue while generating missing thumbnails and other minor bugfixes.

= 2.0.9 =
Fixed issues with price format and taxes.

= 2.0.8 =
Added button to reset credentials if you are Administrator

= 2.0.7 =
Added parent image to variant products without image in rest response

= 2.0.6 =
Added image_link to products rest response

= 2.0.5 =
Bugfix: Prices reflect the correct taxes now

= 2.0.4 =
Minor bugfix

= 2.0.3 =
Fixed update bug

= 2.0.2 =
Fix a bug while showing the indexation failed message

= 2.0.1 =
Fix a bug in settings migration

= 2.0.0 =
Merged Doofinder and Doofinder for Woocommerce into a single plugin

= 1.5.49 =
Fix custom attributes indexation

= 1.5.48 =
Remove internal search option

= 1.5.47 =
Added compatibility declaration with HPOS

= 1.5.46 =
Fixed some issues with PHP version

= 1.5.45 =
Fixed bug with woocommerce attributes

= 1.5.44 =
Fixed some issues with variable products, facets and stock.

= 1.5.43 =
Added release automation

= 1.5.42 =
Fixed some issues with language codes

= 1.5.41 =
Updated to use language country codes

= 1.5.40 =
Fixed a bug that made some images to be resized in the client's platform

= 1.5.39 =
Fixed a bug with update on save while removing the sale_price

= 1.5.38 =
Fixed a bug with multilanguage indexation

= 1.5.37 =
Restore to v1.5.35

= 1.5.36 =
Fixed a bug with multilanguage indexation

= 1.5.35 =
Fixed bug in Add to cart

= 1.5.34 =
Fixed bug in installation Wizard

= 1.5.33 =
Changed method for another more compatible with older php versions

= 1.5.32 =
Solved bug that made price to be converted into string

= 1.5.31 =
Bugfixes

= 1.5.30 =
Updated admin endpoint

= 1.5.29 =
Fixed a bug that caused unwanted error logs to be displayed

= 1.5.28 =
Fixed issues while connecting to doofinder in Setup Wizard

= 1.5.27 =
Bugfixes

= 1.5.26 =
Added sector selection step in Setup Wizard

= 1.5.25 =
Fixed jQuery noConflict issues

= 1.5.24 =
Bugfixes

= 1.5.23 =
Added Live Layer "Add to cart" compatibility

= 1.5.22 =
Fixed issue with delete attribute button

= 1.5.21 =
Simplified Installation Wizard

= 1.5.20 =
Add the option to disable update on save

= 1.5.19 =
Add placeholder image for products without an image

= 1.5.18 =
Fix missing attributes for a multilingual woocommerce stores

= 1.5.17 =
Refactor for Woocommerce market place.

= 1.5.16 =
Fix new API specs.

= 1.5.15 =
More fixes in API indexation.

= 1.5.13 =
More fixes in special chars indexed through api.
Fixed some PHP warnings.

= 1.5.12 =
Fix special chars indexed through api.
Fix group_id and df_group_id sent as string.

= 1.5.11 =
Fix problem on delting products.

= 1.5.10 =
Fix variants indexing. Fix indexing taking too long time.

= 1.5.9 =
Extend API Exception logs

= 1.5.8 =
Set settings form always visible

= 1.5.7 =
Fix autoloader conflicts

= 1.5.6 =
Add query params hooks filter

= 1.5.5 =
Downgrade PHP version requirements

= 1.5.4 =
Remove uninstall hook

= 1.5.3 =
Updated PHP version

= 1.5.2 =
Bump setup wizard status info in config endpoint.

= 1.5.1 =
Update dependencies. Migrate module settings. Remove module settings on uninstall.

= 1.5.0 =
Prefix dependencies to prevent conflicts with other plugins. Fixed bug in Setup Wizard regarding saving API urls.

= 1.4.3 =
Enforce URL protocol when required.

= 1.4.2 =
Fix skip setup wizard and welcome button height.

= 1.4.1 =
Better variants indexing. Better logging. Bugfixes for categories in both API and feed indexing.

= 1.4.0 =
Updated Doofinder library to the latest version.

= 1.3.17 =
Properly export variants description when using short description attribute.

= 1.3.16 =
Fix problem with bulk API response processing.

= 1.3.15 =
Decode HTML entities when exporting categories.

= 1.3.14 =
Fixed problems with custom fields and categories.

= 1.3.13 =
Revert changes introduced in previous version due to indexing problems.

= 1.3.12 =
Fixed problems with custom fields and categories.

= 1.3.11 =
Fix problem with categories.

= 1.3.10 =
Expand logs, support for php v7.0, additional error msgs for users.

= 1.3.9 =
Fixed problem creating items in temporary indices via API.

= 1.3.8 =
Fixed SQL query to get ids. Expanded logs for debugging.

= 1.3.7 =
Fix posts per batch indexing limit when split variable active

= 1.3.6 =
Extended logging.

= 1.3.5 =
Fixed bug with variants indexing (#48)

= 1.3.4 =
Updated lib error classes for better logging.

= 1.3.3 =
Fixed problem during setup wizard for users with no search engines.

= 1.3.2 =
Fixed some UX bugs. Allow editing some settings.

= 1.3.1 =
Fix problem installing via setup wizard.

= 1.3.0 =
Big refactor to use our new indexing API (v2). New automatic setup wizard.

= 1.2.22 =
Add support for image sizes.

= 1.2.21 =
Check for existance of post_type before checking for its value.

= 1.2.20 =
Fix separation of multiple value attributes.

= 1.2.19 =
Bugfixes.

= 1.2.18 =
Only load variations for loaded products, instead of all.

= 1.2.17 =
Add some debugging information.

= 1.2.16 =
Export images in thumbnail size instead of full size.

= 1.2.15 =
Add the choice of custom meta fields to settings.
Use variations from parent on feed.

= 1.2.14 =
Fix inconsitence between internal search and JS Layer

= 1.2.13 =
Fix issues.

= 1.2.12 =
Fix issues.

= 1.2.11 =
Fix issues.

= 1.2.10 =
Minor changes.

= 1.2.9 =
Added support for banners in search results.

= 1.2.8 =
Minor compatibility fixes.

= 1.2.7 =
Updated price retrieving functions. Correctly handle custom attributes added at product level. Multisite support.

= 1.2.6 =
Handle taxonomy based catalog visibility for WooCommerce 3+.

= 1.2.5 =
Just added WooCommerce version checks.

= 1.2.4 =
Fixed version number.

= 1.2.3 =
Products with a visibility attribute but no specific value (visible by default in WooCommerce) are now exported in the data feed.

= 1.2.2 =
Added custom product attributes options to attribute selection fields. Now empty fields are not exported, saving space in the data feed.

= 1.2.1 =
Fixed bug that prevented products with no explicit visibility set from being exported in the data feed.

= 1.2.0 =
Added Export Product Tags feature.

= 1.0.3 =
More backwards compatibility with PHP 5.3.

= 1.0.2 =
Added backwards compatibility with PHP 5.3.

= 1.0.1 =
Fixed bug that could break the page layout when using server search integration.

= 1.0 =
Plugin built from the ground up. Added feed pagination, custom attributes, WPML support and more.

= 0.1.6 =
Fixed issue with taxes. Now prices are exported with the same taxes configuration the store uses when displaying products.

= 0.1.5 =
Fixed issue with unescaped characters in the XML.

= 0.1.4 =
Bugfixes.

= 0.1.3 =
Bugfixes.

= 0.1.2 =
Some bugfixes. Improved feed generation. Fixed a weird error with URL routes.

= 0.1 =
First usable version.

== Upgrade Notice ==

= 1.0 =

This version is recommended for all new and existing users.
