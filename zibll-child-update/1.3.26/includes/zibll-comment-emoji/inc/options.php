<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 获取插件配置（外部可用）。
 */
function zibll_additional_demo01_get_option($key, $default = false)
{
    static $options = null;
    if ($options === null) {
        $options = get_option(ZIBLL_COMMENT_EMOJI_OPTIONS_KEY, array());
        if (!is_array($options)) {
            $options = array();
        }
        $options = wp_parse_args($options, zibll_additional_demo01_default_settings());
    }

    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * 使用 CSF 创建后台配置。
 */
function zibll_additional_demo01_admin_csf_options()
{
    if (!is_admin() || !class_exists('CSF')) {
        return;
    }

    $options_key = ZIBLL_COMMENT_EMOJI_OPTIONS_KEY;
    $defaults = zibll_additional_demo01_default_settings();
    $storage = zibll_additional_demo01_get_storage(true, true);
    $allowed_exts = zibll_additional_demo01_get_allowed_exts(zibll_additional_demo01_get_setting('allowed_exts', $defaults['allowed_exts']));
    $detected_groups = zibll_additional_demo01_get_detected_folder_groups();
    $accept = '.' . implode(',.', $allowed_exts);

    $uploader_html  = '<div class="zibll-emoji-admin-uploader">';
    $uploader_html .= '<p>在此页面直接上传表情图片，插件会自动创建目录和分组配置，无需手动操作文件。</p>';
    $uploader_html .= '<p><strong>当前存储目录：</strong><code>' . esc_html($storage['root']) . '</code></p>';
    $uploader_html .= '<div class="zibll-emoji-admin-row"><label>分组目录（英文）</label><input type="text" class="regular-text" name="zibll_emoji_group_dir" placeholder="例如：cat 或 acg"></div>';
    $uploader_html .= '<div class="zibll-emoji-admin-row"><label>分组显示名（可选）</label><input type="text" class="regular-text" name="zibll_emoji_group_title" placeholder="不填则显示文件夹名称"></div>';
    $uploader_html .= '<div class="zibll-emoji-admin-row"><label>选择图片（' . esc_html(implode(', ', $allowed_exts)) . '）</label><input type="file" name="zibll_emoji_files" accept="' . esc_attr($accept) . '" multiple></div>';
    $uploader_html .= '<div class="zibll-emoji-admin-row"><button type="button" class="button button-primary zibll-emoji-admin-upload-btn">上传并保存</button></div>';
    $uploader_html .= '<div class="zibll-emoji-admin-result"></div>';
    $uploader_html .= '</div>';
    $uploader_html .= '<hr><h4>已识别分组</h4>' . zibll_additional_demo01_get_group_list_html();

    CSF::createOptions($options_key, array(
        'menu_title'         => '评论表情包组',
        'menu_slug'          => $options_key,
        'framework_title'    => 'Zibll 评论表情包插件',
        'show_in_customizer' => false,
        'theme'              => 'light',
        'footer_text'        => 'BY 苏晨',
        'footer_credit'      => '',
    ));

    CSF::createSection($options_key, array(
        'title'  => '基础设置',
        'icon'   => 'fa fa-fw fa-smile-o',
        'fields' => array(
            array(
                'id'      => 'enabled',
                'type'    => 'switcher',
                'title'   => '启用功能',
                'default' => !empty($defaults['enabled']),
            ),
            array(
                'id'      => 'include_default_group',
                'type'    => 'switcher',
                'title'   => '显示默认表情组',
                'default' => !empty($defaults['include_default_group']),
            ),
            array(
                'id'      => 'default_group_label',
                'type'    => 'text',
                'title'   => '默认组名称',
                'default' => $defaults['default_group_label'],
            ),
            array(
                'id'      => 'smilies_root',
                'type'    => 'text',
                'title'   => '表情根目录（绝对路径）',
                'default' => $defaults['smilies_root'],
                'desc'    => '目录不可用时会自动回退到 uploads/zibll-comment-emoji/smilies',
            ),
            array(
                'id'      => 'smilies_base_url',
                'type'    => 'text',
                'title'   => '表情访问 URL',
                'default' => $defaults['smilies_base_url'],
            ),
            array(
                'id'      => 'allowed_exts',
                'type'    => 'text',
                'title'   => '允许后缀',
                'default' => $defaults['allowed_exts'],
                'desc'    => '英文逗号分隔，例如：gif,png,jpg,jpeg,webp',
            ),
            array(
                'id'      => 'max_per_group',
                'type'    => 'number',
                'title'   => '每组最多读取数量',
                'default' => (int) $defaults['max_per_group'],
                'unit'    => '张',
            ),
            array(
                'id'      => 'panel_width',
                'type'    => 'number',
                'title'   => '前台面板宽度',
                'default' => (int) $defaults['panel_width'],
                'unit'    => 'px',
            ),
            array(
                'id'                     => 'emoji_groups',
                'type'                   => 'group',
                'title'                  => '分组配置（可选）',
                'button_title'           => '添加分组',
                'accordion_title_number' => true,
                'default'                => $detected_groups,
                'desc'                   => '自动识别文件夹，目录名和显示名默认均为文件夹名。只改显示名即可自定义。',
                'fields'                 => array(
                    array(
                        'id'    => 'dir',
                        'type'  => 'text',
                        'title' => '目录名',
                        'desc'  => '留空时无效，建议与实际文件夹同名',
                    ),
                    array(
                        'id'    => 'title',
                        'type'  => 'text',
                        'title' => '显示名',
                        'desc'  => '留空时默认使用目录名',
                    ),
                ),
            ),
        ),
    ));

    CSF::createSection($options_key, array(
        'title'  => '后台上传',
        'icon'   => 'fa fa-fw fa-upload',
        'fields' => array(
            array(
                'type'    => 'content',
                'content' => $uploader_html,
            ),
        ),
    ));

    CSF::createSection($options_key, array(
        'title'  => '排序管理',
        'icon'   => 'fa fa-fw fa-sort',
        'fields' => array(
            array(
                'type'    => 'content',
                'content' => zibll_additional_demo01_get_sort_manager_html(),
            ),
        ),
    ));
}
add_action('zib_require_end', 'zibll_additional_demo01_admin_csf_options');
