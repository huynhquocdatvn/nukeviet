<?php
/**
 * Install Database Queue Table
 */
define('NV_SYSTEM', true);
define('NV_ROOTDIR', dirname(__DIR__));

$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['DOCUMENT_ROOT'] = NV_ROOTDIR;
require NV_ROOTDIR . '/includes/vendor/autoload.php';
require NV_ROOTDIR . '/includes/mainfile.php';

echo "=== Installing Database Queue Table ===\n";

global $db, $db_config;
$tableName = ($db_config['prefix'] ?? 'nv4') . '_queue_jobs';

$sql = "CREATE TABLE IF NOT EXISTS " . $tableName . " (
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

try {
    $db->query($sql);
    echo "Table '$tableName' created successfully (or already exists).\n";
} catch (\Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if table exists
try {
    $count = $db->query("SELECT COUNT(*) FROM " . $tableName)->fetchColumn();
    echo "Current jobs count: " . $count . "\n";
} catch (\Exception $e) {
    echo "Error verifying table: " . $e->getMessage() . "\n";
}
