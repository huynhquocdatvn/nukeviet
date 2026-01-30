<?php

/**
 * NukeViet Queue System - CLI Entry Point
 *
 * @version 1.0
 * @author AI Assistant
 * @copyright (C) 2026 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 *
 * Usage:
 *   php worker/run.php [options]
 *
 * Options:
 *   --help          Show this help message
 *   --debug         Enable debug logging
 *   --max-jobs=N    Maximum jobs to process (default: 50)
 *   --max-time=N    Maximum runtime in seconds (default: 3600)
 *   --max-memory=N  Maximum memory in MB (default: 100)
 */

declare(strict_types=1);

// Ensure running from CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

// Parse command line options
$options = getopt('h', [
    'help',
    'debug',
    'max-jobs:',
    'max-time:',
    'max-memory:',
]);

// Show help
if (isset($options['h']) || isset($options['help'])) {
    echo <<<HELP
NukeViet Queue Worker v1.0

Usage:
  php worker/run.php [options]

Options:
  -h, --help          Show this help message
  --debug             Enable debug logging
  --max-jobs=N        Maximum jobs to process before exit (default: 50)
  --max-time=N        Maximum runtime in seconds before exit (default: 3600)
  --max-memory=N      Maximum memory usage in MB before exit (default: 100)

Examples:
  php worker/run.php
  php worker/run.php --max-jobs=100 --max-time=7200
  php worker/run.php --debug

For production Linux deployment, use Supervisor or systemd to manage the worker.
For Windows/XAMPP, use NSSM or Windows Task Scheduler for auto-restart.

HELP;
    exit(0);
}

// Enable debug mode
if (isset($options['debug'])) {
    define('NV_QUEUE_DEBUG', true);
}

/**
 * Bootstrap NukeViet environment.
 * This loads config.php, mainfile.php, and initializes $db, $site_mods, etc.
 */
require __DIR__ . '/bootstrap.php';

use NukeViet\Queue\LinuxWorker;
use NukeViet\Queue\WindowsWorker;

/**
 * Detect the appropriate worker class based on environment.
 *
 * - If pcntl extension is available: Use LinuxWorker (fork-based)
 * - Otherwise: Use WindowsWorker (loop-based)
 */
function detectWorkerClass(): string
{
    // Check for pcntl extension (Linux/Unix only)
    if (extension_loaded('pcntl') && function_exists('pcntl_fork')) {
        // Additional check: Ensure we're on a Unix-like system
        if (DIRECTORY_SEPARATOR === '/') {
            return LinuxWorker::class;
        }
    }

    // Fallback to Windows worker
    return WindowsWorker::class;
}

/**
 * Main execution
 */
try {
    echo "============================================\n";
    echo "  NukeViet Queue Worker v1.0\n";
    echo "============================================\n";
    echo "Started at: " . date('Y-m-d H:i:s') . "\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "OS: " . PHP_OS . "\n";
    echo "============================================\n\n";

    // Detect and instantiate worker
    $workerClass = detectWorkerClass();
    echo "Using worker: {$workerClass}\n\n";

    /** @var \NukeViet\Queue\AbstractWorker $worker */
    $worker = new $workerClass();

    // Apply command line options
    if (isset($options['max-jobs'])) {
        $reflection = new \ReflectionProperty($workerClass, 'maxJobs');
        $reflection->setAccessible(true);
        $reflection->setValue($worker, (int) $options['max-jobs']);
    }

    if (isset($options['max-time'])) {
        $reflection = new \ReflectionProperty($workerClass, 'maxRuntime');
        $reflection->setAccessible(true);
        $reflection->setValue($worker, (int) $options['max-time']);
    }

    if (isset($options['max-memory'])) {
        $reflection = new \ReflectionProperty($workerClass, 'maxMemory');
        $reflection->setAccessible(true);
        $reflection->setValue($worker, (int) $options['max-memory'] * 1048576);
    }

    // Run the worker
    $worker->run();

    echo "\n============================================\n";
    echo "Worker stopped at: " . date('Y-m-d H:i:s') . "\n";
    echo "============================================\n";

    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "\n");
    fwrite(STDERR, "============================================\n");
    fwrite(STDERR, "  FATAL ERROR\n");
    fwrite(STDERR, "============================================\n");
    fwrite(STDERR, "Message: " . $e->getMessage() . "\n");
    fwrite(STDERR, "File: " . $e->getFile() . ":" . $e->getLine() . "\n");
    fwrite(STDERR, "\nStack Trace:\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    fwrite(STDERR, "============================================\n");

    exit(1);
}
