<?php
/**
 * 子比子主题 - 卸载数据清理
 *
 * 需求：当用户切换离开（或后续删除）本子主题时，可以选择「保留」或「清除」本子主题整合插件的全部自有数据。
 *
 * 机制说明（关键坑）：WordPress 不允许直接删除「正在启用」的主题，必须先切换到其它主题。
 * 而切换那一刻，被切走的本子主题仍是启用态、其 PHP 仍在运行 —— 因此可在 switch_theme 钩子里
 * 可靠地执行清理，完美绕开「删除非启用主题时其代码不加载、自身钩子不触发」的固有限制。
 *
 * 交互：后台「外观 → 主题」点击其它主题的「启用」时，由 assets/js/theme-switch-cleanup.js 弹出询问框；
 * 选「清除数据并切换」则通过 cookie 标记，在切换同时删除本子主题整合插件的全部自有数据。
 *
 * 清理范围严格限定为本子主题整合插件的自有数据，绝不动父主题 zibll 与其它插件的任何数据。
 *
 * @package zibll-child
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 清除本子主题整合插件的全部自有数据。
 * 仅删除下方明确列出的键，不触碰其它任何数据。
 */
function zibll_child_cleanup_integrated_data()
{
    global $wpdb;

    // 先读取评论表情可能自定义过的存储根目录（option 删除后就读不到了）
    $ce_opts = get_option('zibll_comment_emoji_options', array());
    $ce_root = (is_array($ce_opts) && !empty($ce_opts['smilies_root'])) ? $ce_opts['smilies_root'] : '';

    // 1) 选项（option）
    $options = array(
        'zrp_rating_options',              // 文章评分插件设置
        'xiazhi_qz_options',               // 夏稚文章前缀插件设置
        'zibll_comment_emoji_options',     // 评论表情包插件设置
        'zibll_comment_emoji_sort_order',  // 评论表情包排序
        'zibll_child_modules',             // 模块全量开关
        'zibll_child_options',             // 子主题 CSF 设置
        'zibll_child_qq_avatar_done',      // 头像同步一次性标记
        'zibll_child_version',             // 版本号（增量更新器用）
        'zibll_child_bak_cleanup_version', // 更新器 .bak 清扫标记
        'wpmcs_global_settings',           // 已废弃媒体云接管孤儿 option
        'wpmcs_enabled',                   // 已废弃媒体云接管孤儿 option
    );
    foreach ($options as $opt) {
        delete_option($opt);
    }

    if (!empty($wpdb)) {
        // 2) 文章元（postmeta）
        $pm_keys = array(
            'titles_moshi',            // 夏稚前缀
            'text',                    // 夏稚前缀
            'text_bg_color',           // 夏稚前缀
            'img',                     // 夏稚前缀
            'custom_img_prefixes',     // 夏稚前缀
            'zrp_rating_count',        // 文章评分人数
            // 注：评分插件还写过一个无前缀的 'score' 文章元，因过于通用，此处不批量删，避免误伤其它插件。
        );
        foreach ($pm_keys as $k) {
            $wpdb->delete($wpdb->postmeta, array('meta_key' => $k), array('%s'));
        }

        // 3) 用户元（usermeta）：评分按文章 ID 动态生成 zrp_user_rating_{post_id}
        //    用转义后的 LIKE 精确匹配前缀，避免误删。
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'zrp_user_rating\\_%'");
    }

    // 4) 上传文件：评论表情（默认回退路径 uploads/zibll-comment-emoji + 可能的自定义 smilies_root）
    $uploads = wp_upload_dir();
    $dirs    = array();
    if (!empty($uploads['basedir'])) {
        $dirs[] = $uploads['basedir'] . '/zibll-comment-emoji';
    }
    if ($ce_root && is_dir($ce_root)) {
        $dirs[] = $ce_root;
    }
    foreach ($dirs as $d) {
        zibll_child_rrmdir($d);
    }
}

/**
 * 递归删除目录（含其内容），用于清理评论表情的上传目录。
 * 仅删除给定目录本身，不向上影响其它路径。
 */
function zibll_child_rrmdir($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            zibll_child_rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

/**
 * 切换主题钩子：若离开的是本子主题且用户选择清除，则执行清理。
 *
 * @param string    $new_name  新主题名
 * @param WP_Theme  $new_theme 新主题对象
 * @param WP_Theme  $old_theme 旧（被切走）主题对象
 */
function zibll_child_maybe_cleanup_on_switch($new_name, $new_theme, $old_theme)
{
    if (!($old_theme instanceof WP_Theme)) {
        return;
    }
    // 仅当被切走的是本子主题才处理
    if ($old_theme->get_stylesheet() !== 'zibll-child') {
        return;
    }

    $do_cleanup = !empty($_COOKIE['zibll_child_cleanup_data']) && $_COOKIE['zibll_child_cleanup_data'] === '1';
    if ($do_cleanup) {
        zibll_child_cleanup_integrated_data();
    }

    // 无论是否清理，都清掉 cookie，避免影响后续切换
    if (!headers_sent()) {
        $path   = defined('COOKIEPATH') ? COOKIEPATH : '/';
        $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
        setcookie('zibll_child_cleanup_data', '', time() - 3600, $path, $domain, false, true);
    }
}
add_action('switch_theme', 'zibll_child_maybe_cleanup_on_switch', 10, 3);

/**
 * 后台主题列表页：挂载切换确认弹窗 JS（仅当本子主题为当前启用主题时）。
 */
function zibll_child_enqueue_switch_modal($hook)
{
    if (!is_admin()) {
        return;
    }
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || !in_array($screen->id, array('themes', 'themes-network'), true)) {
        return;
    }
    // 仅当本子主题为当前启用主题时才需要弹窗
    if (function_exists('get_stylesheet') && get_stylesheet() !== 'zibll-child') {
        return;
    }

    $js_url = get_stylesheet_directory_uri() . '/assets/js/theme-switch-cleanup.js';
    $ver    = function_exists('wp_get_theme') ? wp_get_theme('zibll-child')->get('Version') : '1.0.0';
    wp_enqueue_script('zibll-child-switch-cleanup', $js_url, array('jquery'), $ver, true);

    wp_localize_script('zibll-child-switch-cleanup', 'ZIBLL_CHILD_SWITCH', array(
        'themeName' => function_exists('wp_get_theme') ? wp_get_theme('zibll-child')->get('Name') : '子比子主题',
    ));
}
add_action('admin_enqueue_scripts', 'zibll_child_enqueue_switch_modal');
