<?php

/**
 * NukeViet Queue System - Dispatcher Functions
 *
 * @version 1.0
 * @author AI Assistant
 * @copyright (C) 2026 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 *
 * This file contains the dispatcher function that pushes jobs to the queue
 * or processes them synchronously using "Fire and Forget" technique.
 */

if (!defined('NV_MAINFILE')) {
    exit('Stop!!!');
}

/**
 * Dispatch a job to the queue system.
 *
 * This function supports two modes based on $global_config['sys_use_queue']:
 * - Mode 1 (Redis Async): Pushes the job to Redis for background processing
 * - Mode 0 (Sync Fallback): Uses "Fire and Forget" to process immediately after response
 *
 * @param string $module The module name (e.g., 'news', 'users')
 * @param string $handler The handler class name (e.g., 'SendEmail', 'Jobs\NotifyUser')
 * @param array $data Job data to pass to the handler
 * @param int $priority Job priority (lower = higher priority, future use)
 * @return bool True if job was dispatched successfully
 *
 * @example
 * // Dispatch a job to send notification
 * nv_dispatch_job('news', 'SendNotification', [
 *     'article_id' => 123,
 *     'user_ids' => [1, 2, 3],
 * ]);
 */
function nv_dispatch_job(string $module, string $handler, array $data = [], int $priority = 0): bool
{
    global $global_config, $redis_config, $site_mods;

    // Validate module exists (soft check - worker will validate again)
    // In CLI context or when dispatching async, $site_mods may not be fully loaded
    // The worker will perform proper module validation before executing the job
    if (isset($site_mods) && is_array($site_mods) && !empty($site_mods)) {
        if (!isset($site_mods[$module])) {
            trigger_error("nv_dispatch_job: Module '{$module}' not found or not active", E_USER_WARNING);
            return false;
        }
    }


    // Build job payload
    $job = [
        'id' => uniqid('job_', true),
        'module' => $module,
        'handler' => $handler,
        'data' => $data,
        'priority' => $priority,
        'created_at' => time(),
        'created_by' => defined('NV_CLIENT_IP') ? NV_CLIENT_IP : 'system',
    ];

    // Check queue mode
    $useQueue = !empty($global_config['sys_use_queue']);

    if ($useQueue) {
        $driver = $global_config['queue_driver'] ?? 'redis';
        
        if ($driver === 'database') {
            return nv_dispatch_job_database($job);
        }

        // Mode 1: Redis Async (Default)
        return nv_dispatch_job_async($job);
    } else {
        // Mode 0: Sync Fallback with Fire and Forget
        return nv_dispatch_job_sync($job);
    }
}

/**
 * Push job to Database queue
 *
 * @param array $job Job payload
 * @return bool True if pushed successfully
 */
