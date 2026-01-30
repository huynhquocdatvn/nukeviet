<?php

/**
 * NukeViet Queue System - Abstract Worker
 *
 * @version 1.0
 * @author AI Assistant
 * @copyright (C) 2026 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 *
 * Base class for all worker strategies. Handles Redis connection, database
 * reconnection, and job dispatching logic.
 */

declare(strict_types=1);

namespace NukeViet\Queue;

use Predis\Client as RedisClient;
use NukeViet\Core\Database;

/**
 * AbstractWorker - Base class for Queue Workers
 *
 * This class provides common functionality for both LinuxWorker and WindowsWorker:
 * - Redis connection management
 * - Database reconnection before each job (prevents "MySQL has gone away")
 * - Job deserialization and handler invocation
 * - Module validation
 */
abstract class AbstractWorker
{
    /**
     * Redis client instance
     */
    protected ?RedisClient $redis = null;

    /**
     * Redis configuration from config.php
     */
    protected array $redisConfig;

    /**
     * Queue name/key in Redis
     */
    protected string $queueName;

    /**
     * Number of jobs processed
     */
    protected int $jobsProcessed = 0;

    /**
     * Worker start time
     */
    protected int $startTime;

    /**
     * Maximum memory usage in bytes (100MB)
     */
    protected int $maxMemory = 104857600;

    /**
     * Maximum runtime in seconds (1 hour)
     */
    protected int $maxRuntime = 3600;

    /**
     * Maximum jobs to process before exit
     */
    protected int $maxJobs = 50;

    /**
     * Polling interval in seconds when queue is empty
     */
    protected int $sleepInterval = 5;

    /**
     * Whether the worker should continue running
     */
    protected bool $shouldRun = true;

    /**
     * Constructor - Initialize Redis connection
     *
     * @throws \RuntimeException If Redis configuration is missing
     */
    public function __construct()
    {
        global $redis_config;

        if (!isset($redis_config) || !is_array($redis_config)) {
            throw new \RuntimeException(
                'Redis configuration ($redis_config) is not defined in config.php. ' .
                'Please add Redis configuration with host, port, password, database, and prefix keys.'
            );
        }

        $this->redisConfig = $redis_config;
        $this->queueName = ($redis_config['prefix'] ?? 'nv_queue_') . 'jobs';
        $this->startTime = time();

        $this->connectRedis();
    }

