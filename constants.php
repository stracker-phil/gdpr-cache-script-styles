<?php
/**
 * Defines constants for the GDPR cache plugin
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Absolute path to this plugin's folder, with trailing slash.
 *
 * @var string
 */
define( 'GDPR_CACHE_PATH', plugin_dir_path( GDPR_CACHE_PLUGIN_FILE ) );

/**
 * Option name that holds a list of all cached assets.
 *
 * @var string
 */
const GDPR_CACHE_OPTION = 'gdpr_cache';

/**
 * Option name that contains the task queue of invalidated or missing assets.
 *
 * @var string
 */
const GDPR_CACHE_QUEUE = 'gdpr_queue';

/**
 * Option name that holds the timestamp of the background worker start time.
 *
 * @var string
 */
const GDPR_CACHE_WORKER_LOCK = 'gdpr_lock';
