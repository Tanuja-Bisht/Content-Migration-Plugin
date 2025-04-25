=== Content Migrator ===
Contributors: anckr
Tags: migration, content, excel, import, csv, content-scraping, website-migration
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A comprehensive WordPress content migration solution that seamlessly transfers content from any website using Excel or CSV files.

== Description ==

Content Migrator is a powerful WordPress plugin designed to simplify the process of migrating content from external websites to WordPress. The plugin handles the entire migration process automatically, from content extraction to HTML cleaning and proper formatting - all through a simple spreadsheet-based workflow.

= Key Features =

* **Multiple File Format Support**: Import content using both `.xlsx` and `.csv` files
* **Smart Content Extraction**: Automatically scrapes and extracts content from source URLs
* **Post Type Support**: Migrate content as either posts or pages
* **Metadata Import**: Preserves meta titles and descriptions for SEO
* **HTML Cleaning**: Automatically cleans and sanitizes HTML content
* **Hierarchical Structure**: Preserves parent-child relationships for pages
* **Category Handling**: Automatically assigns categories to posts based on URL structure
* **Publication Date Retention**: Extracts and preserves original publication dates for posts
* **Batch Processing**: Processes large imports in batches to prevent timeouts
* **Overwrite Protection**: Option to skip existing content to prevent duplicate entries

This plugin is ideal for website redesigns, content migrations, or consolidating multiple sites into a single WordPress installation - all without requiring complex programming or database knowledge.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/content-migrator` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Content Migrator menu item to access the plugin

== Usage ==

1. Go to the Content Migrator page in your WordPress admin
2. Download the sample Excel file to see the required format
3. Prepare your Excel file with the required columns
4. Upload your Excel file and choose whether to allow overwriting existing content
5. Review the results table for any errors or issues

== Frequently Asked Questions ==

= What columns should my Excel file have? =

Your Excel file should include these columns:
* Migrate: Set to "MIGRATE" for rows you want to process
* Menu Name: Name for menus (reference only)
* Old URL: URL of existing content to scrape
* New URL: Slug for the new page/post
* Meta Title: SEO title for the page/post
* H1: Main heading to add at the top of the content
* Page/Post Title: Title of the page/post
* Type: Either "page" or "post"

= Can I update existing content? =

Yes, you can choose to allow overwriting existing content with the same slug by checking the "Allow overwrite if slug exists" option.

== Screenshots ==

1. The Content Migrator admin interface
2. Sample Excel file format
3. Migration results table

== Changelog ==

= 1.0.0 =
* Initial release
