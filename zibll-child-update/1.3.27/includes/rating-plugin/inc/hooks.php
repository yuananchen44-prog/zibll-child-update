<?php
/**
 * 前端钩子挂载
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 文章详情页展示评分
 * 挂载到正文内容和标签之间（Zibll 主题钩子）
 */
add_action('zib_article_content_after', 'zrp_output_rating_box', 10);
