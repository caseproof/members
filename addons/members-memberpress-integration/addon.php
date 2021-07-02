<?php

namespace Members\Integration\MemberPress;

# Don't execute code if file is file is accessed directly.
defined( 'ABSPATH' ) || exit;

# Bootstrap plugin.
require_once 'src/functions-filters.php';
require_once 'src/functions-caps.php';
