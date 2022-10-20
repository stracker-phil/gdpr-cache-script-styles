<?php
/**
 * Scans the current request for external assets, attempts to replace them with
 * a locally cached asset
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


// ----------------------------------------------------------------------------

add_filter( 'script_loader_src', __NAMESPACE__ . '\scan_external_assets' );
add_filter( 'style_loader_src', __NAMESPACE__ . '\scan_external_assets' );
add_filter( 'wp_get_custom_css', __NAMESPACE__ . '\parse_custom_css' );
add_filter( 'gdpr_cache_swap_asset', __NAMESPACE__ . '\apply_blacklisted_assets', 10, 2 );

// ----------------------------------------------------------------------------


/**
 * Scans enqueued styles/scripts and replaces external sources with a local ones.
 *
 * @since 1.0.0
 *
 * @param string $source A script or style that might be local or external.
 *
 * @return string URL to the local script or style.
 */
function scan_external_assets( $source ) {
	// Source is empty when asset is loaded via load-scripts.php or load-styles.php
	// Those assets are always local, and we can safely exit early.
	if ( ! $source ) {
		return $source;
	}

	if ( is_external_url( $source ) ) {
		/**
		 * Determines whether the specified URL should be swapped with a local
		 *
		 * @sine 1.0.0
		 *
		 * @param bool   $should_swap Whether to download and cache the asset locally.
		 * @param string $source      The assets external URL.
		 */
		$should_swap = apply_filters( 'gdpr_cache_swap_asset', true, $source );

		if ( $should_swap ) {
			$source = swap_to_local_asset( $source );
		}
	}

	return $source;
}


/**
 * Parses the CSS contents of the themes "Custom CSS" field; this is the CSS
 * that's added via the Customizer.
 *
 * @since 1.0.2
 *
 * @param string $css The custom CSS string.
 *
 * @return string Parsed custom CSS.
 */
function parse_custom_css( $css ) {
	$parsed = replace_urls_in_css( $css );

	if ( $parsed['changed'] ) {
		set_dependencies( 'wp_custom_css', $parsed['urls'] );
	}

	return $parsed['content'];
}


/**
 * Caches the given URL in a local file and returns the URL to the local file.
 *
 * @since 1.0.0
 *
 * @param string $url The URL to cache locally.
 *
 * @return string The local URL of the asset, on success. Returns the unmodified
 *     input parameter on failure.
 */
function swap_to_local_asset( $url ) {
	// When an invalid URL is provided, bail.
	if ( ! $url || false === strpos( $url, '//' ) || ! is_external_url( $url ) ) {
		return $url;
	}

	// Store a flag to mark this file as "used".
	set_last_used( $url );

	/**
	 * Filter to allow short-circuiting the asset-swapping logic.
	 * When a string value is returned, that string is used as function return
	 * value without further checks.
	 *
	 * @since 1.0.0
	 *
	 * @param null|string $url      The URL to load in the visitor's browser, or
	 *                              NULL to use the default logic.
	 * @param string      $orig_url The original external asset URL.
	 */
	$pre_source = apply_filters( 'gdpr_cache_pre_swap_asset', null, $url );

	if ( is_string( $pre_source ) ) {
		return $pre_source;
	}

	$local_url = get_local_url( $url );

	/**
	 * Filter that allows modifying the asset URL before it's returned.
	 * This is useful if an empty local-URL should be returned instead of
	 * using the external asset.
	 *
	 * @since 1.0.0
	 *
	 * @param string|false $local_url The local cache URL, or false when the
	 *                                cache is not populated yet.
	 * @param string       $orig_url  The original external asset URL.
	 */
	$local_url = apply_filters( 'gdpr_cache_swap_asset', $local_url, $url );

	/*
	 * When the asset is still enqueued for capturing, the local URL is FALSE.
	 * In that case, we return the external URL so the website functions
	 * correctly until the background worker has processed the task queue.
	 */
	if ( false === $local_url ) {
		spawn_worker();

		return $url;
	}

	return $local_url;
}


