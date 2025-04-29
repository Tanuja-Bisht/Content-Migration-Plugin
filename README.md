# WordPress Content Migrator

A powerful WordPress plugin designed to migrate content from external websites into your WordPress site using CSV or Excel files. Developed by [ANCKR](https://anckr.com).

## Description

WordPress Content Migrator allows seamless transfer of content from any website to your WordPress installation without complex programming or database knowledge. The plugin handles the entire migration process including content extraction, HTML cleaning, metadata import, and proper formatting - all through a simple spreadsheet-based workflow. Ideal for website redesigns, content migrations, or consolidating multiple sites into a single WordPress installation.

## Features

- **Multiple File Format Support**: Import content using both `.xlsx` and `.csv` files
- **Smart Content Extraction**: Automatically scrapes and extracts content from source URLs
- **Post Type Support**: Migrate content as either posts or pages
- **Metadata Import**: Preserves meta titles and descriptions for SEO
- **HTML Cleaning**: Automatically cleans and sanitizes HTML content
- **Hierarchical Structure**: Preserves parent-child relationships for pages
- **Category Handling**: Automatically assigns categories to posts based on URL structure
- **Publication Date Retention**: Extracts and preserves original publication dates for posts
- **Batch Processing**: Processes large imports in batches to prevent timeouts
- **Overwrite Protection**: Option to skip existing content to prevent duplicate entries

## How It Works

### 1. File Preparation

Create a CSV or Excel file with the following columns:
- `Migrate`: Mark rows with "MIGRATE" to include them in the import
- `Menu Name`: For menu integration (optional)
- `Old URL`: The source URL to scrape content from
- `New URL`: The desired URL path on your WordPress site
- `Meta Title`: SEO title for the page/post
- `H1`: The main heading for the content
- `Page/Post Title`: The title that appears in admin and menus
- `Type`: Either "page" or "post"

### 2. Import Process

1. Upload your Excel/CSV file through the plugin interface
2. The plugin processes each row marked with "MIGRATE"
3. For each row, the plugin:
   - Scrapes content from the source URL
   - Extracts main content, meta descriptions, and publication dates
   - Cleans and formats HTML content
   - Creates or updates posts/pages in WordPress
   - Sets meta fields, categories, and parent relationships

### 3. Content Processing

The plugin uses multiple strategies to extract high-quality content:
- Identifies and extracts content from common container elements
- Removes navigation, headers, footers, and other non-content elements
- Preserves important HTML structure while removing unnecessary attributes
- Applies WordPress safety standards to prevent security issues

## Usage Instructions

1. Install and activate the plugin
2. Go to Tools → Content Migrator
3. Prepare your CSV/Excel file following the required format
4. Upload your file and choose whether to allow overwriting existing content
5. Start the migration process
6. View the migration results and any error messages

## Best Practices

- **Test Small Batches**: Start with a few items to test before large migrations
- **Review Content**: Always review imported content for quality and formatting
- **Backup First**: Create a backup of your WordPress site before large imports
- **Check URLs**: Ensure your source URLs are accessible from your server
- **Mind Resources**: Large migrations require sufficient server resources and time

## Troubleshooting

- **Empty Content**: If content appears empty, check if the source URL is accessible and doesn't block scraping
- **403 Errors**: After migration, flush permalinks if you encounter 403 errors
- **Timeout Issues**: For large files, try splitting into smaller batches
- **Formatting Problems**: Some complex layouts may require manual adjustment after import

## Developer Notes

The plugin includes extensive error logging to help diagnose issues. Check your server's error log for messages prefixed with "MIGRATION:" for detailed information about the import process.

## Sample File

Use the "Generate Sample" button to download a sample CSV file with the correct format for your migration. 