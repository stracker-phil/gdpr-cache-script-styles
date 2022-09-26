<?php
/**
 * GDPR Cache Scripts & Styles
 *
 * @formatter:off
 * @author      Philipp Stracker
 * @package     GdprCache
 * @copyright   2022 Philipp Stracker
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: GDPR Cache Scripts & Styles
 * Plugin URI:  https://github.com/divimode/gdpr-cache-script-styles
 * Description: Caches external scripts and styles and serves them from your local website to make it more compliant with GDPR regulations
 * Version:     1.0.5
 * Author:      Divimode
 * Author URI:  https://divimode.com/
 * Text Domain: gdpr-cache
 * License:     GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * Copyright (C) 2022 Philipp Stracker
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see <http://www.gnu.org/licenses/>.
 *
 * @formatter:on
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * The current plugin version.
 *
 * @var string
 */
const GDPR_CACHE_VERSION = '1.0.4';

/**
 * Absolute path and file name of the main plugin file.
 *
 * @var string
 */
const GDPR_CACHE_PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/start.php';
