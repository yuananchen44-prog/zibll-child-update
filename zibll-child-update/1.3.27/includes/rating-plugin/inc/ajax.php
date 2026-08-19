<?php
/**
 * AJAX 处理：评分提交
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_zrp_submit_rating', 'zrp_ajax_submit_rating');

function zrp_ajax_submit_rating()
{
    // 验证 nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'zrp_rating_nonce')) {
        zib_send_json_error(array('msg' => __('安全验证失败，请刷新页面重试', 'zibll-rating-plugin')));
    }

    // 仅登录用户
    if (!is_user_logged_in()) {
        zib_send_json_error(array('msg' => __('请先登录后再评分', 'zibll-rating-plugin')));
    }

    // 验证并清理参数
    $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
    $rating  = isset($_POST['rating']) ? absint(wp_unslash($_POST['rating'])) : 0;

    if ($post_id <= 0 || !get_post($post_id)) {
        zib_send_json_error(array('msg' => __('无效的文章', 'zibll-rating-plugin')));
    }

    if ($rating < 1 || $rating > 5) {
        zib_send_json_error(array('msg' => __('评分值必须在 1-5 之间', 'zibll-rating-plugin')));
    }

    $user_id = get_current_user_id();

    // 检查是否已评分
    $existing = zrp_get_user_rating($post_id, $user_id);
    if ($existing && !zrp_get_option('rating_allow_update', false)) {
        zib_send_json_error(array('msg' => __('您已经评过分了', 'zibll-rating-plugin')));
    }

    // 存储用户评分
    update_user_meta($user_id, 'zrp_user_rating_' . $post_id, $rating);

    // 重新计算平均分
    zrp_calculate_average($post_id);

    // 获取最新评分数据
    $rating_data = zrp_get_post_rating($post_id);

    zib_send_json_success(array(
        'msg'         => __('评分成功，感谢您的评价！', 'zibll-rating-plugin'),
        'avg'         => number_format($rating_data['avg'], 1),
        'count'       => $rating_data['count'],
        'user_rating' => $rating,
    ));
}
