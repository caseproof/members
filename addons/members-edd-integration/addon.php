<?php

namespace Members\Integration\EDD;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

# Bootstrap plugin.
require_once 'src/functions-filters.php';
require_once 'src/functions-caps.php';
require_once 'src/functions-roles.php';
