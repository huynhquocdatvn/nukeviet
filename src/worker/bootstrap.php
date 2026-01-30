<?php

/**
 * NukeViet Queue System - Bootstrap
 *
 * @version 1.0
 * @author AI Assistant
 * @copyright (C) 2026 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 *
 * CRITICAL: This file initializes the NukeViet environment for CLI worker processes.
 * It must correctly define NV_ROOTDIR so that mainfile.php can locate config.php at the root.
 */

declare(strict_types=1);

// Prevent direct web access - this file is for CLI only
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

/**
 * NV_SYSTEM constant tells mainfile.php that this is a system-level script.
 * This bypasses the redirect to index.php at the top of mainfile.php.
 */
define('NV_SYSTEM', true);

/**
 * NV_ROOTDIR must point to the parent directory of /worker/
 * Since this file is in /src/worker/bootstrap.php,
 * dirname(__DIR__) will return /src/ which is the NukeViet root.
 */
define('NV_ROOTDIR', dirname(__DIR__));

/**
 * Simulate $_SERVER variables required by NukeViet core.
 * These are normally set by the web server but are missing in CLI context.
 * mainfile.php and its dependencies expect these to be present.
 */
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? 'NukeViet Queue Worker';
$_SERVER['REQUEST_TIME'] = $_SERVER['REQUEST_TIME'] ?? time();
$_SERVER['PHP_SELF'] = $_SERVER['PHP_SELF'] ?? '/index.php';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'] ?? NV_ROOTDIR . '/index.php';
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? NV_ROOTDIR;

/**
 * Include Composer autoloader.
 * This must be loaded before mainfile.php to ensure all classes are available.
 */
$autoloadPath = NV_ROOTDIR . '/includes/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    fwrite(STDERR, "Error: Composer autoload not found at: {$autoloadPath}\n");
    fwrite(STDERR, "Please run 'composer install' in the includes/ directory.\n");
    exit(1);
}
require $autoloadPath;

/**
 * PRE-LOAD DATABASE CONFIG
 *
 * mainfile.php calls `unset($db_config['dbpass'])` after establishing
 * the initial DB connection for security. However, the worker needs
 * the password to reconnect the database for each job.
 *
 * Solution: Pre-load config.php to capture the password, then restore
 * it after mainfile.php runs.
 */
$configPath = NV_ROOTDIR . '/config.php';
if (file_exists($configPath)) {
    require $configPath;
    $saved_dbpass = $db_config['dbpass'] ?? null;
} else {
    $saved_dbpass = null;
}

/**
 * Include the main NukeViet bootstrap file.
 * This will:
 * - Load config.php from NV_ROOTDIR (again, but NV_MAINFILE prevents re-requiring)
 * - Initialize database connection ($db)
 * - Load global configuration ($global_config)
 * - Initialize site modules ($site_mods)
 */
$mainfilePath = NV_ROOTDIR . '/includes/mainfile.php';
if (!file_exists($mainfilePath)) {
    fwrite(STDERR, "Error: mainfile.php not found at: {$mainfilePath}\n");
    exit(1);
}
require $mainfilePath;

/**
 * RESTORE DATABASE PASSWORD
 * Restore the password that was unset by mainfile.php
 * This allows reconnectDatabase() to work in the worker.
 */
if ($saved_dbpass !== null) {
    $db_config['dbpass'] = $saved_dbpass;
}
unset($saved_dbpass);

/**
 * Verify Redis configuration is present.
 * The $redis_config array should be defined in config.php.
 */
global $redis_config;
if (!isset($redis_config) || !is_array($redis_config)) {
    fwrite(STDERR, "Warning: \$redis_config is not defined in config.php.\n");
    fwrite(STDERR, "Queue system will not be able to connect to Redis.\n");
    fwrite(STDERR, "Please add Redis configuration to your config.php file.\n");
}

/**
 * Output success message for debugging purposes.
 */
if (defined('NV_QUEUE_DEBUG') && NV_QUEUE_DEBUG) {
    fwrite(STDOUT, "NukeViet Queue Bootstrap loaded successfully.\n");
    fwrite(STDOUT, "NV_ROOTDIR: " . NV_ROOTDIR . "\n");
    fwrite(STDOUT, "PHP Version: " . PHP_VERSION . "\n");
}
