<?php
/**
 * Plugin Name:  夏稚自定义文章前缀
 * Description:  为文章标题添加自定义前缀，支持图片模式和文字模式（兼容 zibll 父主题与子主题）
 * Version:      1.1.1
 * Plugin URI:   https://www.ksxjy.com/
 * Author:       子比主题专用
 * Author URI:   https://www.ksxjy.com/
 * Requires PHP: 7.4 - 8.4
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 主题依赖检测（兼容父主题与子主题）
 * ----------------------------------------------------------------
 * 必须用 get_template() 而不是 get_stylesheet()：
 *   - get_template()  始终返回【父主题目录名】，父主题与子主题下都是 'zibll'；
 *   - get_stylesheet() 在子主题下返回子主题 slug（如 'zibll-child'），
 *     会让依赖 zibll 的插件误报「未启用 zibll」，从而整插件被 return 掐死。
 * 因此判定条件用 get_template() !== 'zibll' 即可在父/子主题下都正常工作。
 */
function xiazhi_qz_error_notices()
{
    echo '<div class="notice notice-error is-dismissible">'
        . '<h3>插件错误！</h3>'
        . '<p>此插件依赖于zibll主题，请先启用zibll子比主题</p></div>';
}

if (get_template() !== 'zibll') {
    add_action('admin_notices', 'xiazhi_qz_error_notices');
    return;
}

// ── 位置无关的资源根 URL（兼容「独立插件」与「子主题内置副本」两种部署位置）──
// 进主题目录后 plugin_dir_url() 会算出错误地址（/wp-content/plugins/...）导致 CSS/JS/图片 404，
// 故改用 WP_CONTENT_DIR 反推相对路径，对两种位置都成立。
$xz_dir = wp_normalize_path(dirname(__FILE__));
$xz_rel = str_replace(wp_normalize_path(WP_CONTENT_DIR), '', $xz_dir);
define('XIAZHI_QZ_PATH', trailingslashit($xz_dir));
define('XIAZHI_QZ_URL', content_url(trailingslashit($xz_rel)));

// 主题检测通过后才加载功能模块，避免在非 zibll 环境下调用 CSF 等父主题 API 报错
require_once dirname(__FILE__) . '/inc/options.php';
require_once dirname(__FILE__) . '/inc/functions.php';
require_once dirname(__FILE__) . '/inc/widget.php';

// 前台样式与脚本
function xiazhi_qz_enqueue_scripts()
{
    $ver = '1.1.1';
    wp_enqueue_style('xiazhi_qz_style', XIAZHI_QZ_URL . 'assets/css/main.min.css', array(), $ver);
    wp_enqueue_script('xiazhi_qz_script', XIAZHI_QZ_URL . 'assets/js/main.min.js', array('jquery'), $ver, true);
}
add_action('wp_enqueue_scripts', 'xiazhi_qz_enqueue_scripts');
