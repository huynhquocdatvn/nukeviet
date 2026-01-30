<?php

namespace NukeViet\Module\users\Jobs;

/**
 * Job to send test email
 */
class SendTestEmailJob
{
    /**
     * Handle the job
     * 
     * @param array $data
     * @return bool
     */
    public static function handle(array $data): bool
    {
        global $global_config, $site_mods;

        echo "Started SendTestEmailJob with data: " . json_encode($data) . "\n";

        $to = $data['email'] ?? 'huynhquocdat.vn@gmail.com';
        $subject = $data['subject'] ?? 'Test Email from Queue Job';
        $message = $data['message'] ?? ('This is a test email sent from NukeViet Queue System at ' . date('Y-m-d H:i:s'));
        
        // Ensure site_email is available
        $from = $global_config['site_email'];
        if (empty($from)) {
            $from = 'noreply@' . $global_config['site_domain'];
        }

        echo "Sending email to: $to\n";
        
        // Basic check for nv_sendmail
        if (!function_exists('nv_sendmail')) {
             // Try to load functions if not available? Worker should have loaded core.
             echo "Error: nv_sendmail function not found!\n";
             return false;
        }

        // Send email
        // nv_sendmail($from, $to, $subject, $body)
        $result = nv_sendmail($from, $to, $subject, $message);
        
        if ($result) {
            echo "Email sent successfully.\n";
            return true;
        } else {
            echo "Failed to send email.\n";
            return false;
        }
    }
}
