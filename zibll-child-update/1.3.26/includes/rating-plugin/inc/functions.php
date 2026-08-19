<?php
/**
 * 核心业务函数
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 获取文章评分数
 * @return array ['avg' => float, 'count' => int]
 */
function zrp_get_post_rating($post_id)
{
    $post_id = (int) $post_id;
    if (!$post_id) {
        return array('avg' => 0.0, 'count' => 0);
    }

    $score = get_post_meta($post_id, 'score', true);
    $score = floatval($score);
    $count = (int) get_post_meta($post_id, 'zrp_rating_count', true);

    // 保留一位小数
    $avg = round($score, 1);

    return array(
        'avg'   => $avg,
        'count' => $count,
    );
}

/**
 * 获取指定用户对文章的评分
 * @return int|false 1-5 或 false（未评分）
 */
function zrp_get_user_rating($post_id, $user_id = 0)
{
    $post_id = (int) $post_id;
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    if (!$post_id || !$user_id) {
        return false;
    }

    $rating = get_user_meta($user_id, 'zrp_user_rating_' . $post_id, true);
    if ($rating && $rating >= 1 && $rating <= 5) {
        return (int) $rating;
    }

    return false;
}

/**
 * 重新计算平均分
 */
function zrp_calculate_average($post_id)
{
    $post_id = (int) $post_id;
    if (!$post_id) {
        return;
    }

    global $wpdb;

    // 统计评分人数和总分
    $meta_key = 'zrp_user_rating_' . $post_id;
    $results  = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value > 0",
        $meta_key
    ));

    $count = count($results);
    $total = 0;
    foreach ($results as $row) {
        $total += (float) $row->meta_value;
    }

    if ($count > 0) {
        $avg = round($total / $count, 1);
        update_post_meta($post_id, 'score', (string) $avg);
        update_post_meta($post_id, 'zrp_rating_count', $count);
    } else {
        delete_post_meta($post_id, 'score');
        delete_post_meta($post_id, 'zrp_rating_count');
    }
}

/**
 * 渲染星级 HTML（仅显示，无交互）
 * @param float $rating 评分值
 * @return string HTML
 */
function zrp_render_star_display($rating)
{
    $rating = round(floatval($rating), 1);
    $html   = '<span class="zrp-stars-display" title="' . esc_attr($rating) . ' 分">';

    for ($i = 1; $i <= 5; $i++) {
        $class = 'zrp-star-display';
        if ($i <= floor($rating)) {
            $class .= ' active';
        } elseif ($i - 0.5 <= $rating) {
            $class .= ' half';
        }
        $html .= '<i class="fa ' . esc_attr($class) . '"></i>';
    }

    $html .= '</span>';
    return $html;
}

/**
 * 渲染评分交互框
 * @param int    $post_id 文章ID
 * @param string $mode    float|sidebar
 * @return string HTML
 */
