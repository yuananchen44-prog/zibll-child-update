<?php
/**
 * 子比子主题 - 文章评分插件「一体化」加载器
 * ─────────────────────────────────────────────────────────────
 * 目的：把「子阿卿」的《子比主题 - 文章评分插件》(zibll-rating-plugin)
 *       作为【子主题内置模块】打包进来，实现子主题自包含、随子主题一起部署/更新。
 *
 * ── 与服务器已有独立插件的数据一致性（核心诉求）─────────────────
 *   该插件的所有数据均存于 WordPress 标准存储、且 key 固定，没有任何自建数据表：
 *     - 选项：       get_option('zrp_rating_options')              （CSF 设置面板）
 *     - 文章平均分： post_meta 'score'
 *     - 评分人数：   post_meta 'zrp_rating_count'
 *     - 用户评分：   user_meta 'zrp_user_rating_{post_id}'
 *   因此无论代码来自「独立插件」还是「子主题内置副本」，
 *   只要共用同一套函数名 + 同一套存储 key，数据天然互通、零迁移。
 *
 * ── 零冲突加载策略（避免 cannot redeclare fatal）──────────────
 *   服务器若已启用【同名独立插件】，其主文件会先于子主题 functions.php 被 WP 加载，
 *   此时 zrp_is_zibll_theme() 等函数已定义。本加载器检测到后【不再】加载内置副本，
 *   → 由独立插件继续服务（含已有数据），子主题内置副本保持休眠、不重复定义。
 *   若服务器未启用独立插件（全新安装 / 已停用独立插件），则加载内置副本，
 *   复用同一套存储 key，无缝接管已有数据。
 *
 *   结论：部署子主题后，无论独立插件是否启用，评分数据始终一致、界面不重复、不报错；
 *         停用独立插件后，内置副本自动接管，无需任何数据迁移。
 *
 * @package zibll-child
 */

if (!defined('ABSPATH')) {
    exit;
}

// 检测独立插件是否在 active_plugins 中（用于后台状态展示；兼容 multisite）
// 模块全量开关（须早于 function_exists 守卫）：关闭则不加载内置副本，零钩子零开销。
if (!zibll_child_module_enabled('module_rating')) {
    return;
}

if (!function_exists('zibll_child_rating_standalone_active')) {
    function zibll_child_rating_standalone_active()
    {
        if (!function_exists('get_option')) {
            return false;
        }
        $entry = 'zibll-rating-plugin/zibll-rating-plugin.php';
        $active = get_option('active_plugins', array());
        if (is_array($active) && in_array($entry, $active, true)) {
            return true;
        }
        if (is_multisite()) {
            $sitewide = get_option('active_sitewide_plugins', array());
            if (is_array($sitewide) && isset($sitewide[$entry])) {
                return true;
            }
        }
        return false;
    }
}

// ── 关键守卫：若同名函数已由独立插件定义（即独立插件已加载），直接跳过重载，杜绝重定义 fatal ──
if (function_exists('zrp_is_zibll_theme')) {
    return; // 独立插件已激活并加载，交由它服务（含历史数据），内置副本休眠
}

// 否则加载子主题内置副本（路径随本文件位置自动推导，无需硬编码）
$bundled_main = __DIR__ . '/rating-plugin/zibll-rating-plugin.php';
if (file_exists($bundled_main)) {
    require_once $bundled_main;

    // ── 时机补正（关键修复）─────────────────────────────────
    // 父主题在子主题 functions.php「require 父 inc/inc.php」时（第 43 行）即已触发
    // do_action('zib_require_end')（见 zibll/inc/inc.php:126）；而本加载器在之后（第 46 行）
    // 才 require 内置副本，此时主文件注册的 add_action('zib_require_end','zrp_plugin_init')
    // 已错过触发时机 → 钩子永不执行 → inc/functions.php、inc/ajax.php、inc/hooks.php 永不加载，
    // 前台评分框 / 小工具 / AJAX 全部失效。
    // 因此若 zib_require_end 已触发，则在此手动调用 zrp_plugin_init() 完成业务初始化；
    // 若尚未触发（极少数顺序变动），则交由主文件注册的钩子正常执行，不会重复初始化。
    if (function_exists('zrp_plugin_init') && did_action('zib_require_end')) {
        zrp_plugin_init();
    }
}
