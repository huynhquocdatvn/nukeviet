<?php

/**
 * NukeViet Queue System - Windows Worker
 *
 * @version 1.0
 * @author AI Assistant
 * @copyright (C) 2026 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 *
 * This worker uses a simple loop strategy suitable for Windows/XAMPP environments
 * where pcntl_fork is not available. It processes jobs sequentially with
 * configurable limits to prevent memory leaks.
 */

declare(strict_types=1);

namespace NukeViet\Queue;

/**
 * WindowsWorker - Loop-based worker for Windows/XAMPP
 *
 * Uses a simple loop to process jobs sequentially.
 * Automatically exits after reaching any of these limits:
 * - 50 jobs processed
 * - 1 hour runtime
 * - 100MB memory usage
 *
 * For production use on Windows, use NSSM or Windows Task Scheduler
 * to automatically restart the worker when it exits.
 */
class WindowsWorker extends AbstractWorker
{
    /**
     * Number of jobs before forcing garbage collection
     */
    protected int $gcInterval = 10;

    /**
     * Last garbage collection job count
     */
    protected int $lastGcAt = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        // Windows-specific settings
        // Set max execution time to unlimited
        set_time_limit(0);

        // Enable garbage collection
        gc_enable();
    }

    /**
     * Force garbage collection if needed
     */
    protected function maybeGarbageCollect(): void
    {
        if ($this->jobsProcessed - $this->lastGcAt >= $this->gcInterval) {
            $before = memory_get_usage(true);
            gc_collect_cycles();
            $after = memory_get_usage(true);
            $freed = $before - $after;

            if ($freed > 0) {
                $freedMB = round($freed / 1048576, 2);
                $this->log("Garbage collection freed {$freedMB}MB", 'debug');
            }

            $this->lastGcAt = $this->jobsProcessed;
        }
    }

    /**
     * Get current memory usage percentage of limit
     *
     * @return float Percentage (0-100)
     */
    protected function getMemoryUsagePercent(): float
    {
        return (memory_get_usage(true) / $this->maxMemory) * 100;
    }

    /**
     * Main run loop - simple sequential processing
     *
     * Processes jobs one at a time until limits are reached:
     * 1. Pop job from queue
     * 2. Process job
     * 3. Check limits
     * 4. Garbage collect periodically
     * 5. Repeat
     */
    public function run(): void
    {
        $this->log("WindowsWorker started (Loop & Limit strategy)");
        $this->log("Queue: {$this->queueName}");
        $this->log("Limits: {$this->maxJobs} jobs, {$this->maxRuntime}s runtime, " .
            round($this->maxMemory / 1048576) . "MB memory");

        $emptyQueueCount = 0;
        $maxEmptyChecks = 60; // Exit if queue is empty for 5 minutes (60 * 5s)

        while ($this->shouldContinue()) {
            // Try to get a job
            $job = $this->popJob($this->sleepInterval);

            if ($job === null) {
                $emptyQueueCount++;

                // Log periodic status when queue is empty
                if ($emptyQueueCount % 12 === 0) { // Every minute
                    $stats = $this->getStats();
                    $this->log("Queue empty. Jobs: {$stats['jobs_processed']}, " .
                        "Runtime: {$stats['runtime_seconds']}s, " .
                        "Memory: {$stats['memory_usage_mb']}MB");
                }

                // Optional: Exit if queue has been empty for too long
                if ($emptyQueueCount >= $maxEmptyChecks) {
                    $this->log("Queue has been empty for {$maxEmptyChecks} checks, exiting");
                    break;
                }

                continue;
            }

            // Reset empty counter when we get a job
            $emptyQueueCount = 0;

            // Process the job
            $success = $this->processJob($job);
            $this->jobsProcessed++;

            if (!$success) {
                $this->log("Job failed, continuing to next job", 'warning');
            }

            // Periodic garbage collection
            $this->maybeGarbageCollect();

            // Log progress every 10 jobs
            if ($this->jobsProcessed % 10 === 0) {
                $stats = $this->getStats();
                $memPercent = round($this->getMemoryUsagePercent(), 1);
                $this->log("Progress: {$stats['jobs_processed']}/{$this->maxJobs} jobs, " .
                    "{$stats['runtime_seconds']}s runtime, " .
                    "{$stats['memory_usage_mb']}MB ({$memPercent}% of limit)");
            }
        }

        // Final garbage collection
        gc_collect_cycles();

        // Print final statistics
        $stats = $this->getStats();
        $this->log("Worker finished. Processed {$stats['jobs_processed']} jobs in {$stats['runtime_seconds']}s");
        $this->log("Peak memory: {$stats['peak_memory_mb']}MB");

        // Print restart suggestion for Windows
        $this->log("Note: Use NSSM or Windows Task Scheduler to automatically restart this worker.");
    }
}
