=== GDPR Cache Scripts & Styles ===
Tags: gdpr, ccpa, privacy, asset cache
Requires at least: 5.8
Tested up to: 6.0.2
Requires PHP: 7.4
Stable tag: trunk
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Cache external scripts and styles, and serve them from your local website.

== Description ==

One-click solution to automatically serve external assets from your local website.

No configuration and no coding required - activate the plugin and forget about it.

## Disclaimer

This plugin does not provide any guarantees of making your website GDPR-compliant. As a website operator, you are always responsible to verify if this plugin works for you and collect consent for external scripts before loading them in the visitor's browser.

## How it works

The plugin scans every URL that is enqueued via `wp_enqueue_script()` and `wp_enqueue_style()`. When detecting external URL, that file is saved to your uploads-folder and served from there.

It also scans the contents of CSS files for external dependencies and also saves those files to your uploads-folder!

Heads-up: For technical reasons, we cannot scan the contents of JS files for such dependencies - JS files can always inject external assets

## Background worker

To speed up your websites loading time, all assets are downloaded in a background process: When a new asset is detected, or a cached file expires, a worker-task is enqueued.

The queue is then processed in an asynchronous process; while the queue is processed, your website could still serve the external assets for a while - usually the queue is processed within one or two minutes.

## Options page

You'll find the plugin options page at Tools > GDPR Cache. On that page you can invalidate all assets and see a list of the entire cache.

When you deactivate the plugin, the entire cache is purged (all files are deleted and relevant DB values are reset)

== Installation ==

Automatic WordPress installer:

1. Install the plugin from wordpress.org
2. Activate it.
3. Done! No configuration needed.

From GitHub:

1. Visit https://github.com/divimode/gdpr-cache-script-styles and download the repository zip file.
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

== Screenshots ==

1. Without this plugin: A website uses a Google Font, and the visitor's browser connects to 2 external servers
2. With this plugin: The Google Font files are saved to your website and served locally. No request to Google's server is made!
3. The options-page displays a list of all cached files and gives the option to invalidate all files.

== Changelog ==

= 1.0.0 =

* Initial Release