    /**
     * Connect to Redis server
     *
     * @throws \RuntimeException If connection fails
     */
    protected function connectRedis(): void
    {
        try {
            $options = [
                'scheme' => 'tcp',
                'host' => $this->redisConfig['host'] ?? '127.0.0.1',
                'port' => (int) ($this->redisConfig['port'] ?? 6379),
            ];

            // Add password if configured
            if (!empty($this->redisConfig['password'])) {
                $options['password'] = $this->redisConfig['password'];
            }

            // Add database selection if configured
            if (isset($this->redisConfig['database'])) {
                $options['database'] = (int) $this->redisConfig['database'];
            }

            $this->redis = new RedisClient($options);

            // Test connection
            $this->redis->ping();

            $this->log("Connected to Redis at {$options['host']}:{$options['port']}");
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to connect to Redis: " . $e->getMessage()
            );
        }
    }

    /**
     * Reconnect to database before processing a job.
     *
     * CRITICAL: This method MUST be called before processing any job.
     * Long-running CLI processes will experience "MySQL has gone away" errors
     * if the same connection is kept open for extended periods.
     *
     * @return Database New database connection instance
     */
    protected function reconnectDatabase(): Database
    {
        global $db, $db_config;

        // Close existing connection if any
        if ($db instanceof Database) {
            $db = null;
        }

        // Create fresh database connection
        $db = new Database($db_config);

        if (empty($db->connect)) {
            throw new \RuntimeException('Failed to reconnect to database');
        }

        return $db;
    }

    /**
     * Check if a module is active
     *
     * In CLI context, $site_mods may not be fully loaded by mainfile.php.
     * When $site_mods is not available, we assume the job was validated
     * at dispatch time and allow it to proceed.
     *
     * @param string $module Module name
     * @return bool True if module is active or cannot be verified
     */
    protected function isModuleActive(string $module): bool
    {
        global $site_mods;

        // If site_mods is not available (CLI context), assume module is valid
        // The job was already validated at dispatch time
        if (!isset($site_mods) || !is_array($site_mods) || empty($site_mods)) {
            $this->log("Note: \$site_mods not available (CLI context), proceeding with job", 'debug');
            return true;
        }

        return isset($site_mods[$module]) && !empty($site_mods[$module]['module_file']);
    }

    /**
     * Pop a job from the Redis queue
     *
     * @param int $timeout Timeout in seconds for blocking pop
     * @return array|null Job data or null if no job available
     */
    protected function popJob(int $timeout = 5): ?array
    {
        try {
            // Use BLPOP for blocking pop with timeout
            $result = $this->redis->blpop([$this->queueName], $timeout);

            if ($result === null) {
                return null;
            }

            // BLPOP returns [key, value]
            $jobData = $result[1] ?? null;

            if ($jobData === null) {
                return null;
            }

            $job = json_decode($jobData, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log("Invalid JSON in job data: " . json_last_error_msg(), 'error');
                return null;
            }

            return $job;
        } catch (\Exception $e) {
            $this->log("Error popping job from queue: " . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Process a single job
     *
     * @param array $job Job data containing 'module', 'handler', 'data'
     * @return bool True if job processed successfully
     */
    protected function processJob(array $job): bool
    {
        $module = $job['module'] ?? '';
        $handler = $job['handler'] ?? '';
        $data = $job['data'] ?? [];
        $jobId = $job['id'] ?? uniqid();

        $this->log("Processing job {$jobId}: {$module}::{$handler}");

        // Validate required fields
        if (empty($module) || empty($handler)) {
            $this->log("Invalid job: missing module or handler", 'error');
            return false;
        }

        // Check if module is active
        if (!$this->isModuleActive($module)) {
            $this->log("Module '{$module}' is not active, skipping job", 'warning');
            return false;
        }

        // Reconnect database before processing
        try {
            $this->reconnectDatabase();
        } catch (\Exception $e) {
            $this->log("Database reconnection failed: " . $e->getMessage(), 'error');
            return false;
        }

        // Resolve handler class
        // Expected format: Jobs\ClassName or full namespace
        $handlerClass = $this->resolveHandlerClass($module, $handler);

        if (!class_exists($handlerClass)) {
            $this->log("Handler class not found: {$handlerClass}", 'error');
            return false;
        }

        if (!method_exists($handlerClass, 'handle')) {
            $this->log("Handler class {$handlerClass} does not have a handle() method", 'error');
            return false;
        }

        // Execute the job
        try {
            $startTime = microtime(true);
            $result = $handlerClass::handle($data);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($result) {
                $this->log("Job {$jobId} completed in {$duration}ms");
            } else {
                $this->log("Job {$jobId} returned false after {$duration}ms", 'warning');
            }

            return (bool) $result;
        } catch (\Throwable $e) {
            $this->log("Job {$jobId} failed: " . $e->getMessage(), 'error');
            $this->log("Stack trace: " . $e->getTraceAsString(), 'debug');
            return false;
        }
    }

    /**
     * Resolve the full class name for a job handler
     *
     * @param string $module Module name (e.g., 'news')
     * @param string $handler Handler name (e.g., 'Jobs\SendEmail' or 'SendEmail')
     * @return string Full class name
     */
    protected function resolveHandlerClass(string $module, string $handler): string
    {
        // If handler already contains namespace, use as-is
        if (str_contains($handler, '\\')) {
            // If it starts with NukeViet, assume it's fully qualified
            if (str_starts_with($handler, 'NukeViet\\')) {
                return $handler;
            }
            // Otherwise, prepend the module namespace
            return "NukeViet\\Module\\{$module}\\{$handler}";
        }

        // Default: assume handler is in Jobs namespace of the module
        return "NukeViet\\Module\\{$module}\\Jobs\\{$handler}";
    }

    /**
     * Check if worker should continue running
     *
     * @return bool True if worker should continue
     */
    protected function shouldContinue(): bool
    {
        if (!$this->shouldRun) {
            return false;
        }

        // Check job limit
        if ($this->jobsProcessed >= $this->maxJobs) {
            $this->log("Reached maximum jobs limit ({$this->maxJobs})");
            return false;
        }

        // Check runtime limit
        $runtime = time() - $this->startTime;
        if ($runtime >= $this->maxRuntime) {
            $this->log("Reached maximum runtime limit ({$this->maxRuntime}s)");
            return false;
        }

        // Check memory limit
        $memoryUsage = memory_get_usage(true);
        if ($memoryUsage >= $this->maxMemory) {
            $memoryMB = round($memoryUsage / 1048576, 2);
            $this->log("Reached maximum memory limit ({$memoryMB}MB)");
            return false;
        }

        return true;
    }

    /**
     * Log a message to stdout/stderr
     *
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error, debug)
     */
    protected function log(string $message, string $level = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        $formattedMessage = "[{$timestamp}] [{$levelUpper}] {$message}\n";

        if ($level === 'error') {
            fwrite(STDERR, $formattedMessage);
        } else {
            fwrite(STDOUT, $formattedMessage);
        }
    }

    /**
     * Graceful shutdown handler
     */
    public function shutdown(): void
    {
        $this->shouldRun = false;
        $this->log("Shutting down worker...");
    }

    /**
     * Get statistics about the worker
     *
     * @return array Worker statistics
     */
    public function getStats(): array
    {
        return [
            'jobs_processed' => $this->jobsProcessed,
            'runtime_seconds' => time() - $this->startTime,
            'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ];
    }

    /**
     * Main run loop - implemented by child classes
     *
     * LinuxWorker: Uses pcntl_fork for each job
     * WindowsWorker: Uses loop with limits
     */
    abstract public function run(): void;
}
