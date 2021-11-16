<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Import Updater Class
require __DIR__ . '/classes/IB_GitHub_Theme_Updater.php';

// Turn on updater for third party theme
(new IB_GitHub_Theme_Updater(__FILE__));