/**
 * 子比子主题 - 切换主题时询问是否清除整合插件数据
 *
 * 后台「外观 → 主题」点击其它主题的「启用」时，拦截该点击并弹出询问框：
 *   - 取消：不切换
 *   - 保留数据并切换：照常切换，不清理
 *   - 清除数据并切换：写入 cookie 标记，切换同时由服务端 switch_theme 钩子执行清理
 *
 * 仅在前台/后台主题列表页、且当前启用主题为本子主题时生效（由 PHP 端 enqueue 控制）。
 */
(function ($) {
    'use strict';

    function setCookie(name, value) {
        if (value === '' || value === null) {
            document.cookie = name + '=; path=/; max-age=0';
        } else {
            document.cookie = name + '=' + encodeURIComponent(value) + '; path=/';
        }
    }

    function btnStyle(bg, color) {
        return {
            border: 'none',
            'border-radius': '4px',
            padding: '8px 14px',
            cursor: 'pointer',
            'font-size': '13px',
            background: bg,
            color: color
        };
    }

    function openModal(href, themeName) {
        var childName = (window.ZIBLL_CHILD_SWITCH && ZIBLL_CHILD_SWITCH.themeName) || '本子主题';

        var $overlay = $('<div></div>').css({
            position: 'fixed', left: 0, top: 0, right: 0, bottom: 0,
            background: 'rgba(0,0,0,0.55)', 'z-index': 999999,
            display: 'flex', 'align-items': 'center', 'justify-content': 'center'
        });

        var $box = $('<div></div>').css({
            background: '#fff', 'max-width': '480px', width: '90%',
            'border-radius': '8px', padding: '24px',
            'box-shadow': '0 10px 40px rgba(0,0,0,0.3)',
            'font-family': '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif',
            'font-size': '14px', color: '#1d2327'
        });

        var $title = $('<h2></h2>').css({ margin: '0 0 12px', 'font-size': '18px' }).text('切换主题');

        var $desc = $('<p></p>').css({ 'line-height': '1.6', 'margin': '0 0 20px' });
        $desc.append('你正在离开「');
        $desc.append($('<strong></strong>').text(childName));
        $desc.append('」并启用「');
        $desc.append($('<strong></strong>').text(themeName));
        $desc.append('」。');
        $desc.append($('<br>'));
        $desc.append('本子主题整合的插件（文章评分 / 夏稚前缀 / 评论表情等）会在数据库中保留数据。');
        $desc.append($('<br>'));
        $desc.append($('<strong></strong>').text('是否一并清除这些插件数据？'));

        var $actions = $('<div></div>').css({ display: 'flex', 'justify-content': 'flex-end', gap: '10px' });

        var $cancel = $('<button type="button"></button>').css(btnStyle('#f6f7f7', '#1d2327')).text('取消');
        var $keep   = $('<button type="button"></button>').css(btnStyle('#2271b1', '#fff')).text('保留数据并切换');
        var $clean  = $('<button type="button"></button>').css(btnStyle('#d63638', '#fff')).text('清除数据并切换');

        function close() { $overlay.remove(); }

        $cancel.on('click', function () { close(); });
        $keep.on('click', function () {
            setCookie('zibll_child_cleanup_data', '');
            window.location.href = href;
        });
        $clean.on('click', function () {
            setCookie('zibll_child_cleanup_data', '1');
            window.location.href = href;
        });

        $actions.append($cancel, $keep, $clean);
        $box.append($title, $desc, $actions);
        $overlay.append($box);
        $('body').append($overlay);

        $overlay.on('click', function (e) {
            if (e.target === $overlay[0]) { close(); }
        });
    }

    $(function () {
        var childSlug = 'zibll-child';

        $('a').each(function () {
            var $a = $(this);
            var href = $a.attr('href') || '';
            if (href.indexOf('action=activate') === -1) { return; }       // 只拦「启用」
            if (href.indexOf('stylesheet=' + childSlug) !== -1) { return; } // 跳过本子主题自身

            // 取目标主题名（卡片标题）
            var $card = $a.closest('.theme');
            var themeName = '';
            if ($card.length) {
                var $h = $card.find('.theme-name').filter(':visible').first();
                if (!$h.length) { $h = $card.find('h2, h3').first(); }
                themeName = $.trim($h.text());
            }
            if (!themeName) { themeName = '其它主题'; }

            $a.on('click', function (e) {
                e.preventDefault();
                openModal(href, themeName);
            });
        });
    });
})(jQuery);
