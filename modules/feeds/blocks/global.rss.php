<?php

/**
 * NukeViet Content Management System
 * @version 4.x
 * @author VINADES.,JSC <contact@vinades.vn>
 * @copyright (C) 2009-2022 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 * @see https://github.com/nukeviet The NukeViet CMS GitHub project
 */

if (!defined('NV_MAINFILE')) {
    exit('Stop!!!');
}

if (!nv_function_exists('nv_block_data_config_rss')) {
    /**
     * nv_block_data_config_rss()
     *
     * @param string $module
     * @param array  $data_block
     * @param array  $lang_block
     * @return string
     */
    function nv_block_data_config_rss($module, $data_block, $lang_block)
    {
        global $lang_module;

        $return = '';

        $html = '<input class="form-control" name="config_url" type="text" value="' . $data_block['url'] . '"/>';
        $return .= '<div class="form-group"><label class="control-label col-sm-6">' . $lang_block['url'] . ':</label><div class="col-sm-18">' . $html . '</div></div>';

        $html = "<select class=\"form-control\" name=\"config_number\">\n";
        for ($index = 1; $index <= 50; ++$index) {
            $sel = ($index == $data_block['number']) ? ' selected' : '';
            $html .= '<option value="' . $index . '" ' . $sel . '>' . $index . "</option>\n";
        }
        $html .= "</select>\n";
        $return .= '<div class="form-group"><label class="control-label col-sm-6">' . $lang_block['number'] . ':</label><div class="col-sm-18">' . $html . '</div></div>';

        $data_block['title_length'] = isset($data_block['title_length']) ? (int) ($data_block['title_length']) : 0;
        $html = "<select class=\"form-control\" name=\"config_title_length\">\n";
        for ($index = 0; $index <= 255; ++$index) {
            $sel = ($index == $data_block['title_length']) ? ' selected' : '';
            $html .= '<option value="' . $index . '" ' . $sel . '>' . $index . "</option>\n";
        }
        $html .= "</select>\n";
        $return .= '<div class="form-group"><label class="control-label col-sm-6">' . $lang_block['title_length'] . ':</label><div class="col-sm-18">' . $html . '</div></div>';

        $sel = ((int) ($data_block['isdescription']) == 1) ? 'checked="checked"' : '';
        $html = '<input type="checkbox" name="config_isdescription" value="1" ' . $sel . ' /> ' . $lang_module['block_yes'] . "\n";
        $return .= '<div class="form-group"><label class="control-label col-sm-6">' . $lang_block['isdescription'] . '</label><div class="col-sm-18"><div class="checkbox"><label>' . $html . '</label></div></div></div>';

        $sel = ((int) ($data_block['ishtml']) == 1) ? 'checked="checked"' : '';
        $html = '<input type="checkbox" name="config_ishtml" value="1" ' . $sel . ' /> ' . $lang_module['block_yes'] . "\n";
        $return .= '<div class="form-group"><label class="control-label col-sm-6">' . $lang_block['ishtml'] . ':</label><div class="col-sm-18"><div class="checkbox"><label>' . $html . '</label></div></div></div>';

        $sel = ((int) ($data_block['ispubdate']) == 1) ? 'checked="checked"' : '';
        $html = '<input type="checkbox" name="config_ispubdate" value="1" ' . $sel . ' /> ' . $lang_module['block_yes'] . "\n";
        $return .= '<div class="form-group"><label class="control-label col-sm-6">' . $lang_block['ispubdate'] . ':</label><div class="col-sm-18"><div class="checkbox"><label>' . $html . '</label></div></div></div>';

        $sel = ((int) ($data_block['istarget']) == 1) ? 'checked="checked"' : '';
        $html = '<input type="checkbox" name="config_istarget" value="1" ' . $sel . ' /> ' . $lang_module['block_yes'] . "\n";

        $return .= '<div class="form-group"><label class="control-label col-sm-6">' . $lang_block['istarget'] . ':</label><div class="col-sm-18"><div class="checkbox"><label>' . $html . '</label></div></div></div>';

        return $return;
    }

    /**
     * nv_block_data_config_rss_submit()
     *
     * @param string $module
     * @param array  $lang_block
     * @return array
     */
    function nv_block_data_config_rss_submit($module, $lang_block)
    {
        global $nv_Request;
        $return = [];
        $return['error'] = [];
        $return['config'] = [];
        $return['config']['url'] = $nv_Request->get_title('config_url', 'post', '', 0);
        $return['config']['number'] = $nv_Request->get_int('config_number', 'post', 0);
        $return['config']['isdescription'] = $nv_Request->get_int('config_isdescription', 'post', 0);
        $return['config']['ishtml'] = $nv_Request->get_int('config_ishtml', 'post', 0);
        $return['config']['ispubdate'] = $nv_Request->get_int('config_ispubdate', 'post', 0);
        $return['config']['istarget'] = $nv_Request->get_int('config_istarget', 'post', 0);
        $return['config']['title_length'] = $nv_Request->get_int('config_title_length', 'post', 0);
        if (!nv_is_url($return['config']['url'])) {
            $return['error'][] = $lang_block['error_url'];
        }

        return $return;
    }

    function change_description($description, $alt = '')
    {
        if (!empty($description)) {
            $description = trim(strip_tags($description, '<img><br>'));
            // Remove all attributes from html tags
            $description = preg_replace("/<([a-z][a-z0-9]*)(?:[^>]*(\ssrc=['\"][^'\"]*['\"]))?[^>]*?(\/?)>/i", '<$1$2$3>', $description);
            $description = preg_replace('#<img.+src="([^"]+)"[^>]*>#i', '<img src="' . ASSETS_STATIC_URL . '/images/pix.svg" style="background-image:url($1)" alt="' . $alt . '" width="120" height="80"/>', $description);
            $description = preg_replace("/[\r\n]+/", ' ', $description);
            $description = preg_replace("/(\&nbsp\;|\s)+/", ' ', $description);
            $description = nv_clean60($description, 500, false);
        }

        return $description;
    }

    function change_link($link)
    {
        if (!empty($link)) {
            $link = trim(strip_tags($link));
            if (!nv_is_url($link)) {
                return '';
            }
        }

        return $link;
    }

    /**
     * nv_get_rss()
     *
     * @param string $url
     * @return array
     */
    function nv_get_rss($url)
    {
        global $nv_Cache;
        $array_data = [];
        $cache_file = NV_LANG_DATA . '_' . md5($url) . '_' . NV_CACHE_PREFIX . '.cache';
        if (($cache = $nv_Cache->getItem('rss', $cache_file, 3600)) != false) {
            $array_data = json_decode($cache, true);
        } else {
            $xml_source = url_get_contents($url);
            if (!empty($xml_source)) {
                $feed = new DOMDocument('1.0', 'utf-8');
                libxml_use_internal_errors(true);
                if ($feed->loadXML($xml_source)) {
                    if ($feed->getElementsByTagName('feed')->length > 0 && $feed->getElementsByTagName('rss')->length <= 0) {
                        foreach ($feed->getElementsByTagName('entry') as $item) {
                            $links = $item->getElementsByTagName('link');
                            $itemlLink = $links->item(0)->getAttribute('href');
                            if ($item->getElementsByTagName('content')->length) {
                                $description = $item->getElementsByTagName('content')->item(0)->nodeValue;
                            } elseif ($item->getElementsByTagName('summary')->length) {
                                $description = $item->getElementsByTagName('summary')->item(0)->nodeValue;
                            }
                            $title = trim(strip_tags($item->getElementsByTagName('title')->item(0)->nodeValue));
                            $pubtime = strtotime($item->getElementsByTagName('updated')->item(0)->nodeValue);
                            $key = $pubtime . '-' . $title;

                            $array_data[$key] = [
                                'title' => $title,
                                'description' => change_description($description, $title),
                                'pubtime' => $pubtime,
                                'link' => change_link($itemlLink)
                            ];
                        }
                    } else {
                        foreach ($feed->getElementsByTagName('item') as $item) {
                            $title = trim(strip_tags($item->getElementsByTagName('title')->item(0)->nodeValue));
                            $pubtime = strtotime($item->getElementsByTagName('pubDate')->item(0)->nodeValue);
                            $key = $pubtime . '-' . $title;

                            $array_data[$key] = [
                                'title' => $title,
                                'description' => change_description($item->getElementsByTagName('description')->item(0)->nodeValue, $title),
                                'pubtime' => $pubtime,
                                'link' => change_link($item->getElementsByTagName('link')->item(0)->nodeValue)
                            ];
                        }
                    }

                    krsort($array_data);
                }
            }

            $cache = json_encode(array_values($array_data));
            $nv_Cache->setItem('rss', $cache_file, $cache);
        }

        return $array_data;
    }

    /**
     * nv_block_global_rss()
     *
     * @param array $block_config
     * @return string
     */
    function nv_block_global_rss($block_config)
    {
        global $global_config;

        if (file_exists(NV_ROOTDIR . '/themes/' . $global_config['module_theme'] . '/modules/feeds/global.rss.tpl')) {
            $block_theme = $global_config['module_theme'];
        } elseif (file_exists(NV_ROOTDIR . '/themes/' . $global_config['site_theme'] . '/modules/feeds/global.rss.tpl')) {
            $block_theme = $global_config['site_theme'];
        } else {
            $block_theme = 'default';
        }

        $a = 1;
        $xtpl = new XTemplate('global.rss.tpl', NV_ROOTDIR . '/themes/' . $block_theme . '/modules/feeds');
        $array_rrs = nv_get_rss($block_config['url']);
        $title_length = isset($block_config['title_length']) ? (int) ($block_config['title_length']) : 0;
        foreach ($array_rrs as $item) {
            if ($a <= $block_config['number']) {
                $item['description'] = ($block_config['ishtml']) ? $item['description'] : (!empty($item['description']) ? strip_tags($item['description']) : '');
                $item['target'] = ($block_config['istarget']) ? ' data-target="_blank"' : '';
                $item['class'] = ($a % 2 == 0) ? 'second' : '';
                if ($title_length > 0) {
                    $item['text'] = nv_clean60($item['title'], $title_length);
                } else {
                    $item['text'] = $item['title'];
                }
                $item['pubDate'] = !empty($item['pubtime']) ? nv_date('l - d/m/Y H:i', $item['pubtime']) : '';
                $xtpl->assign('DATA', $item);
                if (!empty($item['description']) and $block_config['isdescription']) {
                    $xtpl->parse('main.loop.description');
                }
                if (!empty($item['pubDate']) and $block_config['ispubdate']) {
                    $xtpl->parse('main.loop.pubDate');
                }
                $xtpl->parse('main.loop');
                ++$a;
            } else {
                break;
            }
        }
        $xtpl->parse('main');

        return $xtpl->text('main');
    }
}

if (defined('NV_SYSTEM')) {
    $content = nv_block_global_rss($block_config);
}
