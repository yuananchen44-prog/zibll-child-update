<?php
/**
 * 子比评论表情包(zibll-comment-emoji)「一体化」加载器
 *
 * 整合自独立插件（作者：苏晨），复制进 includes/zibll-comment-emoji/。
 * 条件加载：若独立插件已激活（主函数已定义），跳过重载避免重定义 fatal；
 *           否则 require 内置副本，并补正主题环境下的初始化时机。
 *
 * 资源 URL 已在主文件内改为 get_stylesheet_directory_uri() 反推
 * （插件原用 plugin_dir_url，在主题内会算错到 /wp-content/plugins/）。
 */

if (!defined('ABSPATH')) {
    exit;
}

// 主函数守卫：独立插件已激活则不重载内置副本
// 模块全量开关（须早于 function_exists 守卫）：关闭则不加载内置副本，零钩子零开销。
if (!zibll_child_module_enabled('module_comment_emoji')) {
    return;
}

if (!function_exists('zibll_additional_demo01_register_activation_hook')) {

    $zibll_comment_emoji_main = get_stylesheet_directory() . '/includes/zibll-comment-emoji/zibll-comment-emoji.php';
    if (file_exists($zibll_comment_emoji_main)) {
        require_once $zibll_comment_emoji_main;
    }

    // 主题内 register_activation_hook 不会触发（仅插件激活时触发），手动初始化默认 option
    if (function_exists('zibll_additional_demo01_register_activation_hook')) {
        zibll_additional_demo01_register_activation_hook();
    }

    // 防御：若本加载器在 zib_require_end 之后才被调用（极端情况），
    // options.php 注册的 add_action 已错过触发，手动补正 CSF 后台面板注册
    if (did_action('zib_require_end') && function_exists('zibll_additional_demo01_admin_csf_options')) {
        zibll_additional_demo01_admin_csf_options();
    }
}
