<?php
/*禁止倒卖*/
if (!defined('ABSPATH')) {
    exit;
}

function xiazhi_qz_prefix_images()
{
    $base_url = XIAZHI_QZ_URL . 'assets/img/';

    return array(
        'shice'       => $base_url . 'shice.webp',
        'dujia'       => $base_url . 'dujia.webp',
        'shoufa'      => $base_url . 'shoufa.webp',
        'temai'       => $base_url . 'temai.webp',
        'miaosha'     => $base_url . 'miaosha.webp',
        'baiyibutie'  => $base_url . 'baiyibutie.webp',
    );
}

function xiazhi_qz_create_metabox()
{
    if (!class_exists('CSF')) {
        return;
    }

    CSF::createMetabox('xiazhi_qz_titles', array(
        'title'     => '标题前缀',
        'post_type' => 'post',
        'context'   => 'advanced',
        'data_type' => 'unserialize',
        'priority'  => 'high',
    ));

    CSF::createSection('xiazhi_qz_titles', array(
        'fields' => array(
            array(
                'id'      => 'titles_moshi',
                'type'    => 'radio',
                'title'   => '模式选择',
                'desc'    => '图片模式支持多选预设前缀，也可选择自定义图片前缀',
                'inline'  => true,
                'options' => array(
                    'img'  => '图片',
                    'text' => '文字',
                ),
                'default' => 'img',
            ),
            array(
                'id'         => 'text',
                'type'       => 'text',
                'title'      => '文字模式',
                'desc'       => '建议两个字',
                'dependency' => array('titles_moshi', '==', 'text'),
            ),
            array(
                'id'         => 'text_bg_color',
                'type'       => 'palette',
                'title'      => '背景颜色',
                'desc'       => '部分颜色带有文字颜色，其余默认白色',
                'class'      => 'compact skin-color',
                'default'    => 'jb-vip2',
                'options'    => class_exists('CFS_Module') ? CFS_Module::zib_palette(array(), array('jb')) : array(),
                'dependency' => array('titles_moshi', '==', 'text'),
            ),
            array(
                'id'         => 'img',
                'type'       => 'image_select',
                'title'      => '选择预设图片前缀',
                'desc'       => '可同时选择多个图片前缀，前台按预设排列顺序显示',
                'multiple'   => true,
                'options'    => xiazhi_qz_prefix_images(),
                'dependency' => array('titles_moshi', '==', 'img'),
            ),
            array(
                'id'          => 'custom_img_prefixes',
                'type'        => 'gallery',
                'title'       => '自定义图片前缀',
                'desc'        => '从媒体库选择多张图片作为标题前缀',
                'add_title'   => '选择前缀图片',
                'edit_title'  => '编辑前缀图片',
                'clear_title' => '清空前缀图片',
                'dependency'  => array('titles_moshi', '==', 'img'),
            ),
        ),
    ));
}

add_action('zib_require_end', 'xiazhi_qz_create_metabox');

function xiazhi_qz_get_selected_prefix_keys($value)
{
    if (empty($value)) {
        return array();
    }

    $keys = is_array($value) ? $value : array($value);
    $keys = array_map('sanitize_key', $keys);

    return array_values(array_intersect(array_keys(xiazhi_qz_prefix_images()), $keys));
}

function xiazhi_qz_get_custom_prefix_urls($value)
{
    if (empty($value)) {
        return array();
    }

    $urls = array();
    foreach ((is_array($value) ? $value : explode(',', $value)) as $item) {
        $attachment_id = is_array($item) ? absint($item['id'] ?? 0) : absint($item);
        $url = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'full') : '';

        if (!$url && is_array($item) && !empty($item['url'])) {
            $url = $item['url'];
        }

        if (!$url && is_string($item) && filter_var($item, FILTER_VALIDATE_URL)) {
            $url = $item;
        }

        if ($url) {
            $urls[] = esc_url_raw($url);
        }
    }

    return array_values(array_filter(array_unique($urls)));
}

function xiazhi_qz_prefix_img($url, $alt = 'prefix')
{
    return $url ? '<img class="xiazhi_qz_prefix_img" src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" style="height:20px;pointer-events:none;margin-right:3px;vertical-align:-3px;"/>' : '';
}

