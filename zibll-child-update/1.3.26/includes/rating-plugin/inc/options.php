<?php
/**
 * CSF 后台选项配置
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 注册 CSF 后台选项
 * 挂 after_setup_theme（Zibll 附加插件标准挂载点），确保 CSF 在 admin_menu 前完成注册
 */

function zrp_admin_csf_options()
{
    if (!is_admin()) {
        return;
    }

    if (!class_exists('CSF')) {
        return;
    }

    // 创建选项面板 — 使用独立页面 + menu_hidden 隐藏，再通过 admin_menu 挂载到子比菜单下
    // 避免 menu_type=submenu 模式中 CSF 内部实例迭代顺序不可控导致的 404 问题
    CSF::createOptions(ZRP_OPTIONS_KEY, array(
        'menu_title'      => __('文章评分', 'zibll-rating-plugin'),
        'menu_slug'       => 'zrp_rating_options',
        'menu_hidden'     => true,
        'framework_title' => __('文章评分插件设置', 'zibll-rating-plugin'),
        'theme'           => 'light',
        'footer_text'     => __('Zibll 文章评分插件 by 子阿卿', 'zibll-rating-plugin'),
    ));

    // 基础设置
    CSF::createSection(ZRP_OPTIONS_KEY, array(
        'title'  => __('基础设置', 'zibll-rating-plugin'),
        'icon'   => 'fa fa-star',
        'fields' => array(
            array(
                'id'      => 'rating_enable',
                'type'    => 'switcher',
                'title'   => __('启用评分功能', 'zibll-rating-plugin'),
                'default' => true,
            ),
            array(
                'id'         => 'rating_mode',
                'type'       => 'radio',
                'title'      => __('评分展示模式', 'zibll-rating-plugin'),
                'desc'       => __('选择评分组件在文章中的展示方式', 'zibll-rating-plugin'),
                'default'    => 'sidebar',
                'inline'     => true,
                'options'    => array(
                    'sidebar' => __('侧边栏式（文章正文后显示）', 'zibll-rating-plugin'),
                    'float'   => __('悬浮窗式（右下角浮动）', 'zibll-rating-plugin'),
                ),
                'dependency' => array('rating_enable', '==', 'true'),
            ),
            array(
                'id'         => 'rating_allow_update',
                'type'       => 'switcher',
                'title'      => __('允许修改评分', 'zibll-rating-plugin'),
                'desc'       => __('开启后用户可以修改自己的评分，否则只能评一次', 'zibll-rating-plugin'),
                'default'    => true,
                'dependency' => array('rating_enable', '==', 'true'),
            ),
        ),
    ));

    // 样式设置
    CSF::createSection(ZRP_OPTIONS_KEY, array(
        'title'  => __('样式设置', 'zibll-rating-plugin'),
        'icon'   => 'fa fa-paint-brush',
        'fields' => array(
            array(
                'id'      => 'star_color',
                'type'    => 'color',
                'title'   => __('星级激活颜色', 'zibll-rating-plugin'),
                'desc'    => __('默认使用主题颜色，可自定义', 'zibll-rating-plugin'),
                'default' => '',
            ),
            array(
                'id'      => 'float_bottom',
                'type'    => 'spinner',
                'title'   => __('悬浮窗距底部距离', 'zibll-rating-plugin'),
                'default' => 80,
                'min'     => 20,
                'max'     => 200,
                'step'    => 10,
                'unit'    => 'px',
                'desc'    => __('仅在悬浮窗模式下生效', 'zibll-rating-plugin'),
            ),
        ),
    ));

    // 排序集成
    CSF::createSection(ZRP_OPTIONS_KEY, array(
        'title'  => __('排序集成', 'zibll-rating-plugin'),
        'icon'   => 'fa fa-sort-amount-desc',
        'fields' => array(
            array(
                'type'    => 'content',
                'content' => '<div style="padding:15px;background:#f0f9eb;border-radius:8px;">
                    <h4 style="margin-top:0;">' . __('排序功能说明', 'zibll-rating-plugin') . '</h4>
                    <p>' . __('本插件已自动集成"按评分排序"功能，主题的文章列表小工具（文章列表(新)、多栏目文章(新)、横向滚动文章(新)）中已自动支持评分排序。', 'zibll-rating-plugin') . '</p>
                    <p>' . __('如需在文章列表页筛选栏中使用评分排序，请前往 <strong>子比主题设置 → 首页布局 → 文章列表</strong> 中的"显示排序方式"勾选"评分"即可。', 'zibll-rating-plugin') . '</p>
                </div>',
            ),
        ),
    ));
}

/**
 * 在子比主题菜单下手动挂载"文章评分"子菜单链接
 * 优先级 11，确保在 CSF 默认 admin_menu（优先级 10）创建父菜单后执行
 */
add_action('admin_menu', 'zrp_add_rating_submenu', 11);

function zrp_add_rating_submenu()
{
    add_submenu_page(
        'zibll_options',
        __('文章评分', 'zibll-rating-plugin'),
        __('文章评分', 'zibll-rating-plugin'),
        'manage_options',
        'zrp_rating_options'
    );
}
