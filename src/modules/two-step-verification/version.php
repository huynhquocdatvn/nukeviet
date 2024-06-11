<?php

/**
 * NukeViet Content Management System
 * @version 4.x
 * @author VINADES.,JSC <contact@vinades.vn>
 * @copyright (C) 2009-2023 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 * @see https://github.com/nukeviet The NukeViet CMS GitHub project
 */

if (!defined('NV_ADMIN') or !defined('NV_MAINFILE')) {
    exit('Stop!!!');
}

$module_version = [
    'name' => 'Two-Step Verification',
    'modfuncs' => 'main,setup,confirm',
    'submenu' => 'main,setup,confirm',
    'is_sysmod' => 1,
    'virtual' => 0,
    'version' => '4.6.00',
    'date' => 'Saturday, July 17, 2021 4:00:00 PM GMT+07:00',
    'author' => 'VINADES.,JSC <contact@vinades.vn>',
    'note' => 'Two-Step Verification',
    'icon' => 'fa-solid fa-shield-halved'
];
