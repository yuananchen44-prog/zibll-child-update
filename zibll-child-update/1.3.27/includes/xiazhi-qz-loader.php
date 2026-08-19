<?php
/**
 * 子比子主题 - 夏稚文章前缀插件「一体化」加载器
 * ─────────────────────────────────────────────────────────────
 * 把《夏稚自定义文章前缀》(xiazhi_qz) 作为【子主题内置模块】打包，
 * 实现子主题自包含、随子主题一起部署/更新。
 *
 * ── 与服务器已有独立插件的数据一致性 ───────────────────────
 *   插件所有数据均存于 WordPress 标准存储、且 key 固定，无自建数据表：
 *     - 文章前缀模式： post_meta 'titles_moshi'
 *     - 文字前缀：     post_meta 'text' / 'text_bg_color'
 *     - 图片前缀：     post_meta 'img' / 'custom_img_prefixes'
 *   只要共用同一套函数名 + 同一存储 key，内置副本与独立插件数据天然互通、零迁移。
 *
 * ── 零冲突加载策略（避免 cannot redeclare fatal）────────────
 *   服务器若已启用【同名独立插件】，其函数已定义。本加载器检测到后【不再】加载
 *   内置副本，交由独立插件继续服务（含已有数据），子主题内置副本保持休眠。
 *   若服务器未启用独立插件（全新安装 / 已停用独立插件），则加载内置副本接管。
 *
 * @package zibll-child
 */

if (!defined('ABSPATH')) {
    exit;
}

// ── 关键守卫：若核心函数已由独立插件定义（即独立插件已加载），直接跳过重载，杜绝重定义 fatal ──
// 模块全量开关（须早于 function_exists 守卫）：关闭则不加载内置副本，零钩子零开销。
if (!zibll_child_module_enabled('module_xiazhi_qz')) {
    return;
}

if (function_exists('xiazhi_qz_prefix_images')) {
    return; // 独立插件已激活并加载，交由它服务（含历史数据），内置副本休眠
}

// 否则加载子主题内置副本（路径随本文件位置自动推导，无需硬编码）
$bundled_main = __DIR__ . '/xiazhi-qz/xiazhi_qz.php';
if (file_exists($bundled_main)) {
    require_once $bundled_main;

    // ── 时机补正（Gotcha 1）─────────────────────────────────
    // 插件的 metabox 与 widget 创建挂在 add_action('zib_require_end', ...)。
    // 父主题在子主题 functions.php require 父 inc/inc.php 时即已触发 zib_require_end，
    // 本加载器在其后才 require 内置副本，钩子已错过、永不执行 → 编辑页前缀面板与前台投稿前缀小工具缺失。
    // 因此若 zib_require_end 已触发，在此手动调用补初始化；若尚未触发（极少数顺序变动），
    // 则交由插件注册的钩子正常执行，二者互斥不重复。
    if (did_action('zib_require_end')) {
        if (function_exists('xiazhi_qz_create_metabox')) {
            xiazhi_qz_create_metabox();
        }
        if (function_exists('xiazhi_qz_title_prefix_widget_create')) {
            xiazhi_qz_title_prefix_widget_create();
        }
    }
}
