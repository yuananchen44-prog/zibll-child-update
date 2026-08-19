<?php
/**
 * Plugin Name:  子比表情包管理
 * Description:  管理 Zibll 评论自定义表情分组，支持后台可视化上传
 * Version:      1.1.0
 * Plugin URI:   https://www.scbkw.com/
 * Author:       苏晨
 * Author URI:   https://www.scbkw.com/
 * Requires PHP: 7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ZIBLL_ADDITIONAL_DEMO01_VERSION', '1.1.0');
define('ZIBLL_ADDITIONAL_DEMO01_PATH', plugin_dir_path(__FILE__));
// 整合进子主题：资源 URL 改用主题内路径（plugin_dir_url 在主题中会算错到 /wp-content/plugins/）
define('ZIBLL_ADDITIONAL_DEMO01_URL', get_stylesheet_directory_uri() . '/includes/zibll-comment-emoji/');
define('ZIBLL_COMMENT_EMOJI_OPTIONS_KEY', 'zibll_comment_emoji_options');
define('ZIBLL_COMMENT_EMOJI_ORDER_KEY', 'zibll_comment_emoji_sort_order');

$zibll_comment_emoji_required_files = array(
    ZIBLL_ADDITIONAL_DEMO01_PATH . 'inc/functions.php',
    ZIBLL_ADDITIONAL_DEMO01_PATH . 'inc/options.php',
);

foreach ($zibll_comment_emoji_required_files as $zibll_comment_emoji_required_file) {
    if (!file_exists($zibll_comment_emoji_required_file)) {
        error_log('[zibll-comment-emoji] Missing required file: ' . $zibll_comment_emoji_required_file);
        add_action('admin_notices', function () use ($zibll_comment_emoji_required_file) {
            echo '<div class="notice notice-error is-dismissible"><p>zibll-comment-emoji missing file: ' . esc_html($zibll_comment_emoji_required_file) . '</p></div>';
        });
        return;
    }
    require_once $zibll_comment_emoji_required_file;
}

function zibll_additional_demo01_register_activation_hook()
{
    $options = get_option(ZIBLL_COMMENT_EMOJI_OPTIONS_KEY);
    if (!is_array($options)) {
        add_option(ZIBLL_COMMENT_EMOJI_OPTIONS_KEY, zibll_additional_demo01_default_settings(), '', 'yes');
    }
}
register_activation_hook(__FILE__, 'zibll_additional_demo01_register_activation_hook');

function zibll_additional_demo01_enqueue_scripts()
{
    if (!zibll_additional_demo01_is_enabled()) {
        return;
    }

    $emoji_data = zibll_additional_demo01_get_emoji_data();
    if (empty($emoji_data['groups'])) {
        return;
    }

    $ver = ZIBLL_ADDITIONAL_DEMO01_VERSION;

    wp_enqueue_style(
        'zibll_additional_demo01_front',
        ZIBLL_ADDITIONAL_DEMO01_URL . 'assets/css/main.css',
        array(),
        $ver
    );

    wp_enqueue_script(
        'zibll_additional_demo01_front',
        ZIBLL_ADDITIONAL_DEMO01_URL . 'assets/js/main.js',
        array('jquery'),
        $ver,
        true
    );

    wp_localize_script('zibll_additional_demo01_front', 'ZibllDemoEmoji', array(
        'groups'            => $emoji_data['groups'],
        'includeDefault'    => (bool) zibll_additional_demo01_get_setting('include_default_group', 1),
        'defaultLabel'      => (string) zibll_additional_demo01_get_setting('default_group_label', '默认'),
        'defaultSmiliesUrl' => trailingslashit(get_template_directory_uri()) . 'img/smilies',
        'panelWidth'        => (int) zibll_additional_demo01_get_setting('panel_width', 360),
    ));
}
add_action('wp_enqueue_scripts', 'zibll_additional_demo01_enqueue_scripts', 99);

function zibll_additional_demo01_admin_enqueue_scripts()
{
    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    if ($page !== ZIBLL_COMMENT_EMOJI_OPTIONS_KEY) {
        return;
    }

    $ver = ZIBLL_ADDITIONAL_DEMO01_VERSION;

    wp_enqueue_style(
        'zibll_additional_demo01_admin',
        ZIBLL_ADDITIONAL_DEMO01_URL . 'assets/css/admin.css',
        array(),
        $ver
    );

    wp_enqueue_script(
        'zibll_additional_demo01_admin',
        ZIBLL_ADDITIONAL_DEMO01_URL . 'assets/js/admin.js',
        array('jquery', 'jquery-ui-sortable'),
        $ver,
        true
    );

    $server_file_limit = (int) ini_get('max_file_uploads');
    $batch_file_limit = $server_file_limit > 0 ? min(10, $server_file_limit) : 10;
    $max_file_bytes = (int) wp_max_upload_size();
    $batch_byte_limit = $max_file_bytes > 0
        ? min(8 * MB_IN_BYTES, max(256 * KB_IN_BYTES, (int) floor($max_file_bytes * 0.8)))
        : 8 * MB_IN_BYTES;

    $emoji_data = zibll_additional_demo01_get_emoji_data();

    wp_localize_script('zibll_additional_demo01_admin', 'ZibllEmojiAdmin', array(
        'ajaxUrl'        => admin_url('admin-ajax.php'),
        'action'         => 'zibll_comment_emoji_admin_upload',
        'nonce'          => wp_create_nonce('zibll_comment_emoji_admin_upload'),
        'sortAction'     => 'zibll_comment_emoji_save_order',
        'sortNonce'      => wp_create_nonce('zibll_comment_emoji_save_order'),
        'sortGroups'     => isset($emoji_data['groups']) ? $emoji_data['groups'] : array(),
        'batchFileLimit' => $batch_file_limit,
        'batchByteLimit' => $batch_byte_limit,
        'maxFileBytes'   => $max_file_bytes,
    ));
}
add_action('admin_enqueue_scripts', 'zibll_additional_demo01_admin_enqueue_scripts');
