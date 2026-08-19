<?php
/*禁止倒卖*/
function xiazhi_qz_title_prefix_widget_create()
{
    $widget_key = 'xiazhi_qz_title_prefix_widget';

    Zib_CFSwidget::create($widget_key, array(
        'title'       => '夏稚标题前缀设置',
        'zib_title'   => false,
        'zib_affix'   => false,
        'zib_show'    => false,
        'zib_animation_in' => false,
        'description' => '用于前台投稿页侧边栏，显示文章标题前缀设置。',
        'fields'      => array(),
    ));
}
add_action('zib_require_end', 'xiazhi_qz_title_prefix_widget_create');

function xiazhi_qz_title_prefix_widget($args, $instance)
{
    $edit_id = !empty($_REQUEST['edit']) ? (int) $_REQUEST['edit'] : 0;
    echo xiazhi_qz_frontend_title_prefix_box($edit_id);
}
