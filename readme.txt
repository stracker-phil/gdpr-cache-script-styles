=== GDPR Cache Scripts & Styles ===
Tags: gdpr, ccpa, privacy, asset cache, script cache, style cache, embed google fonts, local google fonts
Requires at least: 5.8
Tested up to: 6.0.2
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Greatly enhances privacy of your website by embedding external scripts and styles.

== Description ==

One-click solution to automatically serve external assets from your local website.

No configuration and no coding required - activate the plugin and forget about it.

## Disclaimer

This plugin does not provide any guarantees of making your website GDPR-compliant. As a website operator, you are always responsible to verify if this plugin works for you and collect consent for external scripts before loading them in the visitor's browser.

## How it works

> **Short**: External files are downloaded to your WordPress installation (into the uploads-folder) and then served from there.

**More details**:

The plugin scans every URL that is enqueued via `wp_enqueue_script()` and `wp_enqueue_style()`. When detecting external URL, that file is saved to your uploads-folder and served from there.

It also scans the contents of CSS files for external dependencies and also saves those files to your uploads-folder!

Heads-up: For technical reasons, we cannot scan the contents of JS files for such dependencies - JS files can always inject external assets

### No Output Buffer

This plugin does not add any "output buffering" but scans the URLs which are enqueued via recommended WordPress functions.

As a result, *GDPR Cache Scripts & Styles* has practically no performance impact on your response time, no matter how big your website is.

### Background worker

To speed up your websites loading time, all assets are downloaded in a background process: When a new asset is detected, or a cached file expires, a worker-task is enqueued.

The queue is then processed in an asynchronous process; while the queue is processed, your website could still serve the external assets for a while - usually the queue is processed within one or two minutes.

### Options page

You'll find the plugin options page at Tools > GDPR Cache. On that page you can invalidate all assets and see a list of the entire cache.

When you deactivate the plugin, the entire cache is purged (all files are deleted and relevant DB values are reset)

## Tested with

We've tested this plugin with the following themes and plugins, and

* Default WordPress **Block Editor** (embedding Google Fonts via Customizers "Additional CSS")
* [**Divi**](https://divimode.com/go/divi/) (see "Configuration for Divi" below)
* [**Elementor**](https://wordpress.org/plugins/elementor/)
* [Fonts Plugin | Google Fonts Typography](https://wordpress.org/plugins/olympus-google-fonts/)

== Installation ==

Automatic WordPress installer:

1. Install [the plugin from wordpress.org](https://wordpress.org/plugins/gdpr-cache-scripts-styles/)
2. Activate it.
3. Done! No configuration needed.

From GitHub:

1. Visit [github.com/divimode/gdpr-cache-script-styles](https://github.com/divimode/gdpr-cache-script-styles/) and download the repository zip file.
2. Open your wp-admin > Plugins > Add New page and upload that zip file
3. Activate the plugin.
4. Done! No configuration needed.

*Note: When installing the plugin from GitHub, it will be replaced with updates from the wordpress.org repository*

== Frequently Asked Questions ==

= How can this plugin make Google Fonts GDPR safe? =
Activate this plugin and you're done.

We tested this plugin on numerous websites with different themes, and it was able to detect and cache all Google Fonts automatically.

= Does this eliminate all external scripts? =
Unfortunately, no. Some scripts (such as Google Maps scripts) will load external assets that cannot be detected or cached by this plugin.

= Configuration for Divi =

If you're using the Divi Theme, you need to **disable** the Theme Option "Improve Google Fonts Loading".

You can find that option in wp-admin > Divi > Theme Options > General > Performance

When this option is enabled, this plugin cannot detect the Google Fonts, and your website will make some external requests to load those font-files.

= I still see some requests to Google's Servers =
Common reasons are:

* Some scripts (like Google Maps) can dynamically load Google Fonts or other external resources. This cannot be prevented by this plugin.
* A browser plugin loads external assets on every request. Test the page in an Incognito/Private window

Also, some themes or performance plugins can embed the external resources in a way that our plugin cannot detect. If this is the case for you, please let us know. We might be able to adjust this plugin, or provide you with instructions on how to configure the plugin/theme to be compatible with *GDPR Cache Scripts & Styles*.

== Screenshots ==

1. Without this plugin: A website uses a Google Font, and the visitor's browser connects to 2 external servers
2. With this plugin: The Google Font files are saved to your website and served locally. No request to Google's server is made!
3. The options-page displays a list of all cached files and gives the option to invalidate all files.

== Changelog ==

= 1.0.2 =

* New: Replace external URLs found in Additional CSS (via Customizer)

= 1.0.1 =

* New: A "Purge Cache" button to the plugin options page
* New: Include a User-Agent when requesting remote files, to fetch WOFF2 fonts from Google instead of large TTF files
* Fix: Correctly determine asset type from file extension to avoid "tmp" types
* Improve: Color coding of the cache-status on the plugin options page
* Improve: Local cache 1files reflect the entire remote URL, to add transparency over the file contents
* Improve: Fix some typos and remove unused code

= 1.0.0 =

* Initial Release