/**
 * Scans the contents of the cached file to download and cache dependencies that
 * are loaded by the cached asset.
 *
 * @since 1.0.0
 *
 * @param string $type The file type that's parsed (css|js|ttf|...)
 * @param string $path Full path to the local cache file.
 * @param string $url  The remote URL from where the cache file was loaded.
 *
 * @return void
 */
function parse_cache_contents( $type, $path, $url ) {
	$parser = null;

	if ( 'css' === $type ) {
		$parser = __NAMESPACE__ . '\replace_urls_in_css';
	}

	if ( $parser ) {
		$fs = get_filesystem();

		// Read the file contents
		$orig = $fs->get_contents( $path );

		$parsed = $parser( $orig );

		// In case an asset was downloaded and cached, update the local file.
		if ( $parsed['changed'] ) {
			set_dependencies( $url, $parsed['urls'] );

			$fs->put_contents( $path, $parsed['content'] );
		}
	}
}


/**
 * Replaces external URLs inside the given CSS string with local URLs.
 *
 * Returns an array with parser details:
 *  - changed ... (bool) whether the content has changed.
 *  - urls ... (array) list of external URLs and their local representation.
 *  - content ... (string) the parsed contents (with URLs replaced).
 *
 * @since 1.0.2
 *
 * @param string $css The CSS string.
 *
 * @return array Parser details.
 */
function replace_urls_in_css( $css ) {
	$urls = [];

	/**
	 * The $matches array has 4 elements:
	 * [0] the full match, with url-prefix, the URI and the closing bracket
	 * [1] the "url(" prefix
	 * [2] the URI, with enclosing quotes <-- change this!
	 * [3] the closing bracket
	 *
	 * @param array $matches Array with 4 elements.
	 *
	 * @return string The full match with a different URI
	 */
	$parse_dependency = function ( array $matches ) use ( &$urls ) {
		$uri = trim( $matches[2], '"\'' );

		// Try to cache the external dependency.
		$local_uri = swap_to_local_asset( $uri );

		if ( $local_uri && $local_uri !== $uri ) {
			$urls[ $uri ] = $local_uri;

			return $matches[1] . $local_uri . $matches[3];
		} else {
			return $matches[0];
		}
	};

	$css = preg_replace_callback(
		'/([:\s]url\s*\()("[^"]*?"|\'[^\']*?\'|[^"\'][^)]*?)(\))/',
		$parse_dependency,
		$css,
		- 1
	);

	return [
		'changed' => count( $urls ) > 0,
		'urls'    => $urls,
		'content' => $css,
	];
}


/**
 * Returns an array with blacklisted host names.
 *
 * When a host is blacklisted, this plugin does not attempt to download or cache
 * any asset from it.
 *
 * @since 1.0.6
 * @return array List of host names that should not be cached locally.
 */
function get_blacklisted_hosts() {
	static $blacklist = null;

	if ( is_null( $blacklist ) ) {
		$blacklist = [
			'www.paypal.com',
			'paypal.com',
			'js.stripe.com',
			'stripe.com',
		];

		/**
		 * Filters the list of blacklisted hosts. This plugin does not download
		 * or cache any files from a blacklisted host.
		 *
		 * @since 1.0.6
		 *
		 * @param array $blacklist List of hosts (domains) that should not be
		 *                         served from the local site.
		 */
		$blacklist = apply_filters( 'gdpr_cache_blacklisted_asset_hosts', $blacklist );

		// Remove all trailing/leading slashes from the blacklisted hosts.
		$blacklist = array_map( function ( $host ) {
			return trim( $host, '/' );
		}, $blacklist );

		$blacklist = array_filter( $blacklist );
	}

	return $blacklist;
}

/**
 * Applies a blacklist of assets that should not be cached locally.
 *
 * @since 1.0.6
 *
 * @param bool   $should_swap Whether to download and cache the asset locally.
 * @param string $source      The assets external URL.
 *
 * @return bool Whether to download and cache the asset locally.
 */
function apply_blacklisted_assets( $should_swap, $source ) {
	$url = wp_parse_url( $source );

	if ( $url ) {
		if ( in_array( $url['host'], get_blacklisted_hosts() ) ) {
			$should_swap = false;
		}
	}

	return $should_swap;
}
