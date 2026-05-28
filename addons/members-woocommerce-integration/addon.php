<?php

namespace Members\Integration\WooCommerce;

# Don't execute code if file is file is accessed directly.
defined( 'ABSPATH' ) || exit;

# Bootstrap plugin.
require_once __DIR__ . '/src/functions-filters.php';
require_once __DIR__ . '/src/functions-caps.php';
require_once __DIR__ . '/src/functions-roles.php';
