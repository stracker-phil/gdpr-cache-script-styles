<?php
/**
 * Loads and initializes the plugin
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/constants.php';

// Admin page integration.
require_once GDPR_CACHE_PATH . 'includes/admin/actions.php';
require_once GDPR_CACHE_PATH . 'includes/admin/notice.php';
require_once GDPR_CACHE_PATH . 'includes/admin/options.php';

// Asset scanner.
require_once GDPR_CACHE_PATH . 'includes/libs/data.php';
require_once GDPR_CACHE_PATH . 'includes/libs/cache.php';
require_once GDPR_CACHE_PATH . 'includes/libs/scanner.php';
require_once GDPR_CACHE_PATH . 'includes/libs/utils.php';
require_once GDPR_CACHE_PATH . 'includes/libs/worker.php';
