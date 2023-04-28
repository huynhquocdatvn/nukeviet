<?php

/**
 * NukeViet Content Management System
 * @version 4.x
 * @author VINADES.,JSC <contact@vinades.vn>
 * @copyright (C) 2009-2022 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 * @see https://github.com/nukeviet The NukeViet CMS GitHub project
 */

namespace NukeViet\Module\push\uapi;

use NukeViet\Uapi\Uapi;
use NukeViet\Uapi\UapiResult;
use NukeViet\Uapi\UiApi;
use PDO;

if (!defined('NV_MAINFILE')) {
    exit('Stop!!!');
}

/**
 * NukeViet\Module\push\uapi\PushGroupAction
 * API dùng để thêm/sửa/xóa thông báo đẩy dành cho trưởng nhóm
 *
 * @package NukeViet\Module\push\uapi
 * @author VINADES.,JSC <contact@vinades.vn>
 * @copyright (C) 2009-2022 VINADES.,JSC. All rights reserved
 * @version 4.6.00
 * @access public
 */
class PushGroupAction implements UiApi
{
    private $result;

    /**
     * @return string
     */
    public static function getCat()
    {
        return 'Action';
    }

    /**
     * setResultHander()
     *
     * @return mixed
     */
    public function setResultHander(UapiResult $result)
    {
        $this->result = $result;
    }

