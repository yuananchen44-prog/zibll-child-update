<?php
/**
 * 子主题「整合插件」全量开关
 *
 * 提供 zibll_child_module_enabled($key) —— 供各插件 loader 在 require 主文件之前做早退判断。
 * 关闭的插件：连内置副本主文件都不加载（零钩子、零脚本、零 option 查询），真正省开销。
 *
 * 为什么独立成文件、且注册在 index.php 中三个插件 loader 之前？
 *   - 三个插件 loader 早于 includes/functions/functions.php 加载，
 *     而 functions.php 里的 zibll_child_option_enabled() 彼时尚未定义，loader 调不到；
 *   - 本文件只依赖 WP 核心 get_option()，无任何前置依赖，可安全前置加载。
 *
 * 存储：独立 option key `zibll_child_modules`（与 CSF 面板用的 zibll_child_options 隔离，避免被覆盖）；字段名即用完整 key（如 module_xiazhi_qz / enable_qq_avatar）。
 * 默认全开（option 未设置该 key 时返回 true），与 zibll_child_option_enabled() 语义一致：
 *   仅当值为 false / 0 / '0'（用户主动关闭）才视作关闭。
 *
 * @package zibll-child
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('zibll_child_module_enabled')) {
    function zibll_child_module_enabled($key, $default = true)
    {
        // 静态缓存：整个请求只 get_option 一次，各 loader 共用。
        // ⚠️ 关键修复（v1.2.35）：开关独立存于 zibll_child_modules，
        //    不再与 CSF 面板共用的 zibll_child_options 混用——
        //    否则后台保存 CSF 面板时会用其托管字段覆盖整个 option，
        //    把这里的开关值清掉，导致「关了又自动恢复成开启」。
        static $cache = null;
        if ($cache === null) {
            $cache = (array) get_option('zibll_child_modules', array());
        }

        if (!array_key_exists($key, $cache)) {
            return (bool) $default; // 未设置 → 默认开启
        }

        $v = $cache[$key];
        // '0' 在 PHP 里为真，必须显式判定关闭条件。
        return !($v === false || $v === 0 || $v === '0');
    }
}
