<?php
/**
 * 评分排行小工具
 * 参考热榜文章小工具（zib_widget_ui_hot_posts）架构
 */

if (!defined('ABSPATH')) {
    exit;
}

// 注册小工具 — 挂 after_setup_theme（CSF/Zib_CFSwidget 标准挂载点）
add_action('after_setup_theme', 'zrp_widget_create');

function zrp_widget_create()
{
    if (!class_exists('Zib_CFSwidget')) {
        return;
    }

    Zib_CFSwidget::create('zrp_widget_rating_rank', array(
        'title'       => __('评分排行', 'zibll-rating-plugin'),
        'zib_title'   => true,
        'zib_affix'   => true,
        'zib_show'    => true,
        // 不再声明 size => 'mini'：子比主题会对 mini 小工具在「非 sidebar/nav 容器」(如页面内容区)
        // 弹出「此模块不推荐放置在此位置」后台提示。置空即表示不限容器，侧边栏/页面内容区均可放置，
        // 前台布局由 zrp_rating_rank_posts() 依据容器 id 自适应（窄容器单行 / 宽容器多列）。
        'description' => __('按站友评分高低展示文章排行，自适应宽度：侧边栏/移动菜单内为单行，页面内容区或全宽区域自动转为多列网格', 'zibll-rating-plugin'),
        'fields'      => array(
            array(
                'id'      => 'limit_day',
                'title'   => __('限制时间（最近X天）', 'zibll-rating-plugin'),
                'desc'    => __('设置多少天内发布的文章有效，为0则不限制时间', 'zibll-rating-plugin'),
                'default' => 0,
                'max'     => 999999,
                'min'     => 0,
                'step'    => 1,
                'unit'    => __('天', 'zibll-rating-plugin'),
                'type'    => 'spinner',
            ),
            array(
                'id'      => 'count',
                'title'   => __('最大显示数量', 'zibll-rating-plugin'),
                'default' => 6,
                'max'     => 20,
                'min'     => 1,
                'step'    => 1,
                'unit'    => __('篇', 'zibll-rating-plugin'),
                'type'    => 'spinner',
            ),
            array(
                'title'   => __('新窗口打开', 'zibll-rating-plugin'),
                'id'      => 'target_blank',
                'type'    => 'switcher',
                'default' => false,
            ),
        ),
    ));
}

// ========== 小工具输出函数 — 委托给渲染函数（对齐 zib_widget_ui_hot_posts） ==========
function zrp_widget_rating_rank($args, $instance)
{
    // $args 是 WordPress 侧栏参数（含 id），$instance 是小工具保存的字段值。
    // 把当前侧栏 id 透传给渲染函数，用于自适应布局判定（窄/宽/全宽）。
    if (is_array($instance)) {
        $instance['_sidebar_id'] = !empty($args['id']) ? $args['id'] : '';
    }
    echo zrp_rating_rank_posts($instance);
}

