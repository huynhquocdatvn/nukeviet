<?php

/**
 * NukeViet Queue System - Linux Worker
 *
 * @version 1.0
 * @author AI Assistant
 * @copyright (C) 2026 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 *
 * This worker uses pcntl_fork to create child processes for each job.
 * The parent process manages the queue while children handle individual jobs.
 * This approach provides excellent memory management as each child process
 * is completely freed after processing.
 */

declare(strict_types=1);

namespace NukeViet\Queue;

/**
 * LinuxWorker - Fork-based worker for Linux/Unix systems
 *
 * Uses pcntl_fork() to spawn child processes for each job.
 * Advantages:
 * - Complete memory isolation between jobs
 * - No memory leaks from long-running processes
 * - Each job gets a fresh environment
 */
class LinuxWorker extends AbstractWorker
{
    /**
     * Maximum number of concurrent child processes
     */
    protected int $maxChildren = 4;

    /**
     * Currently running child PIDs
     *
     * @var array<int, int> [pid => startTime]
     */
    protected array $children = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Verify pcntl extension is available
        if (!extension_loaded('pcntl')) {
            throw new \RuntimeException(
                'The pcntl extension is required for LinuxWorker. ' .
                'Please use WindowsWorker on systems without pcntl support.'
            );
        }

        parent::__construct();

        // Setup signal handlers
        $this->setupSignalHandlers();
    }

    /**
     * Setup signal handlers for graceful shutdown
     */
    protected function setupSignalHandlers(): void
    {
        // Handle SIGTERM (kill) and SIGINT (Ctrl+C)
        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function () {
            $this->log("Received SIGTERM, initiating graceful shutdown...");
            $this->shutdown();
        });

        pcntl_signal(SIGINT, function () {
            $this->log("Received SIGINT (Ctrl+C), initiating graceful shutdown...");
            $this->shutdown();
        });

        // Handle child process completion
        pcntl_signal(SIGCHLD, function () {
            $this->reapChildren();
        });
    }

    /**
     * Reap completed child processes
     */
    protected function reapChildren(): void
    {
        while (true) {
            $pid = pcntl_waitpid(-1, $status, WNOHANG);

            if ($pid <= 0) {
                break;
            }

            if (isset($this->children[$pid])) {
                $duration = time() - $this->children[$pid];
                unset($this->children[$pid]);

                if (pcntl_wifexited($status)) {
                    $exitCode = pcntl_wexitstatus($status);
                    $this->log("Child process {$pid} exited with code {$exitCode} after {$duration}s", 'debug');
                } elseif (pcntl_wifsignaled($status)) {
                    $signal = pcntl_wtermsig($status);
                    $this->log("Child process {$pid} terminated by signal {$signal}", 'warning');
                }
            }
        }
    }

    /**
     * Wait for a child slot to become available
     */
    protected function waitForChildSlot(): void
    {
        while (count($this->children) >= $this->maxChildren) {
            $this->reapChildren();

            if (count($this->children) >= $this->maxChildren) {
                usleep(100000); // 100ms
            }
        }
    }

    /**
     * Main run loop using fork
     *
     * The parent process continuously:
     * 1. Waits for available child slot
     * 2. Pops a job from Redis
     * 3. Forks a child to process the job
     * 4. Parent returns to step 1
     *
     * Child process:
     * 1. Processes the job
     * 2. Exits immediately (memory freed)
     */
    public function run(): void
    {
        $this->log("LinuxWorker started with max {$this->maxChildren} concurrent processes");
        $this->log("Queue: {$this->queueName}");
        $this->log("Limits: {$this->maxJobs} jobs, {$this->maxRuntime}s runtime, " .
            round($this->maxMemory / 1048576) . "MB memory");

        while ($this->shouldContinue()) {
            // Reap any completed children
            $this->reapChildren();

            // Wait for available child slot
            $this->waitForChildSlot();

            // Check limits again after waiting
            if (!$this->shouldContinue()) {
                break;
            }

            // Try to get a job
            $job = $this->popJob($this->sleepInterval);

            if ($job === null) {
                continue;
            }

            // Fork a child process to handle this job
            $pid = pcntl_fork();

            if ($pid === -1) {
                // Fork failed - process job in parent as fallback
                $this->log("Fork failed, processing job in parent process", 'warning');
                $this->processJob($job);
                $this->jobsProcessed++;
            } elseif ($pid === 0) {
                // Child process
                try {
                    $result = $this->processJob($job);
                    exit($result ? 0 : 1);
                } catch (\Throwable $e) {
                    $this->log("Child process error: " . $e->getMessage(), 'error');
                    exit(1);
                }
            } else {
                // Parent process
                $this->children[$pid] = time();
                $this->jobsProcessed++;
                $this->log("Spawned child process {$pid} for job", 'debug');
            }
        }

        // Wait for all children to complete
        $this->log("Waiting for remaining child processes to complete...");
        while (!empty($this->children)) {
            $this->reapChildren();
            if (!empty($this->children)) {
                usleep(100000); // 100ms
            }
        }

        // Print final statistics
        $stats = $this->getStats();
        $this->log("Worker finished. Processed {$stats['jobs_processed']} jobs in {$stats['runtime_seconds']}s");
        $this->log("Peak memory: {$stats['peak_memory_mb']}MB");
    }

    /**
     * Override shutdown to also terminate children
     */
    public function shutdown(): void
    {
        parent::shutdown();

        // Send SIGTERM to all children
        foreach (array_keys($this->children) as $pid) {
            $this->log("Sending SIGTERM to child process {$pid}", 'debug');
            posix_kill($pid, SIGTERM);
        }
    }
}
