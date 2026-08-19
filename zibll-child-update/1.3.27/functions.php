<?php
/**
 * 子比子主题 - 核心入口
 *
 * 加载顺序（必须严格遵循，否则父主题函数/常量将不可用）：
 *   1. 临时修正 wp_get_theme() 的取值，使父主题 inc/inc.php 取到「父主题」版本号
 *   2. 加载父主题 inc/inc.php（定义全部常量、函数、类、模块）
 *   3. 加载子主题自己的模块入口（includes/index.php）
 *   4. 最后加载站点级自定义代码 func.php（增量更新只覆盖清单内核心文件，func.php 受保护不覆盖）
 *
 * 关于 THEME_VERSION 修正（为何需要）：
 *   父主题 inc/inc.php 中通过 wp_get_theme()->Version 定义 THEME_VERSION。
 *   在子主题环境下，wp_get_theme() 默认返回【子主题】数据，导致 THEME_VERSION
 *   被误设为子主题版本（1.0.0）而非父主题真实版本（如 8.9），进而使父主题所有
 *   以 THEME_VERSION 作为缓存戳的资源（CSS/JS）版本号错乱。
 *
 *   解决方式：在 require 父主题核心【之前】，临时挂一个 pre_option_stylesheet
 *   过滤器，让 wp_get_theme() 在版本检测那一刻取到父主题（zibll）。该过滤器
 *   在首次命中后立即自我移除，之后所有请求仍正常使用子主题身份，互不干扰。
 *   此做法不修改任何常量定义、不劫持主题识别，是最低侵入的兼容方案。
 *
 * @package zibll-child
 */

// ① 临时让 wp_get_theme() 在「父主题核心加载那一次」返回父主题，
//    用于修正 THEME_VERSION（否则会被误设为子主题版本号）。
//    【重要修正 v1.2.22】：原实现用 get_stylesheet_directory() 做前置判断，
//    会提前触发并消耗过滤器的「首次命中」，导致修正时机错乱，
//    且在编辑器（古腾堡）请求中把主题身份污染为父主题，
//    进而使子主题应挂载的编辑器节点为 null → 触发 React createRoot(null) 报错。
//    现改为：无条件注册过滤器，并用 get_template_directory() 定位父核心
//    （其读取的是 option(template) 而非 option(stylesheet)，不触发本过滤器），
//    使「首次命中」精确落在 inc/inc.php 内的 wp_get_theme() 调用上，
//    修正 THEME_VERSION 后过滤器即自行移除，后续请求主题身份完全正常。
add_filter('pre_option_stylesheet', 'zibll_child_force_parent_for_version', 0);
function zibll_child_force_parent_for_version($pre)
{
    remove_filter('pre_option_stylesheet', __FUNCTION__, 0);
    return 'zibll';
}

// ─── 1. 加载父主题核心（必须最先）─────────────────────────────
// 用 get_template_directory() 定位父主题目录，避免触碰 stylesheet 过滤器
$tmpl = get_template_directory();
require_once $tmpl . '/inc/inc.php';

// ─── 2. 加载子主题自己的模块入口 ──────────────────────────────
// 此时过滤器已移除，get_stylesheet_directory() 正确返回子主题
$child = get_stylesheet_directory();
require_once $child . '/includes/index.php';

// ─── 3. 加载站点级临时自定义代码（与父主题 func.php 机制一致）───
// func.php 在增量更新中受保护、不会被覆盖；仅用于少量站点级临时代码；
// 长期功能请放入 includes/ 目录模块化（见 includes/functions/functions.php）。
if (file_exists($child . '/func.php')) {
    require_once $child . '/func.php';
}