function nv_dispatch_job_database(array $job): bool
{
    global $db, $db_config;

    if (!is_object($db)) {
        trigger_error('nv_dispatch_job: Database connection not available', E_USER_WARNING);
        return false;
    }

    $tableName = ($db_config['prefix'] ?? 'nv4') . '_queue_jobs';
    
    // Ensure table exists (simple check to avoid overhead, normally should be created by migration)
    // We rely on catch block to handle missing table if needed, or create it once.
    // Ideally this should be done in module install/update.
    
    $payload = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $createdAt = time();
    $availableAt = time(); // Can support delayed jobs later

    $sql = "INSERT INTO " . $tableName . " (queue, payload, attempts, reserved_at, available_at, created_at) VALUES (:queue, :payload, 0, NULL, :available_at, :created_at)";
    
    $dataInsert = [
        'queue' => 'default',
        'payload' => $payload,
        'available_at' => $availableAt,
        'created_at' => $createdAt
    ];

    try {
        $result = $db->insert_id($sql, 'id', $dataInsert);
        return $result > 0;
    } catch (\Throwable $e) {
        // Try to create table if it doesn't exist
        if (strpos($e->getMessage(), "doesn't exist") !== false) {
             try {
                $createSql = "CREATE TABLE IF NOT EXISTS " . $tableName . " (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    queue VARCHAR(255) NOT NULL DEFAULT 'default',
                    payload LONGTEXT NOT NULL,
                    attempts TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
                    reserved_at INT(10) UNSIGNED DEFAULT NULL,
                    available_at INT(10) UNSIGNED NOT NULL,
                    created_at INT(10) UNSIGNED NOT NULL,
                    PRIMARY KEY (id),
                    KEY queue_reserved_available (queue, reserved_at, available_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                $db->query($createSql);
                
                // Retry insert
                $result = $db->insert_id($sql, 'id', $dataInsert);
                return $result > 0;
             } catch (\Throwable $ex) {
                 trigger_error('nv_dispatch_job: Failed to create table or insert job to Database: ' . $ex->getMessage(), E_USER_WARNING);
                 return false;
             }
        }
        
        trigger_error('nv_dispatch_job: Failed to insert job to Database: ' . $e->getMessage(), E_USER_WARNING);
        return false;
    }
}

/**
 * Push job to Redis queue (Async mode)
 *
 * @param array $job Job payload
 * @return bool True if pushed successfully
 */
function nv_dispatch_job_async(array $job): bool
{
    global $redis_config;

    // Validate Redis configuration
    if (!isset($redis_config) || !is_array($redis_config)) {
        trigger_error(
            'nv_dispatch_job: Redis configuration ($redis_config) is not defined. ' .
            'Please add Redis configuration to config.php.',
            E_USER_WARNING
        );
        return false;
    }

    try {
        // Create Redis connection
        $options = [
            'scheme' => 'tcp',
            'host' => $redis_config['host'] ?? '127.0.0.1',
            'port' => (int) ($redis_config['port'] ?? 6379),
        ];

        if (!empty($redis_config['password'])) {
            $options['password'] = $redis_config['password'];
        }

        if (isset($redis_config['database'])) {
            $options['database'] = (int) $redis_config['database'];
        }

        $redis = new \Predis\Client($options);

        // Build queue name
        $queueName = ($redis_config['prefix'] ?? 'nv_queue_') . 'jobs';

        // Push job to queue
        $payload = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $redis->rpush($queueName, [$payload]);

        return true;
    } catch (\Throwable $e) {
        trigger_error('nv_dispatch_job: Failed to push job to Redis: ' . $e->getMessage(), E_USER_WARNING);
        return false;
    }
}

/**
 * Process job synchronously using "Fire and Forget" technique.
 *
 * This method:
 * 1. Cleans the output buffer
 * 2. Sends response headers to close connection with client
 * 3. Uses fastcgi_finish_request() if available
 * 4. Continues processing the job after connection is closed
 *
 * @param array $job Job payload
 * @return bool True if job executed successfully
 */
function nv_dispatch_job_sync(array $job): bool
{
    // Resolve handler class
    $module = $job['module'];
    $handler = $job['handler'];
    $data = $job['data'];

    // Build full class name
    if (str_contains($handler, '\\')) {
        if (str_starts_with($handler, 'NukeViet\\')) {
            $handlerClass = $handler;
        } else {
            $handlerClass = "NukeViet\\Module\\{$module}\\{$handler}";
        }
    } else {
        $handlerClass = "NukeViet\\Module\\{$module}\\Jobs\\{$handler}";
    }

    // Verify handler exists
    if (!class_exists($handlerClass)) {
        trigger_error("nv_dispatch_job: Handler class not found: {$handlerClass}", E_USER_WARNING);
        return false;
    }

    if (!method_exists($handlerClass, 'handle')) {
        trigger_error("nv_dispatch_job: Handler class {$handlerClass} does not have handle() method", E_USER_WARNING);
        return false;
    }

    // Use Fire and Forget technique
    nv_fire_and_forget(function () use ($handlerClass, $data) {
        try {
            return $handlerClass::handle($data);
        } catch (\Throwable $e) {
            trigger_error("nv_dispatch_job sync error: " . $e->getMessage(), E_USER_WARNING);
            return false;
        }
    });

    return true;
}

/**
 * Execute a callback after sending response to client.
 *
 * This implements the "Fire and Forget" pattern:
 * 1. Clean output buffer and prepare response
 * 2. Send response headers to close connection
 * 3. Use fastcgi_finish_request() if available (PHP-FPM)
 * 4. Continue running the callback in background
 *
 * @param callable $callback Function to execute in background
 * @return void
 */
function nv_fire_and_forget(callable $callback): void
{
    // Allow script to continue after client disconnects
    ignore_user_abort(true);

    // Remove time limit
    set_time_limit(0);

    // Close session to prevent blocking other requests
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Clean output buffer
    $level = ob_get_level();
    for ($i = 0; $i < $level; $i++) {
        ob_end_clean();
    }

    // Start fresh output buffer
    ob_start();

    // Minimal response body
    echo json_encode(['status' => 'queued']);

    // Get content length
    $size = ob_get_length();

    // Send headers to close connection
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Length: ' . $size);
    header('Connection: close');

    // Flush and close connection
    ob_end_flush();

    if (function_exists('ob_flush')) {
        @ob_flush();
    }

    @flush();

    // If using PHP-FPM, use fastcgi_finish_request to properly close connection
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    // Now execute the callback in background
    // Client has already received response and connection is closed
    $callback();
}

/**
 * Check if the queue system is enabled.
 *
 * @return bool True if Redis queue is enabled
 */
function nv_queue_enabled(): bool
{
    global $global_config;
    return !empty($global_config['sys_use_queue']);
}

/**
 * Get queue statistics (Redis/Database).
 *
 * @return array Queue statistics
 */
function nv_queue_stats(): array
{
    global $global_config, $redis_config, $db, $db_config;

    $driver = $global_config['queue_driver'] ?? 'redis';

    if ($driver === 'database') {
        if (!is_object($db)) {
             $db = new \NukeViet\Core\Database($db_config);
        }
        $tableName = ($db_config['prefix'] ?? 'nv4') . '_queue_jobs';
        try {
            $sql = "SELECT COUNT(*) FROM " . $tableName . " WHERE reserved_at IS NULL";
            $result = $db->query($sql);
            $count = $result->fetchColumn();
            return [
                'queue_driver' => 'database',
                'queue_name' => 'default',
                'pending_jobs' => $count,
                'redis_connected' => false,
            ];
        } catch (\Throwable $e) {
             return [
                'queue_driver' => 'database',
                'error' => $e->getMessage()
            ];
        }
    }

    if (!isset($redis_config) || !is_array($redis_config)) {
        return ['error' => 'Redis not configured'];
    }

    try {
        $options = [
            'scheme' => 'tcp',
            'host' => $redis_config['host'] ?? '127.0.0.1',
            'port' => (int) ($redis_config['port'] ?? 6379),
        ];

        if (!empty($redis_config['password'])) {
            $options['password'] = $redis_config['password'];
        }

        if (isset($redis_config['database'])) {
            $options['database'] = (int) $redis_config['database'];
        }

        $redis = new \Predis\Client($options);
        $queueName = ($redis_config['prefix'] ?? 'nv_queue_') . 'jobs';

        return [
            'queue_driver' => 'redis',
            'queue_name' => $queueName,
            'pending_jobs' => $redis->llen($queueName),
            'redis_connected' => true,
        ];
    } catch (\Throwable $e) {
        return [
            'queue_driver' => 'redis',
            'error' => $e->getMessage(),
            'redis_connected' => false,
        ];
    }
}