function xiazhi_qz_title_prefix_html($post_id)
{
    if (get_post_meta($post_id, 'titles_moshi', true) === 'text') {
        $text = get_post_meta($post_id, 'text', true);
        $color = get_post_meta($post_id, 'text_bg_color', true);
        return $text === '' ? '' : '<span class="xiazhi_qz_prefix ' . esc_attr($color) . '">' . esc_html($text) . '</span> ';
    }

    $html = '';
    $images = xiazhi_qz_prefix_images();
    $selected = xiazhi_qz_get_selected_prefix_keys(get_post_meta($post_id, 'img', true));

    foreach ($selected as $key) {
        $html .= xiazhi_qz_prefix_img($images[$key], $key);
    }

    foreach (xiazhi_qz_get_custom_prefix_urls(get_post_meta($post_id, 'custom_img_prefixes', true)) as $index => $url) {
        $html .= xiazhi_qz_prefix_img($url, 'custom-prefix-' . ($index + 1));
    }

    return $html;
}

/**
 * 判断指定文章标题是否应注入前缀（统一注入逻辑）
 * ----------------------------------------------------------------
 * 通过 WordPress 标准 the_title 过滤器注入：凡经过 get_the_title()
 * 渲染的文章标题都会命中本逻辑，从而在全站范围内保持一致：
 *   - 文章列表页（archive / home / search / tag / category）
 *   - 侧边栏文章小工具：含 mini 紧凑列表与卡片列表。zibll 的
 *     zib_posts_mian_list_list / zib_posts_mini_while / zib_posts_mian_list_card
 *     等函数均经 get_the_title() 渲染标题，故统一生效。
 *   - 相关文章推荐（同样走 get_the_title()）
 *   - 文章详情页标题：hero 区与 <h1 class="article-title"> 均调用
 *     get_the_title()，旧逻辑因排除 is_singular() 而缺失，现已覆盖。
 *
 * 排除场景（避免出现错误位置的前缀）：
 *   - 后台编辑界面（is_admin 且非 AJAX）
 *   - RSS / feed 输出（is_feed）
 *   - REST API 请求（避免污染 title.rendered 等机器可读接口）
 *   - 非 post 文章类型（页面、nav_menu_item 导航菜单项、媒体等）
 *   - $id 为空（小工具自身标题、无明确文章来源的文本）
 * 注意：子比「文章列表(新)」等小工具默认 ajax 懒加载，前台经 admin-ajax.php 请求，
 *       此时 is_admin() 为 true 但 wp_doing_ajax() 也为 true，不属于「is_admin 且非 AJAX」，
 *       故不在排除之列，前缀照常注入（这正是本修复要解决的问题）。
 *
 * 历史说明：早期版本依赖「调用栈中是否含有 zib_get_posts_list_title」
 * 来决定是否注入。但 zibll 的 mini 紧凑列表（zib_posts_mini_while）等
 * 场景直接调用 get_the_title() 而不经过该函数，导致侧边栏小工具缺失
 * 前缀；此外旧逻辑排除 is_singular() 又使详情页标题缺失前缀。改为纯
 * post_type + 上下文判断后，所有经 get_the_title() 渲染的标题统一生效。
 */
function xiazhi_qz_should_apply_title_prefix($post_id)
{
    // 关键修复：子比小工具 ajax 懒加载时 is_admin() 为 true，必须放行前台 AJAX，
    // 仅排除「is_admin 且非 AJAX」的真实后台，否则前台列表前缀被误砍。
    if (!$post_id || (is_admin() && !wp_doing_ajax()) || is_feed() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }

    if (get_post_type($post_id) !== 'post') {
        return false;
    }

    // ── 排除「标题非主要展示」场景 ──
    // 这些场景中 get_the_title() 的返回值会被用于：
    //   • 图形卡片的 alt 属性 / text2 截断文本 / style-3 左下角绝对定位容器
    //   • 搜索框热门文章的 swiper-slide 卡片
    //   注入前缀 HTML 会导致样式突兀、HTML 被截断、或 alt 属性含标签。
    // 通过调用栈检测（仅向上查 6 帧，开销极小）排除已知问题函数。
    $exclude_functions = array(
        'zib_get_search_posts',   // 搜索框热门文章 → 标题进 card[text1/text2/alt]
        'zib_graphic_card',       // 图形卡片 → 标题进受限容器/alt/截断
    );
    if (function_exists('debug_backtrace')) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
        // $trace[0] = 本函数, [1] = apply_prefixes_to_title, [2] = the_title 过滤器调用点
        // 检查 [3] 及以上是否有排除函数
        for ($i = 3; $i < count($trace); $i++) {
            if (isset($trace[$i]['function']) && in_array($trace[$i]['function'], $exclude_functions, true)) {
                return false;
            }
        }
    }

    return true;
}

