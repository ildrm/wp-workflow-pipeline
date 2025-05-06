# WordPress Workflow Pipeline

![WordPress Workflow Pipeline](https://img.shields.io/badge/WordPress-Plugin-blue.svg) ![Version](https://img.shields.io/badge/version-1.3.8-green.svg) ![License](https://img.shields.io/badge/license-GPLv2-blue.svg)

**WordPress Workflow Pipeline** is a powerful WordPress automation plugin designed to streamline repetitive tasks by creating dynamic, Zapier-like workflows. Whether you're fetching data from external APIs, processing it with PHP or WordPress functions, or automating content creation (e.g., posts, WooCommerce products), this plugin offers a flexible and user-friendly solution. Ideal for developers, marketers, and site owners, it simplifies data integration, task automation, and content management in WordPress.

## Features

- **Dynamic Workflow Creation**: Build multi-step pipelines with actions like data fetching, processing, and content creation.
- **Data Fetching**: Extract data from external URLs in HTML, JSON, or XML formats using XPath selectors or JSON paths.
- **Flexible Data Mapping**: Use placeholders to dynamically map data between steps for customized outputs.
- **Cron Scheduling**: Automate pipelines with WordPress cron (every 5 minutes, hourly, or daily).
- **Detailed Logging**: Monitor and debug pipelines with comprehensive logs stored in `wp-content/wwp_logs/`.
- **WooCommerce Integration**: Create or update WooCommerce products with fetched data.
- **Extensibility**: Add custom PHP functions or WordPress actions to extend functionality.
- **User-Friendly Interface**: Manage pipelines through an intuitive admin dashboard in WordPress.

## Why Choose WordPress Workflow Pipeline?

This plugin is perfect for automating tasks like:
- Syncing external data (e.g., product feeds, calendars) with WordPress posts or products.
- Creating bulk content from APIs or web scraping.
- Scheduling automated updates for dynamic content.
- Integrating third-party services with WordPress without coding.

With **WordPress Workflow Pipeline**, you save time, reduce manual work, and enhance your site's capabilities.

## Installation

1. **Download**:
   - Clone this repository: `git clone https://github.com/ildrm/wp-workflow-pipeline.git`
   - Or download the ZIP file from the [Releases](https://github.com/ildrm/wp-workflow-pipeline/releases) page.

2. **Upload**:
   - Upload the `wordpress-workflow-pipeline` folder to `/wp-content/plugins/` on your WordPress site.
   - Alternatively, install via the WordPress admin panel by uploading the ZIP file.

3. **Activate**:
   - Go to the WordPress admin dashboard, navigate to **Plugins**, and activate **WordPress Workflow Pipeline**.

4. **Configure**:
   - Access the plugin under the **Workflow Pipelines** menu in the WordPress admin.
   - Create a new pipeline, define steps, and configure settings like cron schedules and logging.

## Usage Example

### Scenario: Automate Post Creation from a Jalali Calendar
To fetch dates from a webpage and create WordPress posts for each date:

1. **Step 1: Fetch Data**
   - **Action Type**: Fetch Data
   - **Config**:
     ```json
     {
       "url": "https://example.com/jalali-calendar",
       "format": "html",
       "selectors": {"jalali_text": "//div[@class='jalali']"},
       "output_key": "jalali_data"
     }
     ```

2. **Step 2: Create Posts**
   - **Action Type**: WordPress Function (`wp_insert_post`)
   - **Input Mapping**: `{"data": "jalali_data"}`
   - **Config**:
     ```json
     {
       "function_name": "wp_insert_post",
       "parameters": {
         "post_title": "Jalali Day: {{jalali_text}}",
         "post_content": "This is about day {{jalali_text}} in the Jalali calendar.",
         "post_status": "publish",
         "post_type": "post"
       },
       "output_key": "post_ids"
     }
     ```

3. **Run Pipeline**:
   - Save and run the pipeline manually or schedule it.
   - Check logs in `wp-content/wwp_logs/pipeline_[ID].log` for debugging.

**Result**: For each fetched date (e.g., "۳۰", "۳۱"), a new post is created with the title "Jalali Day: ۳۰" and corresponding content.

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **PHP Extensions**: `DOMDocument` and `SimpleXML` for HTML/XML parsing
- **Tested Up To**: WordPress 6.6
- **Optional**: WooCommerce for product-related actions

## Debugging

- **Enable Logging**: Turn on logging in pipeline settings to track execution details.
- **View Logs**: Access logs via the admin interface or directly in `wp-content/wwp_logs/`.
- **Common Issues**:
  - **Empty Data**: Verify URL and selectors/JSON paths.
  - **Post Creation Failure**: Check for duplicate titles or invalid mappings in logs.

## Contributing

We welcome contributions! To contribute:

1. Fork the repository.
2. Create a new branch: `git checkout -b feature/your-feature`.
3. Commit your changes: `git commit -m "Add your feature"`.
4. Push to the branch: `git push origin feature/your-feature`.
5. Open a Pull Request.

Please follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) and include tests where possible.

## Support

- **Issues**: Report bugs or feature requests on the [GitHub Issues](https://github.com/ildrm/wp-workflow-pipeline/issues) page.
- **Community**: Join discussions in the [WordPress Support Forum](https://wordpress.org/support/plugin/wordpress-workflow-pipeline).
- **Contact**: Reach out via [ildrm.com](https://ildrm.com).

## Changelog

### 1.3.8
- **Fixed**: Correctly process input arrays in `wp_insert_post` to create posts for each item.
- **Improved**: Enhanced logging for placeholder mapping.

### 1.3.6
- **Added**: Dynamic placeholder mapping for flexible data processing.
- **Improved**: Removed hardcoded title/content for better configurability.
- **Fixed**: Input mapping issues causing empty posts.

### 1.0.0
- Initial release with core pipeline functionality.

## License

This plugin is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html). You are free to use, modify, and distribute it under the terms of the license.

## Credits

Developed by [Shahin Ilderemi](https://ildrm.com). Thanks to the WordPress and open-source communities for their support and feedback.

---

*Keywords*: WordPress automation, workflow plugin, Zapier for WordPress, data fetching, content automation, WooCommerce integration, WordPress cron, dynamic pipelines, WordPress development