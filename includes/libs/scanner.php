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

add_filter( 'script_loader_src', __NAMESPACE__ . '\scan_external_scripts' );
add_filter( 'style_loader_src', __NAMESPACE__ . '\scan_external_styles' );

add_action( 'wp_head', 'die', 1 ); // FOR TEST ONLY (to debug *_loader_src output)

// ----------------------------------------------------------------------------


function scan_external_scripts( $source ) {
	echo "Script: <code>$source</code><br>";
}

function scan_external_styles( $source ) {
	echo "Style: <code>$source</code><br>";
}