function xiazhi_qz_apply_prefixes_to_title($title, $id = null)
{
    if (!xiazhi_qz_should_apply_title_prefix($id)) {
        return $title;
    }

    $prefix = xiazhi_qz_title_prefix_html($id);

    return $prefix ? $prefix . $title : $title;
}

add_filter('the_title', 'xiazhi_qz_apply_prefixes_to_title', 10, 2);

function xiazhi_qz_title_prefix_admin_style()
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'post') {
        return;
    }
    ?>
    <style>
        #xiazhi_qz_titles .csf-field-image_select .csf--image {
            margin: 0 8px 8px 0;
            vertical-align: top;
        }

        #xiazhi_qz_titles .csf-field-image_select figure {
            width: 112px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 8px;
            box-sizing: border-box;
            border-radius: 4px;
        }

        #xiazhi_qz_titles .csf-field-image_select img {
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: 100%;
            display: block;
        }
    </style>
    <?php
}

add_action('admin_head-post.php', 'xiazhi_qz_title_prefix_admin_style');
add_action('admin_head-post-new.php', 'xiazhi_qz_title_prefix_admin_style');

function xiazhi_qz_current_user_can_frontend_prefix()
{
    if (!xiazhi_qz_get_option('frontend_prefix_s', true)) {
        return false;
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        return false;
    }

    $is_admin = is_super_admin($user_id) || user_can($user_id, 'manage_options');
    if (xiazhi_qz_get_option('frontend_admin_only', false)) {
        return $is_admin;
    }

    if ($is_admin) {
        return true;
    }

    if (xiazhi_qz_get_option('frontend_vip_only', false)) {
        if (!function_exists('zib_get_user_vip_level') || !zib_get_user_vip_level($user_id)) {
            return false;
        }
    }

    if (xiazhi_qz_get_option('frontend_auth_only', false)) {
        if (!function_exists('zib_is_user_auth') || !zib_is_user_auth($user_id)) {
            return false;
        }
    }

    return true;
}

