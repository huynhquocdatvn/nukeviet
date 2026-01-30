<?php

/**
 * NukeViet Queue System - Contact Module Email Job
 *
 * @version 1.0
 * @author AI Assistant
 * @copyright (C) 2026 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 *
 * Job thay thế nv_sendmail_async trong contact module.
 * Xử lý gửi email thông báo contact mới đến admin và bản sao đến người gửi.
 */

declare(strict_types=1);

namespace NukeViet\Module\contact\Jobs;

/**
 * SendContactEmail - Gửi email thông báo contact form
 *
 * Thay thế nv_sendmail_async trong funcs/main.php
 *
 * @example
 * // Gửi email đến admin
 * nv_dispatch_job('contact', 'SendContactEmail', [
 *     'type' => 'notify_admin',
 *     'contact_id' => 123,
 *     'to' => 'admin@example.com',
 *     'from_name' => 'Người gửi',
 *     'from_email' => 'sender@example.com',
 *     'subject' => 'Tiêu đề',
 *     'content' => 'Nội dung email HTML',
 *     'custom_headers' => ['References' => 'hash...'],
 * ]);
 *
 * // Gửi bản sao đến người gửi
 * nv_dispatch_job('contact', 'SendContactEmail', [
 *     'type' => 'sender_copy',
 *     'contact_id' => 123,
 *     'to' => 'sender@example.com',
 *     'from_name' => 'Site Name',
 *     'from_email' => 'noreply@example.com',
 *     'subject' => 'Tiêu đề',
 *     'content' => 'Nội dung email HTML',
 *     'custom_headers' => ['References' => 'hash...'],
 * ]);
 */
class SendContactEmail
{
    /**
     * Handle the job
     *
     * @param array $data Job data
     * @return bool
     */
    public static function handle(array $data): bool
    {
        global $global_config;

        $type = $data['type'] ?? 'notify_admin';
        $contactId = $data['contact_id'] ?? 0;
        $to = $data['to'] ?? '';
        $fromName = $data['from_name'] ?? $global_config['site_name'];
        $fromEmail = $data['from_email'] ?? $global_config['site_email'];
        $subject = $data['subject'] ?? '';
        $content = $data['content'] ?? '';
        $customHeaders = $data['custom_headers'] ?? [];

        self::log("SendContactEmail started: type={$type}, contact_id={$contactId}, to={$to}");

        // Validate required fields
        if (empty($to) || empty($subject) || empty($content)) {
            self::log("Missing required data: to, subject, or content", 'error');
            return false;
        }

        // Validate email format
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::log("Invalid email format: {$to}", 'error');
            return false;
        }

        try {
            $startTime = microtime(true);

            // Build from array
            $from = [$fromName, $fromEmail];

            // Send email using NukeViet's nv_sendmail function
            // Note: nv_sendmail is synchronous, but we're in a background worker
            if (!function_exists('nv_sendmail')) {
                require_once NV_ROOTDIR . '/includes/functions.php';
            }

            $result = nv_sendmail(
                $from,              // from
                $to,                // to
                $subject,           // subject
                $content,           // message
                '',                 // files
                false,              // from_admin
                false,              // to_admin
                [],                 // cc
                [],                 // bcc
                true,               // is_html
                $customHeaders      // custom_headers
            );

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($result) {
                self::log("Email sent successfully in {$duration}ms: {$type} -> {$to}");

                // Update contact record if needed
                if ($contactId > 0 && $type === 'notify_admin') {
                    self::updateContactEmailStatus($contactId, true);
                }

                return true;
            } else {
                self::log("Failed to send email: {$type} -> {$to}", 'error');

                // Log failure for retry or debugging
                if ($contactId > 0) {
                    self::updateContactEmailStatus($contactId, false);
                }

                return false;
            }
        } catch (\Throwable $e) {
            self::log("Exception sending email: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Update contact record with email status
     *
     * @param int $contactId
     * @param bool $success
     */
    protected static function updateContactEmailStatus(int $contactId, bool $success): void
    {
        global $db, $db_config;

        try {
            // Note: This is optional - you can add a column to track email status
            // $db->query("UPDATE {$db_config['prefix']}_contact_send SET email_sent=" . ($success ? 1 : 0) . " WHERE id=" . $contactId);
        } catch (\Throwable $e) {
            self::log("Failed to update email status: " . $e->getMessage(), 'warning');
        }
    }

    /**
     * Log message to stdout/stderr
     *
     * @param string $message
     * @param string $level
     */
    protected static function log(string $message, string $level = 'info'): void
    {
        if (PHP_SAPI === 'cli') {
            $timestamp = date('Y-m-d H:i:s');
            $levelUpper = strtoupper($level);
            $output = "[{$timestamp}] [SendContactEmail] [{$levelUpper}] {$message}\n";
            fwrite($level === 'error' ? STDERR : STDOUT, $output);
        }
    }
}
