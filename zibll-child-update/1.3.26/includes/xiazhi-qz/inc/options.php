<?php
/*禁止倒卖*/
if (!defined('ABSPATH')) {
    exit;
}

function xiazhi_qz_get_option($key, $default = false)
{
    static $options = null;

    if ($options === null) {
        $options = get_option('xiazhi_qz_options');
        $options = is_array($options) ? $options : array();
    }

    return $options[$key] ?? $default;
}

function xiazhi_qz_create_options()
{
    if (!is_admin() || !class_exists('CSF')) {
        return;
    }

    $prefix = 'xiazhi_qz_options';

    CSF::createOptions($prefix, array(
        'menu_title'         => '标题前缀设置',
        'menu_slug'          => $prefix,
        'menu_capability'    => 'manage_options',
        'framework_title'    => '标题前缀设置',
        'show_in_customizer' => false,
        'footer_text'        => '夏稚自定义文章前缀',
        'footer_credit'      => '<i class="fa fa-fw fa-heart-o" aria-hidden="true"></i>',
        'theme'              => 'light',
    ));

    CSF::createSection($prefix, array(
        'title'  => '前台权限',
        'icon'   => 'fa fa-fw fa-sliders',
        'fields' => array(
            array(
                'id'      => 'frontend_prefix_s',
                'type'    => 'switcher',
                'title'   => '开启前台标题前缀',
                'label'   => '开启后，前台投稿/编辑页显示标题前缀设置并允许保存',
                'default' => true,
            ),
            array(
                'id'         => 'frontend_admin_only',
                'type'       => 'switcher',
                'title'      => '仅管理员可设置',
                'label'      => '开启后，仅管理员账号在前台投稿/编辑时可设置标题前缀',
                'default'    => false,
                'dependency' => array('frontend_prefix_s', '!=', ''),
            ),
            array(
                'id'         => 'frontend_vip_only',
                'type'       => 'switcher',
                'title'      => '仅会员可设置',
                'label'      => '开启后，非会员用户前台不可设置标题前缀',
                'default'    => false,
                'dependency' => array('frontend_prefix_s', '!=', ''),
            ),
            array(
                'id'         => 'frontend_auth_only',
                'type'       => 'switcher',
                'title'      => '仅认证用户可设置',
                'label'      => '开启后，未认证用户前台不可设置标题前缀',
                'default'    => false,
                'dependency' => array('frontend_prefix_s', '!=', ''),
            ),
        ),
    ));
}
add_action('zib_require_end', 'xiazhi_qz_create_options');
