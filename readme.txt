=== Hide Product Category Archives ===
Contributors: sk-america
Tags: woocommerce, product category, redirect, taxonomy
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a per-product-category checkbox to hide a WooCommerce product category archive by redirecting it to the Shop page, while keeping products visible elsewhere.

== Description ==
This plugin lets you hide specific WooCommerce product category archive pages by redirecting visitors to the Shop page. It does not remove products from the catalog—products remain visible via other categories, search, and facets.

== Installation ==
1. Upload the plugin folder to /wp-content/plugins/
2. Activate the plugin
3. Go to Products -> Categories and edit a category
4. Enable “Hide category archive (redirect to Shop)”
5. Save

== Frequently Asked Questions ==
= Does this remove products from the shop? =
No. It only redirects the category archive page.

= Does it work with FacetWP? =
Yes. It does not change product visibility; it only blocks direct access to the category archive.

== Changelog ==
= 1.1.0 =
* Added Plugins page Settings link
* Added “Hidden archive” admin column with one-click toggle
* Added uninstall cleanup
* Added i18n headers

= 1.0.0 =
* Initial release