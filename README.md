# Hide Product Category Archives

![License](https://img.shields.io/github/license/SteveKinzey/hide-product-category-archives?style=flat-square)
![Latest Release](https://img.shields.io/github/v/release/SteveKinzey/hide-product-category-archives?style=flat-square)
![Downloads](https://img.shields.io/github/downloads/SteveKinzey/hide-product-category-archives/total?style=flat-square)
![Last Commit](https://img.shields.io/github/last-commit/SteveKinzey/hide-product-category-archives?style=flat-square)
![Issues](https://img.shields.io/github/issues/SteveKinzey/hide-product-category-archives?style=flat-square)

Adds a per-product-category checkbox in WooCommerce that redirects visitors away from that product category archive to the Shop page, while keeping products visible via other categories, search, and facets.

## Features
- Checkbox on Products → Categories (add/edit)
- “Hidden archive” column on category list with one-click toggle
- 301 redirect for hidden product category archives
- Safe with FacetWP + Elementor (does not alter product visibility)
- Uninstall cleanup included

## Usage
1. Activate the plugin
2. Go to Products → Categories
3. Edit a category
4. Check “Hide category archive (redirect to Shop)”
5. Save

## Notes
- Redirect target is the WooCommerce Shop page.
- This does **not** remove products from the catalog.
- If a category is hidden, its archive URL redirects, including paginated archives.

## License
GPL-2.0-or-later

## Author
Stephen Kinzey, Ph.D.  
https://sk-america.com