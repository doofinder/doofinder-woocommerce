=== Doofinder WP & WooCommerce Search ===
Contributors: Doofinder
Tags: search, autocomplete
Version: 2.2.15
Requires at least: 5.6
Tested up to: 6.3.1
Requires PHP: 7.0
Stable tag: 2.2.15
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

== Frequently Asked Questions ==

= I have problems with your plugin. What can I do? =
Just send your questions to <mailto:support@doofinder.com> and we will try to answer as fast as possible with a working solution for you.

= How can I report security bugs? =
You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/doofinder-for-woocommerce)

== Changelog ==

= 2.2.15 =
Added some error logs to help debugging indexing errors while obtaining images.

= 2.2.14 =
Improved getting pages images

= 2.2.13 =
Improved add to cart response handling.

= 2.2.12 =
Clarify behaviour of update on save configuration, hide update now button when disabled.

= 2.2.11 =
Fixed a bug related to posts/pages title encoding

= 2.2.10 =
Changed stable tag information in the readme.txt
Escaped / as // to prevent issues in the faceted search

= 2.2.9 =
Changed log directory to /WP_CONTENT_FOLDER/uploads/doofinder-logs to avoid permission issues
Fixed a bug in Force Normalization when the language code has language and country (e.g. pt-PT)

= 2.2.8 =
Fixed a bug excluding the attributes of the variants of the parent product

= 2.2.7 =
Fixed a bug that caused some stores to be created without a name.
Exclude the attributes of the variants of the parent product

= 2.2.6 =
Added minor improvements.

= 2.2.5 =
Fixed endpoint registration issue, added checks in Thumbnails class to suppress warnings, and implemented handling to ignore invalid WooCommerce products during indexing.

= 2.2.4 =
Restore the use of credentials api

= 2.2.3 =
Fix an issue that happened when a custom attribute had nested arrays without the key "name" deleting the whole custom attribute
Get the sale price even when WC_Product->get_sale_price fails.

= 2.2.2 =
Fix an issue while setting search engine hashid and some notice styles issues.

= 2.2.1 =
Fix some migrate issues.

= 2.2.0 =
Refactoring the code and applying the calls to the new service.

= 2.1.18 =
Prevent stock_status field removal from the final response

= 2.1.17 =
Fixes some issues detected if it is used along with WPML plugin

= 2.1.16 =
Fixes wrongly decoded HTML entities for custom attributes (e.g. &amp; instead of &)


= 2.1.15 =
Improve JS and CSS secure load

= 2.1.14 =
Remove filter indexation field

= 2.1.13 =
Fix typo.

= 2.1.12 =
Added stock_status to custom_attributes reserved field names.

= 2.1.11 =
Fix renamed metadatafields and plugin attributes fields

= 2.1.9 =
Add basic attributes and plugin attributes to response
Fix corrupts images in custom endpoint
Improve XSS secure

= 2.1.8 =
Fix possible XSS issue

= 2.1.7 =
Fix problem with product attributes with taxonomy

= 2.1.6 =
Add permission_callback in API endpoints
Fix renamed custom attributes

= 2.1.5 =
Added FAQ section.

= 2.1.4 =
Check if regular price is empty

= 2.1.3 =
Set product price correctly and check if product has a corrupted image

= 2.1.2 =
Added initial indexation status check timeout.

= 2.1.1 =
Update on save refactor and add fields tu custom endpoints

= 2.1.0 =
New internal endpoints to obtain products, posts, pages and custom items.
Refactor in custom attributes management.
Secure authentication via token in headers.

= 2.0.34 =
Improved security in Ajax calls.

= 2.0.33 =
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
