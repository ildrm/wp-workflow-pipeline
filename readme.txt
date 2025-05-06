=== WordPress Workflow Pipeline ===
Contributors: shahinild
Tags: automation, workflow, pipeline, zapier, integration, wordpress, cron, data-fetching, post-creation
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 1.3.8
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A Zapier-like automation plugin for WordPress to create dynamic workflows for data fetching, processing, and content creation.

== Description ==

**WordPress Workflow Pipeline** is a powerful automation plugin that enables you to create dynamic workflows in WordPress, similar to Zapier. It allows you to fetch data from external sources (HTML, JSON, XML), process it using PHP or WordPress functions, and create content like posts or WooCommerce products. With a user-friendly interface, you can define multi-step pipelines, map data dynamically, and schedule tasks using WordPress cron.

### Key Features
- **Dynamic Workflows**: Create multi-step pipelines with actions like fetching data, executing PHP/WordPress/WooCommerce functions, and more.
- **Data Fetching**: Extract data from external URLs using HTML/XML selectors or JSON paths.
- **Dynamic Mapping**: Map data between steps using placeholders for flexible content creation.
- **Cron Scheduling**: Schedule pipelines to run automatically (every 5 minutes, hourly, or daily).
- **Logging**: Detailed logs for debugging and monitoring pipeline execution.
- **WooCommerce Integration**: Create or update products with fetched data.
- **Extensible**: Easily extend with custom PHP functions or additional WordPress actions.

This plugin is ideal for developers, marketers, and site owners who need to automate repetitive tasks, sync external data with WordPress, or streamline content creation.

== Installation ==

1. **Download and Install**:
   - Upload the `wordpress-workflow-pipeline` folder to the `/wp-content/plugins/` directory.
   - Alternatively, install the plugin through the WordPress admin interface by searching for "WordPress Workflow Pipeline".

2. **Activate**:
   - Activate the plugin through the 'Plugins' menu in WordPress.

3. **Configure**:
   - Navigate to the "Workflow Pipelines" menu in the WordPress admin dashboard.
   - Create a new pipeline, define steps (e.g., fetch data, create posts), and configure settings like cron schedules and logging.

4. **Run**:
   - Save and run your pipeline manually or schedule it to run automatically.

== Frequently Asked Questions ==

= What types of data sources can I use with this plugin? =
You can fetch data from any publicly accessible URL that returns HTML, JSON, or XML. Use XPath selectors for HTML/XML or JSON paths for JSON data.

= Can I schedule pipelines to run automatically? =
Yes, you can schedule pipelines to run every 5 minutes, hourly, or daily using WordPress cron. Manual execution is also available.

= Does this plugin support WooCommerce? =
Yes, it includes actions to create or update WooCommerce products, making it ideal for syncing product data from external sources.

= How do I debug issues with my pipeline? =
Enable logging in the pipeline settings. Logs are stored in `wp-content/wwp_logs/` and can be viewed from the admin interface.

= Can I extend the plugin with custom actions? =
Yes, you can add custom PHP functions or integrate additional WordPress/WooCommerce actions by modifying the plugin's code or using hooks.

= What are the server requirements? =
The plugin requires PHP 7.4 or higher, WordPress 5.0 or higher, and the DOMDocument and SimpleXML PHP extensions for HTML/XML parsing.

== Screenshots ==

1. **Pipeline Dashboard**: View, edit, run, or delete pipelines from the admin interface.
2. **Pipeline Editor**: Create and configure multi-step workflows with dynamic data mapping.
3. **Log Viewer**: Access detailed logs for debugging pipeline execution.

== Changelog ==

= 1.3.8 =
* Fixed: Correctly process input data arrays in wp_insert_post to create individual posts for each item.
* Improved: Enhanced logging for placeholder mapping in wp_insert_post.

= 1.3.6 =
* Added: Dynamic placeholder mapping for flexible data processing.
* Improved: Removed hardcoded title/content modifications for better configurability.
* Fixed: Input mapping issues causing empty post creation.

= 1.0.0 =
* Initial release with core pipeline functionality, data fetching, and WordPress/WooCommerce actions.

== Upgrade Notice ==

= 1.3.8 =
This update fixes a critical issue where only one post was created from multiple input items. Update immediately to ensure all fetched data is processed correctly.

== Support ==

For support, please visit the [WordPress Support Forum](https://wordpress.org/support/plugin/wordpress-workflow-pipeline) or contact the author at [ildrm.com](https://ildrm.com).

== License ==

This plugin is licensed under the GPLv2 or later. You are free to use, modify, and distribute it under the terms of the license.

== Credits ==

Developed by Shahin Ilderemi. Special thanks to the WordPress community for feedback and contributions.