function zrp_render_rating_box($post_id, $mode = 'sidebar')
{
    $post_id     = (int) $post_id;
    $rating_data = zrp_get_post_rating($post_id);
    $user_rated  = zrp_get_user_rating($post_id);

    $mode_class = 'zrp-mode-' . esc_attr($mode);

    // 构建内联样式
    $inline_style = '';
    if ($mode === 'float') {
        $float_bottom = (int) zrp_get_option('float_bottom', 80);
        $inline_style .= 'bottom:' . $float_bottom . 'px;';
    }
    $star_color = zrp_get_option('star_color', '');
    if ($star_color) {
        $inline_style .= '--zrp-star-color:' . $star_color . ';';
    }

    ob_start();
    ?>
    <div class="zrp-rating-box <?php echo $mode_class; ?> theme-box main-bg" data-post-id="<?php echo $post_id; ?>"
         <?php if ($inline_style): ?>style="<?php echo esc_attr($inline_style); ?>"<?php endif; ?>>
        <div class="zrp-rating-header flex ac jsb">
            <span class="zrp-rating-title muted-color"><?php esc_html_e('游戏评分', 'zibll-rating-plugin'); ?></span>
            <span class="zrp-rating-score-value c-yellow font-bold em14"<?php if ($rating_data['count'] <= 0) echo ' style="display:none"'; ?>>
                <?php echo number_format($rating_data['avg'], 1); ?>
            </span>
        </div>

        <div class="zrp-stars" data-post-id="<?php echo $post_id; ?>"
             data-user-rated="<?php echo $user_rated ? '1' : '0'; ?>">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span class="zrp-star<?php echo $user_rated && $i <= $user_rated ? ' active' : ''; ?>"
                      data-value="<?php echo $i; ?>"
                      title="<?php echo esc_attr($i . ' 星'); ?>">
                    <i class="fa fa-star-o"></i>
                    <i class="fa fa-star"></i>
                </span>
            <?php endfor; ?>
        </div>

        <div class="zrp-rating-info em09 muted-2-color text-center mt6">
            <?php echo sprintf(
                __('平均 %1$s 分（%2$s 人评分）', 'zibll-rating-plugin'),
                '<span class="zrp-avg-score">' . number_format($rating_data['avg'], 1) . '</span>',
                '<span class="zrp-rating-count">' . $rating_data['count'] . '</span>'
            ); ?>
        </div>

        <?php if ($user_rated): ?>
            <div class="zrp-rating-my em09 text-center muted-3-color mt3">
                <?php echo sprintf(__('我的评分：%s 星', 'zibll-rating-plugin'), $user_rated); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 输出评分框（用于钩子回调）
 */
function zrp_output_rating_box()
{
    if (!is_single() || !zrp_get_option('rating_enable', true)) {
        return;
    }

    global $post;
    if (!$post || $post->post_type !== 'post') {
        return;
    }

    $mode = zrp_get_option('rating_mode', 'sidebar');
    echo zrp_render_rating_box($post->ID, $mode);
}

/* ========== 步骤 7：排序功能集成 ========== */

/**
 * 递归遍历字段数组，为 orderby 类字段追加 score 选项
 * @param array  $fields      字段数组（引用传递）
 * @param string $score_label score 选项的显示文本
 * @param string $detect_key  options 中用于识别目标字段的特征 key
 */
function zrp_inject_score_orderby(&$fields, $score_label, $detect_key = 'modified')
{
    foreach ($fields as &$field) {
        if (isset($field['id'], $field['options']) && is_array($field['options'])) {
            $is_orderby    = ($field['id'] === 'orderby' || $field['id'] === 'lists');
            $has_detect    = isset($field['options'][$detect_key]);
            if (($is_orderby || $has_detect) && !isset($field['options']['score'])) {
                $field['options']['score'] = $score_label;
            }
        }
        // 递归子字段
        if (isset($field['fields']) && is_array($field['fields'])) {
            zrp_inject_score_orderby($field['fields'], $score_label, $detect_key);
        }
        if (isset($field['tabs']) && is_array($field['tabs'])) {
            foreach ($field['tabs'] as &$tab) {
                if (isset($tab['fields']) && is_array($tab['fields'])) {
                    zrp_inject_score_orderby($tab['fields'], $score_label, $detect_key);
                }
            }
        }
    }
}

/**
 * 为文章列表小工具添加"按评分排序"选项
 */
add_filter('csf_zib_widget_ui_main_post_args', 'zrp_widget_orderby_score', 10, 2);
add_filter('csf_zib_widget_ui_tab_post_args', 'zrp_widget_orderby_score', 10, 2);
add_filter('csf_widget_ui_oneline_posts_args', 'zrp_widget_orderby_score', 10, 2);
add_filter('csf_zib_widget_ui_term_lists_card_args', 'zrp_widget_orderby_score', 10, 2);

function zrp_widget_orderby_score($args, $widget)
{
    if (isset($args['fields']) && is_array($args['fields'])) {
        zrp_inject_score_orderby($args['fields'], __('评分高低', 'zibll-rating-plugin'), 'modified');
    }
    return $args;
}

/**
 * 为子比主题设置中的排序方式添加"评分"选项
 */
add_filter('csf_zibll_options_sections', 'zrp_admin_orderby_score', 10, 2);

function zrp_admin_orderby_score($sections, $csf)
{
    foreach ($sections as &$section) {
        if (isset($section['fields']) && is_array($section['fields'])) {
            zrp_inject_score_orderby($section['fields'], __('评分', 'zib_language'), 'modified');
        }
        if (isset($section['sections']) && is_array($section['sections'])) {
            zrp_admin_orderby_score_subsections($section['sections']);
        }
    }
    return $sections;
}

function zrp_admin_orderby_score_subsections(&$sections)
{
    foreach ($sections as &$section) {
        if (isset($section['fields']) && is_array($section['fields'])) {
            zrp_inject_score_orderby($section['fields'], __('评分', 'zib_language'), 'modified');
        }
        if (isset($section['sections']) && is_array($section['sections'])) {
            zrp_admin_orderby_score_subsections($section['sections']);
        }
    }
}
