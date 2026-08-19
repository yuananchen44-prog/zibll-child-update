<?php
/**
 * Plugin Name: 子比主题 - 文章评分插件
 * Description: 拓展插件，为 Zibll 子比主题增加文章星级评分功能，支持评分排行小工具和排序集成。
 * Version: 1.1.0
 * Author: 子阿卿
 * Author QQ: 1822178298
 * Requires PHP: 7.0
 * Text Domain: zibll-rating-plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

// ========== 常量定义 ==========
// 位置无关地推导插件 URL / 路径：
// 既可作为独立插件运行（位于 /wp-content/plugins/...），
// 也可作为子主题内置副本运行（位于 /wp-content/themes/zibll-child/includes/rating-plugin/）。
// 用 WP_CONTENT_DIR 反推，避免 plugin_dir_url(__FILE__) 在主题目录下算出
// 「.../wp-content/plugins/themes/...」这类错误地址，导致前端 CSS/JS 404。
$zrp_dir = wp_normalize_path( dirname( __FILE__ ) );
$zrp_rel = str_replace( wp_normalize_path( WP_CONTENT_DIR ), '', $zrp_dir );
define('ZRP_PLUGIN_PATH', trailingslashit( $zrp_dir ) );
define('ZRP_PLUGIN_URL', content_url( trailingslashit( $zrp_rel ) ) );
define('ZRP_PLUGIN_VERSION', '1.1.0');
define('ZRP_OPTIONS_KEY', 'zrp_rating_options');

// ========== 主题依赖检测 ==========
function zrp_is_zibll_theme()
{
    return get_template() === 'zibll' || get_stylesheet() === 'zibll';
}

if (!zrp_is_zibll_theme()) {
    add_action('admin_notices', 'zrp_theme_notice');
    return;
}

function zrp_theme_notice()
{
    echo '<div class="notice notice-error"><p><strong>子比主题 - 文章评分插件</strong> 需要启用 <strong>Zibll 子比主题</strong> 或子比子主题后才能正常使用。</p></div>';
}

// ========== 选项读取函数 ==========
if (!function_exists('zrp_get_option')) {
    function zrp_get_option($key = '', $default = false)
    {
        static $options = null;
        if (null === $options) {
            $options = get_option(ZRP_OPTIONS_KEY, array());
        }
        if (!$key) {
            return $options;
        }
        return isset($options[$key]) ? $options[$key] : $default;
    }
}

// ========== 提前加载 options/widget（仅定义函数和注册 WordPress 核心钩子，不调用主题 API） ==========
// 必须在 after_setup_theme 之前加载，以便 add_action('after_setup_theme', ...) 能正确注册
foreach (array('inc/options.php', 'inc/widget.php') as $file) {
    $path = ZRP_PLUGIN_PATH . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

// ========== CSF 后台选项 + 小工具 — 挂 after_setup_theme（Zibll 附加插件标准挂载点） ==========
add_action('after_setup_theme', 'zrp_admin_csf_options');
// zrp_widget_create 的钩子已在 widget.php 中注册

// ========== 插件业务初始化（挂 zib_require_end，用于前台资源等非 CSF 逻辑） ==========
add_action('zib_require_end', 'zrp_plugin_init');

function zrp_plugin_init()
{
    $require_once = array(
        'inc/functions.php',
        'inc/ajax.php',
        'inc/hooks.php',
    );

    foreach ($require_once as $file) {
        $path = ZRP_PLUGIN_PATH . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }

    // 前台资源（小工具可能在任何页面，不做页面限制）
    add_action('wp_enqueue_scripts', 'zrp_enqueue_front_assets');
}

// ========== 前台资源加载 ==========
function zrp_enqueue_front_assets()
{
    wp_enqueue_style(
        'zrp-rating-style',
        ZRP_PLUGIN_URL . 'assets/css/rating.css',
        array(),
        ZRP_PLUGIN_VERSION
    );

    wp_enqueue_script(
        'zrp-rating-script',
        ZRP_PLUGIN_URL . 'assets/js/rating.js',
        array('jquery'),
        ZRP_PLUGIN_VERSION,
        true
    );

    wp_localize_script('zrp-rating-script', 'zrp_rating', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('zrp_rating_nonce'),
    ));
}

// ========== 激活/停用钩子 ==========
register_activation_hook(__FILE__, 'zrp_plugin_activation');
register_deactivation_hook(__FILE__, 'zrp_plugin_deactivation');

function zrp_plugin_activation()
{
    if (!zrp_is_zibll_theme()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('子比主题 - 文章评分插件需要 Zibll 子比主题才能正常工作，请先安装并激活子比主题。', 'zibll-rating-plugin'),
            esc_html__('插件激活失败', 'zibll-rating-plugin'),
            array('back_link' => true)
        );
    }
}

function zrp_plugin_deactivation()
{
    // 清理工作可在此添加
}