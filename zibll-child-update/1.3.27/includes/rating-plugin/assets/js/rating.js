/**
 * Zibll 文章评分插件 - 前端交互脚本
 * 依赖：jQuery、子比主题 main.js（_win、zib_ajax、notyf）
 */
(function ($) {
    'use strict';

    // 等待 jQuery 就绪
    $(function () {
        // 将悬浮窗评分组件移至 <body> 末尾，避免父级容器 CSS 属性（如 transform）
        // 破坏 position: fixed 相对于视口的定位行为，确保始终固定在屏幕右下角
        var $floatBox = $('.zrp-rating-box.zrp-mode-float');
        if ($floatBox.length) {
            $floatBox.appendTo(document.body);
        }

        initRating();
    });

    function initRating() {
        // 检查评分容器是否存在
        var $boxes = $('.zrp-rating-box');
        if (!$boxes.length) {
            return;
        }

        $boxes.each(function () {
            var $box = $(this);
            var $stars = $box.find('.zrp-star');
            var postId = $box.data('post-id');
            var isRated = $box.find('.zrp-stars').data('user-rated');

            if (!postId) {
                return;
            }

            // 未评分时才绑定交互
            if (!isRated) {
                // 鼠标悬停预览
                $stars.on('mouseenter', function () {
                    var val = parseInt($(this).data('value'), 10);
                    highlightStars($stars, val);
                });

                // 鼠标离开恢复
                $box.on('mouseleave', function () {
                    highlightStars($stars, 0);
                });

                // 点击提交评分
                $stars.on('click', function () {
                    var val = parseInt($(this).data('value'), 10);
                    submitRating($box, $stars, postId, val);
                });
            }
        });
    }

    /**
     * 高亮星级
     */
    function highlightStars($stars, count) {
        $stars.each(function (i) {
            var $s = $(this);
            if (i < count) {
                $s.addClass('hover');
            } else {
                $s.removeClass('hover');
            }
        });
    }

    /**
     * 提交评分
     */
    function submitRating($box, $stars, postId, rating) {
        // 检查主题 zib_ajax 是否可用
        if (typeof zib_ajax !== 'function') {
            console.warn('zrp_rating: zib_ajax not available');
            return;
        }

        // 检查登录状态 — 未登录时触发主题登录弹窗（对齐 main.js 中 .signin-loader 点击逻辑）
        if (typeof _win !== 'undefined' && !_win.is_signin) {
            if (_win.sign_type === 'page') {
                window.location.href = _win.signin_url;
            } else {
                $('.modal:not(#u_sign)').modal('hide');
                $('#u_sign').modal('show');
                if (_win.signin_wx_priority) {
                    $('a[href="#tab-qrcode-signin"]').tab('show');
                    $('.social-login-item.weixingzh:first').click();
                } else {
                    $('a[href="#tab-sign-in"]').tab('show');
                }
            }
            return;
        }

        // 禁用星星点击（防止重复提交）
        $stars.off('click mouseenter');
        $box.off('mouseleave');

        var data = {
            action: 'zrp_submit_rating',
            _wpnonce: (typeof zrp_rating !== 'undefined' ? zrp_rating.nonce : ''),
            post_id: postId,
            rating: rating
        };

        // 使用子比主题的 AJAX 方法
        zib_ajax($box, data, function (res) {
            if (!res.error) {
                // 兼容两种响应格式：zib_send_json_success（展平）和 wp_send_json_success（嵌套）
                var avg   = (res.avg !== undefined) ? res.avg : (res.data && res.data.avg !== undefined ? res.data.avg : '0.0');
                var count = (res.count !== undefined) ? res.count : (res.data && res.data.count !== undefined ? res.data.count : 0);

                // 更新高亮为已评分状态
                $stars.each(function (i) {
                    var $s = $(this);
                    if (i < rating) {
                        $s.addClass('active').removeClass('hover');
                    }
                });

                // 更新评分数据
                $box.find('.zrp-avg-score').text(avg);
                $box.find('.zrp-rating-count').text(count);

                // 显示"我的评分"行
                var myHtml = '我的评分：' + rating + ' 星';
                var $my = $box.find('.zrp-rating-my');
                if ($my.length) {
                    $my.html(myHtml);
                } else {
                    $box.append('<div class="zrp-rating-my em09 text-center muted-3-color mt3">' + myHtml + '</div>');
                }

                // 显示并更新分数
                var $scoreVal = $box.find('.zrp-rating-score-value');
                $scoreVal.text(avg).show();
            }
        }, true);
    }

})(jQuery);