    /**
     * execute()
     *
     * @return mixed
     */
    public function execute()
    {
        global $db, $nv_Request, $lang_module;

        $module_name = Uapi::getModuleName();
        $module_info = Uapi::getModuleInfo();
        $module_data = $module_info['module_data'];
        $module_file = $module_info['module_file'];
        $user_id = Uapi::getUserId();
        $user_groups = Uapi::getUserGroups();
        $u_groups = array_values(array_unique(array_filter(array_map(function ($gr) {
            return $gr >= 10 ? (int) $gr : 0;
        }, $user_groups))));

        include NV_ROOTDIR . '/modules/' . $module_file . '/language/' . NV_LANG_INTERFACE . '.php';

        $group_id = $nv_Request->get_int('group_id', 'post', 0);
        if (empty($group_id) or !in_array($group_id, $u_groups, true)) {
            return $this->result->setError()
                ->setCode('5014')
                ->setMessage($lang_module['group_not_defined'])
                ->getResult();
        }

        $count = $db->query('SELECT COUNT(*) FROM ' . NV_USERS_GLOBALTABLE . '_groups_users WHERE group_id=' . $group_id . ' AND is_leader=1 AND userid=' . $user_id)->fetchColumn();
        if (!$count) {
            return $this->result->setError()
                ->setCode('5015')
                ->setMessage($lang_module['not_group_manager'])
                ->getResult();
        }

        $where = "(mtb.sender_role='group' AND mtb.sender_group=" . $group_id . ')';

        $postdata = [];
        if ($nv_Request->isset_request('operation', 'post')) {
            $postdata['operation'] = $nv_Request->get_title('operation', 'post', '');
        }
        if ($nv_Request->isset_request('id', 'post')) {
            $postdata['id'] = $nv_Request->get_int('id', 'post', 0);
        }
        if ($nv_Request->isset_request('receiver_ids', 'post')) {
            $postdata['receiver_ids'] = $nv_Request->get_typed_array('receiver_ids', 'post', 'int', []);
        }
        if ($nv_Request->isset_request('message', 'post')) {
            $postdata['message'] = $nv_Request->get_title('message', 'post', '');
        }
        if ($nv_Request->isset_request('link', 'post')) {
            $postdata['link'] = $nv_Request->get_title('link', 'post', '');
        }
        if ($nv_Request->isset_request('add_time', 'post')) {
            $postdata['add_time'] = $nv_Request->get_title('add_time', 'post', '');
        }
        if ($nv_Request->isset_request('add_hour', 'post')) {
            $postdata['add_hour'] = $nv_Request->get_int('add_hour', 'post', 0);
        }
        if ($nv_Request->isset_request('add_min', 'post')) {
            $postdata['add_min'] = $nv_Request->get_int('add_min', 'post', 0);
        }
        if ($nv_Request->isset_request('exp_time', 'post')) {
            $postdata['exp_time'] = $nv_Request->get_title('exp_time', 'post', '');
        }
        if ($nv_Request->isset_request('exp_hour', 'post')) {
            $postdata['exp_hour'] = $nv_Request->get_int('exp_hour', 'post', -1);
        }
        if ($nv_Request->isset_request('exp_min', 'post')) {
            $postdata['exp_min'] = $nv_Request->get_int('exp_min', 'post', -1);
        }

        // Không có dữ liệu
        if (empty($postdata)) {
            return $this->result->setError()
                ->setCode('5001')
                ->setMessage('No data')
                ->getResult();
        }
        // Nếu không xác định được hành động
        if (empty($postdata['operation']) or !in_array($postdata['operation'], ['add', 'edit', 'delete'], true)) {
            return $this->result->setError()
                ->setCode('5002')
                ->setMessage($lang_module['unspecified_action'])
                ->getResult();
        }

        if ($postdata['operation'] == 'edit' or $postdata['operation'] == 'delete') {
            // Nếu thông báo đẩy chưa được xác định
            if (empty($postdata['id'])) {
                return $this->result->setError()
                    ->setCode('5003')
                    ->setMessage($lang_module['notification_not_exist'])
                    ->getResult();
            }

            $where .= ' AND (mtb.id=' . $postdata['id'] . ')';
            $exist = $db->query('SELECT COUNT(*) FROM ' . NV_PUSH_GLOBALTABLE . ' AS mtb WHERE ' . $where . ' LIMIT 1')->fetchColumn();
            // Nếu thông báo đẩy không tồn tại
            if (empty($exist)) {
                return $this->result->setError()
                    ->setCode('5004')
                    ->setMessage($lang_module['notification_not_exist'])
                    ->getResult();
            }
        }

        /*ksort($postdata);
        $checkhash = http_build_query($postdata);
        $checkhash = sha1($checkhash);
        $hashreceive = $nv_Request->get_title('checkhash', 'post', '');
        // Nễu mã hash không đúng
        if (strcasecmp($checkhash, $hashreceive) !== 0) {
            return $this->result->setError()
                ->setCode('5005')
                ->setMessage($lang_module['api_error_hash'])
                ->getResult();
        }*/

        // Nếu là xóa thông báo đẩy
        if ($postdata['operation'] == 'delete') {
            $db->query('DELETE FROM ' . NV_PUSH_STATUS_GLOBALTABLE . ' WHERE pid = ' . $postdata['id']);
            $db->query('DELETE FROM ' . NV_PUSH_GLOBALTABLE . ' WHERE id = ' . $postdata['id']);
            $db->query('OPTIMIZE TABLE ' . NV_PUSH_STATUS_GLOBALTABLE);
            $db->query('OPTIMIZE TABLE ' . NV_PUSH_GLOBALTABLE);
        }
        // Nếu là thêm/sửa thông báo
        elseif ($postdata['operation'] == 'add' or $postdata['operation'] == 'edit') {
            // Nếu nội dung của thông báo đẩy chưa được xác định
            if (nv_strlen($postdata['message']) < 3) {
                return $this->result->setError()
                    ->setCode('5009')
                    ->setMessage($lang_module['please_enter_content'])
                    ->getResult();
            }
            // Nếu có link, nhưng link không hợp lệ
            if (!empty($postdata['link']) and !nv_is_url($postdata['link'], true)) {
                return $this->result->setError()
                    ->setCode('5010')
                    ->setMessage($lang_module['please_enter_valid_link'])
                    ->getResult();
            }
            $add_time_array = [];
            // Nếu thời gian bắt đầu của thông báo đẩy không hợp lệ
            if (!preg_match('/^([0-9]{2})\/([0-9]{2})\/([0-9]{4})/', $postdata['add_time'], $add_time_array)) {
                return $this->result->setError()
                    ->setCode('5011')
                    ->setMessage($lang_module['please_enter_valid_add_time'])
                    ->getResult();
            }
            $exp_time_array = [];
            // Nếu thời gian kết thúc của thông báo đẩy không hợp lệ
            if (!empty($postdata['exp_time']) and !preg_match('/^([0-9]{2})\/([0-9]{2})\/([0-9]{4})/', $postdata['exp_time'], $exp_time_array)) {
                return $this->result->setError()
                    ->setCode('5012')
                    ->setMessage($lang_module['please_enter_valid_exp_time'])
                    ->getResult();
            }

            $postdata['receiver_ids'] = !empty($postdata['receiver_ids']) ? implode(',', $postdata['receiver_ids']) : '';
            $postdata['message'] = nv_nl2br($postdata['message'], '<br/>');
            if (!empty($postdata['link']) and !preg_match('#^https?\:\/\/#', $postdata['link'])) {
                str_starts_with($postdata['link'], NV_BASE_SITEURL) && $postdata['link'] = substr($postdata['link'], strlen(NV_BASE_SITEURL));
            }
            $postdata['add_time'] = mktime($postdata['add_hour'], $postdata['add_min'], 0, $add_time_array[2], $add_time_array[1], $add_time_array[3]);
            if (!empty($exp_time_array)) {
                $postdata['exp_hour'] == -1 && $postdata['exp_hour'] = 23;
                $postdata['exp_min'] == -1 && $postdata['exp_min'] = 59;
                $postdata['exp_time'] = mktime($postdata['exp_hour'], $postdata['exp_min'], 0, $exp_time_array[2], $exp_time_array[1], $exp_time_array[3]);
            } else {
                $postdata['exp_time'] = 0;
            }
            if ($postdata['operation'] == 'edit') {
                $sth = $db->prepare('UPDATE ' . NV_PUSH_GLOBALTABLE . ' SET 
                receiver_ids = :receiver_ids, message = :message, link = :link, add_time = ' . $postdata['add_time'] . ', exp_time = ' . $postdata['exp_time'] . '
                WHERE id = ' . $postdata['id']);
            } else {
                $sth = $db->prepare('INSERT INTO ' . NV_PUSH_GLOBALTABLE . " 
                (receiver_ids, sender_role, sender_group, sender_admin, message, link, add_time, exp_time) VALUES 
                (:receiver_ids, 'group', " . $group_id . ', 0, :message, :link, ' . $postdata['add_time'] . ', ' . $postdata['exp_time'] . ')');
            }

            $sth->bindValue(':receiver_ids', $postdata['receiver_ids'], PDO::PARAM_STR);
            $sth->bindValue(':message', $postdata['message'], PDO::PARAM_STR);
            $sth->bindValue(':link', $postdata['link'], PDO::PARAM_STR);
            $sth->execute();
        }

        $this->result->setSuccess();

        return $this->result->getResult();
    }
}
