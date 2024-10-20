<?php

/**
 * NukeViet Content Management System
 * @version 4.x
 * @author VINADES.,JSC <contact@vinades.vn>
 * @copyright (C) 2009-2023 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 * @see https://github.com/nukeviet The NukeViet CMS GitHub project
 */

if (!defined('NV_IS_FILE_THEMES')) {
    exit('Stop!!!');
}

$page_title = $nv_Lang->getModule('xcopyblock');

$selectthemes = $nv_Request->get_title('selectthemes', 'cookie', '');
$op = $nv_Request->get_string(NV_OP_VARIABLE, 'get', '');

$template = get_tpl_dir([$global_config['module_theme'], $global_config['admin_theme']], 'admin_default', '/modules/' . $module_file . '/xcopyblock.tpl');
$tpl = new \NukeViet\Template\NVSmarty();
$tpl->setTemplateDir(NV_ROOTDIR . '/themes/' . $template . '/modules/' . $module_file);
$tpl->assign('LANG', $nv_Lang);
$tpl->assign('MODULE_NAME', $module_name);
$tpl->assign('OP', $op);
$tpl->assign('CHECKSS', md5(NV_CHECK_SESSION . '_' . $module_name . '_' . $op . '_' . $admin_info['userid']));
$tpl->assign('SELECTTHEMES', $selectthemes);

$theme_list = nv_scandir(NV_ROOTDIR . '/themes/', $global_config['check_theme']);

$result = $db->query('SELECT DISTINCT theme FROM ' . NV_PREFIXLANG . '_modthemes WHERE func_id=0');
$array_themes = [];
while ([$theme] = $result->fetch(3)) {
    if (in_array($theme, $theme_list, true)) {
        $array_themes[] = $theme;
    }
}
$tpl->assign('ARRAY_THEMES', $array_themes);

$contents = $tpl->fetch('xcopyblock.tpl');

include NV_ROOTDIR . '/includes/header.php';
echo nv_admin_theme($contents);
include NV_ROOTDIR . '/includes/footer.php';
