# GDPR Cache Scripts & Styles

Free WordPress plugin that greatly enhances privacy of your website by embedding external scripts and styles.

## Requirements

* WP 5.8 or higher
* PHP 7.4 or higher

## Installation Instructions

1. Use the [Download ZIP](https://github.com/divimode/gdpr-cache-script-styles/archive/refs/heads/main.zip) button in the top-right area to download the plugin
2. Visit your **wp-admin > Plugins > Add New** page
3. Hit the **Upload Plugin** button at the top of the page
4. Upload the zip file
5. Activate the plugin "GDPR Cache Scripts & Styles"

Or: 

Install via your WordPress > Plugins page. Search for the plugin "GDPR Cache Scripts & Styles", or download it from https://wordpress.org/plugins/gdpr-cache-scripts-styles/

## Usage

One-click solution to automatically serve external assets from your local website.

No configuration and no coding required - activate the plugin and forget about it.

### Disclaimer

This plugin does not provide any guarantees of making your website GDPR-compliant. As a website operator, you are always responsible to verify if this plugin works for you and collect consent for external scripts before loading them in the visitor's browser.

### How it works

> **Short**: External files are downloaded to your WordPress installation (into the uploads-folder) and then served from there.

**More details**:

The plugin scans every URL that is enqueued via `wp_enqueue_script()` and `wp_enqueue_style()`. When detecting external URL, that file is saved to your uploads-folder and served from there.

It also scans the contents of CSS files for external dependencies and also saves those files to your uploads-folder!

Heads-up: For technical reasons, we cannot scan the contents of JS files for such dependencies - JS files can always inject external assets

#### No Output Buffer

This plugin does not add any "output buffering" but scans the URLs which are enqueued via recommended WordPress functions.

As a result, *GDPR Cache Scripts & Styles* has practically no performance impact on your response time, no matter how big your website is.

#### Background worker

To speed up your websites loading time, all assets are downloaded in a background process: When a new asset is detected, or a cached file expires, a worker-task is enqueued.

The queue is then processed in an asynchronous process; while the queue is processed, your website could still serve the external assets for a while - usually the queue is processed within one or two minutes.

#### Options page

You'll find the plugin options page at Tools > GDPR Cache. On that page you can invalidate all assets and see a list of the entire cache.

When you deactivate the plugin, the entire cache is purged (all files are deleted and relevant DB values are reset)

### Tested with

We've tested this plugin with the following themes and plugins:

* **Block Editor** (embedding Google Fonts via the Customizer's "Additional CSS")
* [**Divi**](https://divimode.com/go/divi/) (see "Configuration for Divi" below)
* [**Elementor**](https://wordpress.org/plugins/elementor/)
* [Jetpack](https://wordpress.org/plugins/jetpack/) (specially with Performance options like "site accelerator")
* [Fonts Plugin | Google Fonts Typography](https://wordpress.org/plugins/olympus-google-fonts/)

Most other plugins and themes will work with this plugin as well. If you encounter any issues, please let us know, so we can improve this plugin 👍
