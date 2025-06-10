# WordPress Content Migrator

A powerful WordPress plugin designed to migrate content from external websites into your WordPress site using CSV or Excel files, and perform advanced search and replace operations. Developed by [ANCKR](https://anckr.com).

## Description

WordPress Content Migrator allows seamless transfer of content from any website to your WordPress installation without complex programming or database knowledge. The plugin handles the entire migration process including content extraction, HTML cleaning, metadata import, and proper formatting - all through a simple spreadsheet-based workflow. Additionally, it provides powerful search and replace functionality to modify your content across the entire WordPress database. Ideal for website redesigns, content migrations, content updates, or consolidating multiple sites into a single WordPress installation.

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
- **Advanced Search and Replace**: Perform database-wide content updates with precision
- **Dry Run Mode**: Preview search and replace changes before applying them
- **Regular Expression Support**: Use powerful regex patterns for complex search operations
- **Selective Table Updates**: Choose specific database tables for search and replace operations
- **Case-Sensitive Search**: Option to perform case-sensitive or case-insensitive searches

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

## Search and Replace

### Overview
The Search and Replace feature allows you to find and replace text across your WordPress database safely and efficiently. This is particularly useful for:
- Updating URLs after site migration
- Fixing common content errors
- Replacing outdated information
- Modifying HTML or shortcode patterns
- Bulk updating specific phrases or terms

### How to Use Search and Replace

1. Navigate to Tools → Content Migrator → Search and Replace
2. Enter your search term in the "Search for" field
3. Enter the replacement text in the "Replace with" field
4. Choose your search options:
   - Case-sensitive search
   - Regular expression support
   - Select specific database tables
5. Click "Preview Changes" to see what will be affected
6. If satisfied with the preview, click "Apply Changes"

### Safety Features

- **Backup Prompt**: The plugin recommends creating a database backup before performing replacements
- **Preview Mode**: See all changes before they're applied
- **Table Selection**: Limit changes to specific database tables
- **Change Logging**: Keep track of all search and replace operations
- **Undo Capability**: Option to reverse the most recent change

### Best Practices for Search and Replace

1. **Always Backup First**: Create a complete database backup before performing any replacements
2. **Use Preview Mode**: Always preview changes before applying them
3. **Start Small**: Test your search patterns on a single table before applying to the entire database
4. **Be Specific**: Use precise search terms to avoid unintended replacements
5. **Check Regular Expressions**: If using regex, verify your patterns in preview mode
6. **Document Changes**: Keep track of major search and replace operations for future reference 
