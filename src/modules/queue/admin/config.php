<?php

/**
 * @Project NUKEVIET 5.x
 * @Author Antigravity
 * @Copyright (C) 2026
 * @License GNU/GPL version 2 or any later version
 */

if (!defined('NV_IS_FILE_ADMIN')) {
    exit('Stop!!!');
}

$page_title = $lang_module['config'] ?? 'Cấu hình';

// Display Queue Status
$queueEnabled = !empty($global_config['sys_use_queue']);
$queueDriver = $global_config['queue_driver'] ?? 'redis (default)';

$contents = '<div class="panel panel-default">
    <div class="panel-heading">' . ($lang_module['config'] ?? 'Cấu hình') . '</div>
    <div class="panel-body">';

if ($queueEnabled) {
    $contents .= '<div class="alert alert-success"><strong>Trạng thái:</strong> ĐANG BẬT (Enabled)</div>';
} else {
    $contents .= '<div class="alert alert-danger"><strong>Trạng thái:</strong> ĐANG TẮT (Disabled)</div>';
    $contents .= '<p>Để bật, hãy thêm dòng sau vào <code>config.php</code>:</p>';
    $contents .= '<pre>$global_config[\'sys_use_queue\'] = 1;</pre>';
}

$contents .= '<hr>';
$contents .= '<h4>Cấu hình Driver</h4>';
$contents .= '<p><strong>Driver hiện tại:</strong> <code>' . $queueDriver . '</code></p>';
$contents .= '<p>Các driver hỗ trợ: <code>redis</code>, <code>database</code>, <code>sync</code> (khi tắt queue).</p>';

$contents .= '    </div>
</div>';

include NV_ROOTDIR . '/includes/header.php';
echo nv_admin_theme($contents);
include NV_ROOTDIR . '/includes/footer.php';
