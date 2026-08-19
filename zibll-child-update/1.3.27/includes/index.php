<?php
/**
 * 子比子主题 - 模块加载入口
 *
 * 使用父主题提供的 zib_require() 批量加载子主题模块文件
 * 父主题 inc/inc.php 已在此之前加载完毕，所有父主题函数均可安全调用
 *
 * @package zibll-child
 */

if (!defined('ABSPATH')) {
    exit;
}

// 使用父主题的 zib_require() 加载子主题模块（保持与父主题一致的加载风格）
// 顺序要点：theme-updater 必须在 theme-options 之前加载，
// 因为 theme-options 注册面板时会调用 theme-updater 的 zibll_child_get_version() 等函数渲染「主题更新」区。
// rating-plugin-loader 需靠前加载：它会在 WP 插件加载之后、父主题 zib_require_end 触发之前，
// 视情况 require 内置评分插件主文件（独立插件已激活则跳过重载，避免重定义 fatal）。
zib_require(array(
    'includes/functions/modules-switch', // 模块全量开关：提供 zibll_child_module_enabled()，须早于各插件 loader 以完成早退判断
    'includes/functions/theme-uninstall', // 卸载数据清理：切换/删除主题时询问并清除本子主题整合插件数据（挂 switch_theme + 后台弹窗 JS）
    'includes/xiazhi-qz-loader',        // 夏稚文章前缀插件「一体化」加载器（条件加载，数据存 post_meta，修复 ajax 懒加载列表前缀不显示）
    'includes/rating-plugin-loader',   // 文章评分插件「一体化」加载器（条件加载，数据兼容独立插件）
    'includes/zibll-comment-emoji-loader', // 评论表情包插件「一体化」加载器（整合 zibll-comment-emoji，替换原月薪喵表情包）
    'includes/functions/functions',
    'includes/functions/theme-updater', // 在线增量更新器（后台提示 + 增量下载覆盖核心文件）
    'includes/functions/theme-options', // 子主题独立设置页（CSF）+ zibll_child_get_option 读取封装（依赖 theme-updater 函数）
), true);