// ========== 核心渲染函数 — 对齐 zib_hot_posts() 架构 ==========
function zrp_rating_rank_posts($args = array(), $echo = false)
{
    $defaults      = array(
        'limit_day'    => 0,
        'target_blank' => '',
        'count'        => 6,
        '_sidebar_id'  => '',
    );
    $args         = wp_parse_args((array) $args, $defaults);

    // 容器类型判定：子比主题侧栏/导航容器 id 含 'sidebar' 或 'nav' → 窄容器（单行列表）；
    // 其余（页面内容区 page_*_content、全宽 *_fluid 等）→ 宽容器，自动多列网格。
    // 含 'fluid' 的宽容器视为全宽，PC 端升到 3 列。
    $sidebar_id = strtolower((string) $args['_sidebar_id']);
    $is_narrow  = (strpos($sidebar_id, 'sidebar') !== false || strpos($sidebar_id, 'nav') !== false);
    $is_fluid   = (strpos($sidebar_id, 'fluid') !== false);
    if ($is_narrow) {
        $wrap_class = 'zrp-rank-narrow';
    } else {
        $wrap_class = 'zrp-rank-wide' . ($is_fluid ? ' zrp-rank-fluid' : '');
    }

    $target_blank = !empty($args['target_blank']) ? ' target="_blank"' : '';

    // 构建查询参数 — 三级排序：评分 DESC > 评分数 DESC > ID ASC
    $posts_args = array(
        'showposts'           => $args['count'],
        'ignore_sticky_posts' => 1,
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'meta_query'          => array(
            'relation'     => 'AND',
            'score_clause' => array(
                'key'     => 'score',
                'value'   => '0',
                'compare' => '>',
                'type'    => 'NUMERIC',
            ),
            'count_clause' => array(
                'key'     => 'zrp_rating_count',
                'compare' => 'EXISTS',
                'type'    => 'NUMERIC',
            ),
        ),
        'orderby'             => array(
            'score_clause' => 'DESC',
            'count_clause' => 'DESC',
            'ID'           => 'ASC',
        ),
        'no_found_rows'       => true,
    );

    // 时间范围过滤（对齐热榜逻辑）
    if ($args['limit_day'] > 0) {
        $current_time             = current_time('Y-m-d H:i:s');
        $posts_args['date_query'] = array(
            array(
                'after'     => date('Y-m-d H:i:s', strtotime('-' . $args['limit_day'] . ' day', strtotime($current_time))),
                'before'    => $current_time,
                'inclusive' => true,
            ),
        );
    }

    // 循环构建帖子 HTML（首条为大卡片，其余归入网格列表）
    $hero_html = '';
    $list_html = '';
    $posts_i   = 1;
    $new_query = new WP_Query($posts_args);

    while ($new_query->have_posts()) {
        $new_query->the_post();
        $title     = get_the_title() . get_the_subtitle(false);
        $permalink = get_permalink();
        // 站友评分数据
        $post_id      = get_the_ID();
        $score        = round(floatval(get_post_meta($post_id, 'score', true)), 1);
        $rating_count = (int) get_post_meta($post_id, 'zrp_rating_count', true);
        $_meta        = sprintf(__('站友评分: %s⭐️（共%d人评分）', 'zibll-rating-plugin'), $score, $rating_count);

        // TOP 徽章（对齐热榜配色）
        $top_bagd_class = array('', 'jb-red', 'jb-yellow');
        $top_bagd       = '<badge class="img-badge left hot ' . ($posts_i == 1 ? 'em12' : '') . (isset($top_bagd_class[$posts_i - 1]) ? $top_bagd_class[$posts_i - 1] : 'b-gray') . '"><i>TOP' . $posts_i . '</i></badge>';

        // 排第一的文章 — 大卡片（完全对齐热榜 DOM）
        if ($posts_i == 1) {
            $_thumb = zib_post_thumbnail('large', 'fit-cover radius8');
            $hero_html .= '<div class="relative">';
            $hero_html .= '<a' . $target_blank . ' href="' . $permalink . '">';
            $hero_html .= '<div class="graphic hover-zoom-img zrp-rank-hero">';
            $hero_html .= $_thumb;
            $hero_html .= '<div class="absolute linear-mask"></div>';
            $hero_html .= '<div class="abs-center left-bottom box-body">';
            $hero_html .= '<div class="mb6"><span class="badg b-theme badg-sm">' . $_meta . '</span></div>';
            $hero_html .= zib_str_cut($title, 0, 32);
            $hero_html .= '</div>';
            $hero_html .= '</div>';
            $hero_html .= '</a>';
            $hero_html .= $top_bagd;
            $hero_html .= '</div>';
        } else {
            // 后续排名 — 横向列表（完全对齐热榜 DOM），外层再用 .zrp-rank-list 包裹成网格
            $_thumb = zib_post_thumbnail('large', 'fit-cover radius8');

            $img_html = '';
            $img_html .= '<a' . $target_blank . ' href="' . $permalink . '">';
            $img_html .= '<div class="graphic">';
            $img_html .= $_thumb;
            $img_html .= '</div>';
            $img_html .= '</a>';

            $posts_meta = '<div class="px12 muted-3-color text-ellipsis flex jsb"><span>' . $_meta . '</span></div>';

            $list_html .= '<div class="flex mt15 relative hover-zoom-img">';
            $list_html .= $img_html;
            $list_html .= '<div class="term-title ml10 flex xx flex1 jsb">';
            $list_html .= '<div class="text-ellipsis-2"><a class=""' . $target_blank . ' href="' . $permalink . '">' . $title . '</a></div>';
            $list_html .= $posts_meta;
            $list_html .= '</div>';
            $list_html .= $top_bagd;
            $list_html .= '</div>';
        }

        $posts_i++;
    }
    wp_reset_query();

    // 组合：首条大卡片 + 其余网格列表（列表为空时（只有 1 篇）不输出列表容器）
    $posts_html = $hero_html;
    if ($list_html) {
        $posts_html .= '<div class="zrp-rank-list">' . $list_html . '</div>';
    }

    $html = '<div class="zib-widget hot-posts zrp-rating-rank ' . $wrap_class . '">' . $posts_html . '</div>';
    if ($echo) {
        echo $html;
    } else {
        return $html;
    }
}