function xiazhi_qz_frontend_title_prefix_box($post_id = 0)
{
    if (!xiazhi_qz_get_option('frontend_prefix_s', true)) {
        return '';
    }

    if (!get_current_user_id()) {
        return '<div class="zib-widget mb10-sm mb20 signin-loader"><div class="flex ac jsb drop-btn"><div class="flex ac"><i class="fa fa-font mr6"></i>标题前缀</div><div class="flex ac"><span class="em09 muted-2-color">请登录</span><i class="ml6 fa fa-angle-right em12"></i></div></div></div>';
    }

    if (!xiazhi_qz_current_user_can_frontend_prefix()) {
        return '';
    }

    $moshi = get_post_meta($post_id, 'titles_moshi', true);
    $moshi = $moshi ?: 'img';
    $text = get_post_meta($post_id, 'text', true);
    $text_bg_color = get_post_meta($post_id, 'text_bg_color', true);
    $text_bg_color = $text_bg_color ?: 'jb-vip2';
    $img = get_post_meta($post_id, 'img', true);

    $prefix_images = xiazhi_qz_prefix_images();
    $palette_options = class_exists('CFS_Module') ? CFS_Module::zib_palette(array(), array('jb')) : array();

    $html = '<div class="zib-widget mb10-sm mb20">';
    $html .= '<div class="title-theme mb10">标题前缀</div>';

    $html .= '<div class="xiazhi-qz-mode flex ac jsb padding-h10 border-bottom">';
    $html .= '<div class="muted-color">模式选择</div>';
    $html .= '<div class="but-average radius em09 form-but-radio">';
    $html .= '<label><input type="radio" name="titles_moshi" value="img" ' . checked($moshi, 'img', false) . '><span class="but but-radio p2-10 pointer"><i class="fa fa-picture-o mr6"></i>图片</span></label>';
    $html .= '<label><input type="radio" name="titles_moshi" value="text" ' . checked($moshi, 'text', false) . '><span class="but but-radio p2-10 pointer"><i class="fa fa-i-cursor mr6"></i>文字</span></label>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="mt10" data-controller="titles_moshi" data-condition="==" data-value="text" ' . ($moshi !== 'text' ? 'style="display: none;"' : '') . '>';
    $html .= '<div class="relative mb10">';
    $html .= '<div class="flex ab">';
    $html .= '<div class="muted-color mb6 flex0"><i class="fa fa-tag mr6"></i>文字前缀</div>';
    $html .= '<input type="text" name="text" value="' . esc_attr($text) . '" class="line-form-input text-right" maxlength="8" placeholder="建议两个字">';
    $html .= '<i class="line-form-line"></i>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="mb6">';
    $html .= '<div class="muted-color mb6"><i class="fa fa-magic mr6"></i>背景颜色</div>';
    $html .= '<div class="xiazhi-qz-palettes">';
    foreach ($palette_options as $key => $colors) {
        $active = ($key === $text_bg_color) ? ' xiazhi-qz-active' : '';
        $checked = ($key === $text_bg_color) ? ' checked' : '';
        $html .= '<div class="xiazhi-qz-palette' . esc_attr($active) . '">';
        if (!empty($colors)) {
            foreach ($colors as $color) {
                $html .= '<span style="background: ' . esc_attr($color) . ';"></span>';
            }
        }
        $html .= '<input type="radio" name="text_bg_color" value="' . esc_attr($key) . '"' . $checked . '/>';
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="mt10" data-controller="titles_moshi" data-condition="==" data-value="img" ' . ($moshi !== 'img' ? 'style="display: none;"' : '') . '>';
    $html .= '<div class="mb6">';
    $html .= '<div class="muted-color mb6"><i class="fa fa-picture-o mr6"></i>预设图片</div>';
    $html .= '<p class="muted-3-color em09 mb10">可多选，前台按预设顺序显示</p>';
    $html .= '<div class="xiazhi-qz-field-image-select">';
    $selected_imgs = xiazhi_qz_get_selected_prefix_keys($img);
    foreach ($prefix_images as $key => $url) {
        $is_selected = in_array($key, $selected_imgs) ? 'xiazhi-qz-active' : '';
        $html .= '<div class="xiazhi-qz-image"><figure class="' . esc_attr($is_selected) . '" data-prefix-value="' . esc_attr($key) . '"><img src="' . esc_url($url) . '" alt="' . esc_attr($key) . '"></figure></div>';
    }
    $html .= '</div>';
    $html .= '<input type="hidden" name="img" value="' . esc_attr(is_array($img) ? implode(',', $img) : $img) . '">';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '</div>';

    return $html;
}

function xiazhi_qz_save_frontend_title_prefix($post)
{
    if (!xiazhi_qz_current_user_can_frontend_prefix()) {
        return;
    }

    $post_id = is_object($post) && !empty($post->ID) ? (int) $post->ID : (int) $post;
    if (!$post_id) {
        return;
    }

    if (isset($_REQUEST['titles_moshi'])) {
        update_post_meta($post_id, 'titles_moshi', sanitize_text_field($_REQUEST['titles_moshi']));
    }

    if (isset($_REQUEST['text'])) {
        update_post_meta($post_id, 'text', sanitize_text_field($_REQUEST['text']));
    }

    if (isset($_REQUEST['text_bg_color'])) {
        update_post_meta($post_id, 'text_bg_color', sanitize_text_field($_REQUEST['text_bg_color']));
    }

    if (isset($_REQUEST['img'])) {
        $img_value = $_REQUEST['img'];
        if (is_array($img_value)) {
            $img_value = array_map('sanitize_key', $img_value);
        } elseif (is_string($img_value)) {
            $img_value = array_filter(array_map('trim', explode(',', $img_value)));
        }
        update_post_meta($post_id, 'img', $img_value);
    }
}
add_action('new_add_posts', 'xiazhi_qz_save_frontend_title_prefix');
add_action('new_edit_posts', 'xiazhi_qz_save_frontend_title_prefix');
add_action('wp_after_insert_post', 'xiazhi_qz_save_frontend_title_prefix');
