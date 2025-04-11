<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

# Bootstrap plugin.
require_once 'src/Activator.php';
require_once 'src/Plugin.php';
require_once 'src/functions-db.php';
require_once 'src/functions-capabilities.php';
require_once 'src/functions-roles.php';
require_once 'src/functions-products.php';
require_once 'src/functions-subscriptions.php';
require_once 'src/functions-transactions.php';
require_once 'src/functions-users.php';
require_once 'src/functions-gateways.php';
require_once 'src/gateways/class-gateway.php';
require_once 'src/gateways/class-stripe-gateway.php';

// Initialize the plugin
Plugin::get_instance();