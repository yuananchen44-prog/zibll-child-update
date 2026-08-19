<?php
/**
 * 子比子主题 - 独立设置页（CSF / Codestar Framework）
 * ─────────────────────────────────────────────────────────────
 * 目的：为子主题（及之后整合进来的阿晨自研插件）提供一个【独立】的后台设置入口，
 *       - 样式与父主题 zibll 完全一致（复用父主题已加载的 CSF 框架，不重复引入）；
 *       - 选项存独立 option key `zibll_child_options`，不污染父主题 `zibll_options`；
 *       - 作为【独立顶级菜单】「子比子主题设置」，与父主题「zibll主题设置」并列（不挂在其下）；
 *       - 所有关键文字说明（标题/页脚/字段说明）均为阿晨自定义，与官方无关。
 *
 * 重要兼容性说明（必读）：
 *   本站点父主题自带的 CSF 是【精简版】，仅支持以下字段类型：
 *     accordion / background / between_number / code_editor / gallery /
 *     group / palette / repeater / select / text / upload
 *   并不包含官方完整版的 switcher / content / callback 等字段。
 *   因此：
 *     - 开/关型选项用「仿父主题 CSF switcher 样式」的自定义开关
 *       （HTML 注入 description + AJAX 即时保存，视觉与父主题开关键一致）；
 *     - 富文本/自定义界面一律写进 section 的 `description`
 *       （CSF 对 description 原样输出、不转义，可放 HTML/链接/按钮）；
 *     - 绝不使用 callback / content 字段（会被静默忽略 → 面板空白）；
 *     - description 注入的自定义 HTML 经 admin_head 注入 scoped CSS（仅本面板）
 *       以复刻 CSF 原生扁平卡片视觉，避免 WP 原生 form-table 风格割裂。
 *
 * 面板内「主题更新」交互说明（2026-07-12 调整）：
 *   - 「检查更新」按钮走 AJAX（wp_ajax），点击【不整页刷新、不跳 section】，
 *     与父主题 CSF 的 AJAX 操作体验一致；清 1h 缓存后重拉、并重渲染面板内区块。
 *   - 「立即增量更新」为重型操作，仍走整页跳转（执行后跳回本面板页）。
 *   - 独立的「设置 → 子主题更新」菜单页【已移除】，更新入口统一收敛到本面板内，
 *     避免重复入口、与父主题菜单结构混淆。
 *
 * 读取方式：前台/后台统一用 zibll_child_get_option('字段id', $default)。
 * 扩展方式：之后整合插件时，仿照下方「我的插件」section 追加即可（见该段注释）。
 *
 * @package zibll-child
 */

if (!defined('ABSPATH')) {
    exit;
}

// 读取封装 zibll_child_get_option() / zibll_child_module_enabled() 见 includes/functions/functions.php
// （本文件在 functions.php 之后加载，可直接调用，无需重复定义）。

// ─── 面板内「检查更新」HTML（供 update section 的 description 注入） ───
// 注意：本函数在 theme-updater.php 之后加载（见 includes/index.php 顺序），
// 因此 zibll_child_get_version() / zibll_child_fetch_update_meta() 均已可用。
// 视觉：扁平 flex 行 + 状态徽标 + CSF 同款按钮，由 admin_head 注入的 scoped CSS 统一。
// 「检查更新」为 <button>（AJAX）；「立即增量更新」为 <a>（整页跳转，复用 theme-updater 链）。
if (!function_exists('zibll_child_update_panel_html')) {
    function zibll_child_update_panel_html()
    {
        if (!function_exists('zibll_child_get_version') || !function_exists('zibll_child_fetch_update_meta')) {
            return '<p class="zibllc-error"><i class="fa fa-fw fa-exclamation-triangle"></i> '
                . '更新模块（theme-updater.php）未加载，请将该文件覆盖到主题目录后重试。</p>';
        }
        if (!current_user_can('manage_options')) {
            return '<p>无权限查看更新信息。</p>';
        }

        $local = zibll_child_get_version();
        $meta  = zibll_child_fetch_update_meta();

        if ($meta === false) {
            $remote     = '（获取失败）';
            $has_update = false;
            $status     = 'error';
        } else {
            $remote     = $meta['version'];
            $has_update = version_compare($remote, $local, '>');
            $status     = $has_update ? 'update' : 'latest';
        }

        // 「检查更新」走 AJAX（wp_ajax_zibll_child_check_update），点击不刷新、不跳 section
        $check_nonce = wp_create_nonce('zibll_child_check_update');
        // 「立即增量更新」复用 theme-updater.php 的执行链（执行后跳回本面板页）
        $update_url = wp_nonce_url(
            admin_url('index.php?zibll_child_do_update=1'),
            'zibll_child_update',
            'zibll_child_update_nonce'
        );

        if ($status === 'error') {
            $badge = '<span class="zibllc-badge zibllc-badge-error">'
                . '<i class="fa fa-fw fa-exclamation-circle"></i> 无法获取更新信息</span>'
                . '<span class="zibllc-hint">请检查 update.json 地址或网络连接（download.achen-mcsever.top）</span>';
        } elseif ($has_update) {
            $badge = '<span class="zibllc-badge zibllc-badge-update">'
                . '<i class="fa fa-fw fa-arrow-circle-up"></i> 有新版本可用，建议更新</span>';
        } else {
            $badge = '<span class="zibllc-badge zibllc-badge-ok">'
                . '<i class="fa fa-fw fa-check-circle"></i> 已是最新版本</span>';
        }

        $html  = '<div class="zibllc-update" id="zibllc-update-box">';
        $html .= '<div class="zibllc-row"><span class="zibllc-k">当前本地版本</span>'
            . '<span class="zibllc-v"><code>' . esc_html($local) . '</code></span></div>';
        $html .= '<div class="zibllc-row"><span class="zibllc-k">远程最新版本</span>'
            . '<span class="zibllc-v"><code>' . esc_html($remote) . '</code></span></div>';
        $html .= '<div class="zibllc-row"><span class="zibllc-k">更新状态</span>'
            . '<span class="zibllc-v">' . $badge . '</span></div>';

        $html .= '<div class="zibllc-actions">'
            . '<button type="button" id="zibllc-check-btn" class="button" data-nonce="' . esc_attr($check_nonce) . '">'
            . '<i class="fa fa-fw fa-refresh"></i> 检查更新</button>';
        if ($has_update) {
            $html .= '<a class="button button-primary" href="' . esc_url($update_url) . '">'
                . '<i class="fa fa-fw fa-cloud-download"></i> 立即增量更新</a>';
        }
        $html .= '</div>';

        if ($meta !== false && !empty($meta['changelog'])) {
            $html .= '<div class="zibllc-changelog-wrap"><h4 class="zibllc-sub">更新说明（v'
                . esc_html($meta['version']) . '）</h4>'
                . '<div class="zibllc-changelog">' . nl2br(esc_html($meta['changelog'])) . '</div></div>';
        }

        $html .= '<p class="zibllc-foot">本功能为子主题（zibll-child）专属更新入口，仅走自有【增量更新】通道'
            . '（只覆盖核心代码文件，不碰你的 style.css / func.php），与「外观 → 主题」里父主题 zibll 的'
            . '原生更新提示无关，请勿混淆。</p>';
        $html .= '</div>';

        return $html;
    }
}

// ─── 基础设置里的「开/关」开关（仿父主题 CSF switcher 样式，AJAX 即时保存） ───
// 精简版 CSF 无 switcher 字段，故手写等价的 HTML 结构（42x22 圆角条 + 18px 圆球 + 0.3s 动画，
// 激活位移 20px），样式在 admin_head 注入的 .zibllc-switch-*（复刻父 .csf-field-switcher 观感）。
// 之后新增「只有开/关」的选项，统一在此追加一行（data-key 指向独立 option zibll_child_modules 的字段 id）。
if (!function_exists('zibll_child_switches_html')) {
    function zibll_child_switches_html()
    {
        $switches = array(
            'enable_qq_avatar' => array(
                'title' => 'QQ 邮箱自动头像',
                'desc'  => '开启后：新注册 / 修改邮箱 / 登录 的 QQ 邮箱用户会自动使用 QQ 头像；'
                    . '已主动上传头像的用户不会被覆盖。关闭后停止自动同步（已设置的头像保留）。',
                'on'    => zibll_child_module_enabled('enable_qq_avatar', true),
            ),
        );

        $nonce = wp_create_nonce('zibll_child_save_switch');
        $html  = '<div class="zibllc-switches">';
        foreach ($switches as $key => $s) {
            $html .= '<div class="zibllc-switch-row">'
                . '<label class="zibllc-switch ' . ($s['on'] ? 'zibllc-on' : '') . '">'
                . '<input type="checkbox" class="zibllc-switch-input"'
                . ' data-key="' . esc_attr($key) . '" data-nonce="' . esc_attr($nonce) . '"'
                . ($s['on'] ? ' checked' : '') . '>'
                . '<span class="zibllc-slider"></span>'
                . '</label>'
                . '<div class="zibllc-switch-meta">'
                . '<div class="zibllc-switch-title">' . esc_html($s['title']) . '</div>'
                . '<div class="zibllc-switch-desc">' . esc_html($s['desc']) . '</div>'
                . '</div>'
                . '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}

// ─── 面板内「我的插件」：整合插件全量开关 ───
// 复用 .zibllc-switches 仿父主题 switcher 样式 + AJAX 即时保存（zibll_child_ajax_save_switch）。
// 各插件 loader 通过 zibll_child_module_enabled() 读取同一 option key 完成早退；
// 默认全开（option 未设置该 key 时 zibll_child_option_enabled 返回 true）。
if (!function_exists('zibll_child_module_switches_html')) {
    function zibll_child_module_switches_html()
    {
        $switches = array(
            'module_xiazhi_qz' => array(
                'title' => '夏稚文章前缀',
                'desc'  => '为文章标题添加前缀（图片/文字模式）。关闭后子主题不再加载内置副本；'
                    . '若服务器已启用同名独立插件，则由其托管，互不影响。',
                'on'    => zibll_child_module_enabled('module_xiazhi_qz', true),
            ),
            'module_rating' => array(
                'title' => '文章评分',
                'desc'  => '前台文章评分与平均分展示。关闭后子主题不再加载内置副本；'
                    . '若服务器已启用同名独立插件，则由其托管，评分数据共用、不丢失。',
                'on'    => zibll_child_module_enabled('module_rating', true),
            ),
            'module_comment_emoji' => array(
                'title' => '评论表情包',
                'desc'  => '评论框表情面板（后台可视化上传/分组）。关闭后子主题不再加载内置副本。',
                'on'    => zibll_child_module_enabled('module_comment_emoji', true),
            ),
        );

        $nonce = wp_create_nonce('zibll_child_save_switch');
        $html  = '<div class="zibllc-switches zibllc-switches-modules">';
        foreach ($switches as $key => $s) {
            $html .= '<div class="zibllc-switch-row">'
                . '<label class="zibllc-switch ' . ($s['on'] ? 'zibllc-on' : '') . '">'
                . '<input type="checkbox" class="zibllc-switch-input"'
                . ' data-key="' . esc_attr($key) . '" data-nonce="' . esc_attr($nonce) . '"'
                . ($s['on'] ? ' checked' : '') . '>'
                . '<span class="zibllc-slider"></span>'
                . '</label>'
                . '<div class="zibllc-switch-meta">'
                . '<div class="zibllc-switch-title">' . esc_html($s['title']) . '</div>'
                . '<div class="zibllc-switch-desc">' . esc_html($s['desc']) . '</div>'
                . '</div>'
                . '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}

// ─── 面板内「我的插件」：文章评分插件状态卡（展示整合状态 + 数据一致性佐证） ───
// 复用 .zibllc-* 扁平卡片样式（与主题更新区一致）。
// 运行来源判定：独立插件已激活 → 由其托管；否则若内置副本存在 → 由子主题托管；
// 两者共用同一套存储 key（zrp_rating_options / score / zrp_rating_count / zrp_user_rating_{id}），数据天然一致。
if (!function_exists('zibll_child_rating_plugin_card_html')) {
    function zibll_child_rating_plugin_card_html()
    {
        $standalone = function_exists('zibll_child_rating_standalone_active')
            ? zibll_child_rating_standalone_active() : false;
        $bundled = file_exists(get_stylesheet_directory() . '/includes/rating-plugin/zibll-rating-plugin.php');

        if ($standalone) {
            $src_badge = '<span class="zibllc-badge zibllc-badge-ok"><i class="fa fa-fw fa-plug"></i> 由独立插件 zibll-rating-plugin 托管</span>';
            $src_note  = '服务器已启用同名独立插件，评分功能与历史数据由它提供服务；子主题内置副本处于休眠状态，不会重复加载或冲突。';
        } elseif ($bundled) {
            $src_badge = '<span class="zibllc-badge zibllc-badge-ok"><i class="fa fa-fw fa-check-circle"></i> 由子主题内置副本托管</span>';
            $src_note  = '未启用独立插件，评分功能由子主题内置副本提供；与独立插件共用同一套数据存储，数据自动继承、无需迁移。';
        } else {
            $src_badge = '<span class="zibllc-badge zibllc-badge-error"><i class="fa fa-fw fa-exclamation-triangle"></i> 未检测到评分插件</span>';
            $src_note  = '既未启用独立插件，子主题也未内置评分模块。';
        }

        // 评分功能开关状态（插件自身选项；若无函数则按默认启用展示）
        $enabled = function_exists('zrp_get_option') ? zrp_get_option('rating_enable', true) : true;
        $en_badge = $enabled
            ? '<span class="zibllc-badge zibllc-badge-ok">已启用</span>'
            : '<span class="zibllc-badge zibllc-badge-update">已停用</span>';

        // 已评分文章数（数据一致性佐证）。注意：本卡片在主题加载阶段（早于 zib_require_end）即生成，
        // 彼时插件的 inc/functions.php 尚未加载，故直接查 postmeta、不依赖插件函数。
        $rated = '';
        global $wpdb;
        if (isset($wpdb)) {
            $cnt = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value > 0",
                    'score'
                )
            );
            if ($cnt > 0) {
                $rated = '<div class="zibllc-row"><span class="zibllc-k">已有评分的文章</span>'
                    . '<span class="zibllc-v"><code>' . esc_html($cnt) . '</code> 篇</span></div>';
            }
        }

        $settings_url = admin_url('admin.php?page=zrp_rating_options');

        $html  = '<div class="zibllc-update" id="zibllc-rating-card">';
        $html .= '<div class="zibllc-row"><span class="zibllc-k">模块</span>'
            . '<span class="zibllc-v"><strong>子比主题 - 文章评分插件</strong>（v1.1.0）</span></div>';
        $html .= '<div class="zibllc-row"><span class="zibllc-k">运行来源</span>'
            . '<span class="zibllc-v">' . $src_badge . '</span></div>';
        $html .= '<div class="zibllc-row"><span class="zibllc-k">评分功能</span>'
            . '<span class="zibllc-v">' . $en_badge . '</span></div>';
        $html .= $rated;
        $html .= '<div class="zibllc-actions">'
            . '<a class="button" href="' . esc_url($settings_url) . '"><i class="fa fa-fw fa-star"></i> 评分插件设置</a>'
            . '</div>';
        $html .= '<p class="zibllc-foot">' . esc_html($src_note)
            . '数据存储：选项 <code>zrp_rating_options</code>、文章 meta <code>score</code>/<code>zrp_rating_count</code>、'
            . '用户 meta <code>zrp_user_rating_{文章ID}</code>，独立于子主题，停用或切换来源均不丢失。</p>';
        $html .= '</div>';

        return $html;
    }
}

// ─── 面板专属样式 + AJAX 脚本（仅本面板 page=zibll_child_options 时注入，不污染全局） ──
// 用 .zibllc-* 前缀 + 仅在 CSP 面板页输出，杜绝与 WP/父主题样式冲突。
add_action('admin_head', 'zibll_child_options_admin_assets');
function zibll_child_options_admin_assets()
{
    if (empty($_GET['page']) || $_GET['page'] !== 'zibll_child_options') {
        return;
    }
    echo '<style id="zibll-child-options-css">
.zibllc-update{font-size:13px;color:#23282d;transition:opacity .2s;}
.zibllc-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f0f0f1;}
.zibllc-row:last-of-type{border-bottom:none;}
.zibllc-k{color:#646970;font-weight:500;}
.zibllc-v code{background:#f0f0f1;padding:2px 8px;border-radius:3px;font-size:12px;}
.zibllc-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:3px;font-weight:600;font-size:12px;}
.zibllc-badge-ok{background:#e7f6ea;color:#1a7f37;}
.zibllc-badge-update{background:#fdecec;color:#d63638;}
.zibllc-badge-error{background:#fdecec;color:#d63638;}
.zibllc-hint{display:block;color:#8c8f94;font-size:12px;margin-top:4px;}
.zibllc-actions{margin:14px 0 4px;display:flex;gap:8px;}
.zibllc-changelog-wrap{margin-top:14px;}
.zibllc-sub{margin:0 0 6px;font-size:13px;color:#23282d;}
.zibllc-changelog{background:#fff;border:1px solid #e2e4e7;border-radius:4px;padding:10px 14px;line-height:1.7;color:#3c434a;}
.zibllc-foot{margin-top:14px;color:#8c8f94;font-size:12px;line-height:1.6;}
.zibllc-error{color:#d63638;}
.zibllc-loading{opacity:.45;pointer-events:none;}
/* ── 仿父主题 CSF switcher 的开关键（仅本面板生效） ── */
.zibllc-switches{margin:2px 0 6px;}
.zibllc-switch-row{display:flex;align-items:flex-start;gap:14px;padding:12px 0;border-bottom:1px solid #f0f0f1;}
.zibllc-switch{position:relative;display:inline-block;width:42px;height:22px;flex:0 0 auto;margin-top:1px;cursor:pointer;}
.zibllc-switch .zibllc-switch-input{position:absolute;opacity:0;width:0;height:0;}
.zibllc-slider{position:absolute;inset:0;background:#b4b9be;border-radius:100px;transition:.3s;}
.zibllc-slider:before{content:"";position:absolute;width:18px;height:18px;left:2px;top:2px;background:#fff;border-radius:100px;transition:.3s;}
.zibllc-switch .zibllc-switch-input:checked + .zibllc-slider{background:#46b450;}
.zibllc-switch .zibllc-switch-input:checked + .zibllc-slider:before{transform:translateX(20px);}
.zibllc-switch .zibllc-switch-input:active + .zibllc-slider:before{width:25px;}
.zibllc-switch .zibllc-switch-input:checked:active + .zibllc-slider:before{transform:translateX(12px);width:25px;}
.zibllc-switch-meta{flex:1;min-width:0;}
.zibllc-switch-title{font-weight:600;color:#23282d;font-size:13px;}
.zibllc-switch-desc{color:#8c8f94;font-size:12px;line-height:1.6;margin-top:3px;}
</style>';
    // AJAX：检查更新（点击不刷新、不跳 section，与父主题 CSF 的 AJAX 操作一致）
    echo '<script id="zibll-child-options-js">
(function($){
  $(document).on("click","#zibllc-check-btn",function(e){
    e.preventDefault();
    var $btn=$(this), $box=$("#zibllc-update-box");
    $btn.prop("disabled",true).text("检查中…");
    $box.addClass("zibllc-loading");
    $.post(ajaxurl,{action:"zibll_child_check_update",nonce:$btn.data("nonce")},function(res){
      if(res && res.success && res.data && res.data.html){
        $box.replaceWith(res.data.html);
      }else{
        $btn.prop("disabled",false).text("检查更新");
        $box.removeClass("zibllc-loading");
        alert("检查更新失败，请稍后重试");
      }
    },"json").fail(function(){
      $btn.prop("disabled",false).text("检查更新");
      $box.removeClass("zibllc-loading");
      alert("检查更新失败，请稍后重试");
    });
  });
  // 开/关开关：即时 AJAX 保存（与父主题 CSF switcher 体验一致，不刷新、不跳 section）
  $(document).on("change",".zibllc-switch-input",function(){
    var $inp=$(this), $label=$inp.closest(".zibllc-switch"),
        key=$inp.data("key"), nonce=$inp.data("nonce"),
        val=$inp.is(":checked")?"1":"0";
    $.post(ajaxurl,{action:"zibll_child_save_switch",key:key,value:val,nonce:nonce},function(res){
      if(res && res.success){
        $label.toggleClass("zibllc-on",$inp.is(":checked"));
      }else{
        $inp.prop("checked",!$inp.is(":checked"));
        $label.toggleClass("zibllc-on",$inp.is(":checked"));
        alert("保存失败，请稍后重试");
      }
    },"json").fail(function(){
      $inp.prop("checked",!$inp.is(":checked"));
      $label.toggleClass("zibllc-on",$inp.is(":checked"));
      alert("保存失败，请稍后重试");
    });
  });
})(jQuery);
</script>';
}

// ─── AJAX 处理：检查更新（清缓存 → 重拉 → 返回重渲染的面板 HTML） ──
add_action('wp_ajax_zibll_child_check_update', 'zibll_child_ajax_check_update');
function zibll_child_ajax_check_update()
{
    check_ajax_referer('zibll_child_check_update', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('无权限');
    }
    // 清 1h 缓存，使下方 zibll_child_update_panel_html() 真实重新远程拉取
    delete_transient('zibll_child_update_meta');
    wp_send_json_success(array('html' => zibll_child_update_panel_html()));
}

// ─── AJAX 处理：保存「开/关」开关（即时写入独立 option zibll_child_modules，避免被 CSF 面板覆盖） ──
add_action('wp_ajax_zibll_child_save_switch', 'zibll_child_ajax_save_switch');
function zibll_child_ajax_save_switch()
{
    check_ajax_referer('zibll_child_save_switch', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('无权限');
    }
    $key = isset($_POST['key']) ? sanitize_key($_POST['key']) : '';
    $val = isset($_POST['value']) ? $_POST['value'] : '0';
    // 仅允许已知开关字段，防止任意写入 option
    $allowed = array('enable_qq_avatar', 'module_xiazhi_qz', 'module_rating', 'module_comment_emoji');
    if (!in_array($key, $allowed, true)) {
        wp_send_json_error('非法字段');
    }
    $opts = get_option('zibll_child_modules', array());
    $opts[$key] = ($val === '1' || $val === 'on' || $val === true) ? '1' : '0';
    update_option('zibll_child_modules', $opts);
    wp_send_json_success(array('key' => $key, 'value' => $opts[$key]));
}

// ─── 注册 CSF 设置面板（仅后台，且 CSF 已加载时） ────────────
if (!function_exists('zibll_child_csf_options')) {
    function zibll_child_csf_options()
    {
        if (!class_exists('CSF')) {
            return;
        }

        $prefix = 'zibll_child_options';

        // 主面板：作为【独立顶级菜单】「子比子主题设置」，与父主题「zibll主题设置」并列。
        // 不挂在其下，避免与父主题设置混淆；文字说明均为阿晨自定义，与官方文案无关。
        CSF::createOptions($prefix, array(
            'menu_title'         => '子比子主题设置',
            'menu_slug'          => 'zibll_child_options',
            'menu_type'          => 'menu',            // 独立顶级菜单（默认即顶级，显式声明更清晰）
            'menu_position'      => 60,                // 与父主题菜单错开位置，互不挤占
            'menu_icon'          => 'dashicons-admin-appearance',
            'framework_title'    => '子比子主题（阿晨维护）',
            'show_in_customizer' => false,
            'save_defaults'      => true,
            'footer_text'        => '子比子主题 · 由阿晨维护 · https://navigation.hoarfall.com/',
            'footer_credit'      => '<i class="fa fa-fw fa-heart-o"></i> ',
            'theme'              => 'light',
        ));

        // 一级分组：基础设置（已有子主题功能的开关 + 外观配置集中放这里）
        // 注：精简版 CSF 无 switcher 字段，开/关型选项改用「仿父主题 CSF switcher 样式」的自定义开关
        //     （zibll_child_switches_html() 注入到 description，AJAX 即时保存，视觉与父主题一致）；
        //     text 等正常字段仍走 CSF 原生，由 CSF 自动保存。
        CSF::createSection($prefix, array(
            'id'          => 'basic',
            'title'       => '基础设置',
            'icon'        => 'fa fa-fw fa-cog',
            'description' => zibll_child_switches_html(),
            'fields'      => array(
                // 其他配置示范：用 text 字段承接任意文本设置（此处以站点副标题为例）
                array(
                    'id'      => 'site_subtitle',
                    'type'    => 'text',
                    'title'   => '站点副标题',
                    'desc'    => '可选，可显示在页脚或前端某处。读取：<code>zibll_child_get_option(\'site_subtitle\')</code>',
                    'default' => '',
                ),
            ),
        ));

        // 一级分组：主题更新（直接在面板内检查/更新，入口统一收敛于此，不再另建独立菜单页）
        // 注意：本精简版 CSF 无 callback 字段，故把更新 UI 作为 section 的 description 原样注入。
        // 「检查更新」走 AJAX（不刷新、不跳 section），「立即增量更新」走整页跳转（执行后回本面板）。
        CSF::createSection($prefix, array(
            'id'          => 'update',
            'title'       => '主题更新',
            'icon'        => 'fa fa-fw fa-refresh',
            'description' => zibll_child_update_panel_html(),
        ));

        // 一级分组：我的插件（整合阿晨自研插件的集中入口）
        // 首个已整合模块：文章评分插件（zibll-rating-plugin）。
        // 其代码以「内置副本」形式随子主题分发，并通过 rating-plugin-loader 与服务器
        // 已有独立插件实现零冲突、数据一致的共存/接管（详见 includes/rating-plugin-loader.php）。
        CSF::createSection($prefix, array(
            'id'          => 'plugins',
            'title'       => '我的插件',
            'icon'        => 'fa fa-fw fa-puzzle-piece',
            'description' => zibll_child_module_switches_html()
                . zibll_child_rating_plugin_card_html()
                . '<p style="margin-top:12px;color:#8c8f94;font-size:12px;">之后整合更多阿晨自研插件时，'
                . '可仿照「内置副本 + 条件加载器」的方式在此追加卡片或子 section，'
                . '前缀统一用 <code>zibll_child_options</code>，读取用 <code>zibll_child_get_option(\'字段id\')</code>。</p>',
        ));

        // [已移除] 子主题授权模块（license 面板 / 域名绑定激活码 / enhanced_css 授权门控）
        // 于 v1.2.22 完整删除：includes/functions/child-auth.php + child-auth-core.php 已删，
        // 不再依赖任何授权判断，子主题所有功能默认开放。如需自定义 CSS 请改用子主题 style.css。
    }
}

// 父主题 zibll 在 inc/inc.php 中已加载 CSF；子主题 includes 在其后加载，
// 直接在后台注册面板（与父主题 admin-options.php 同风格）。
// 本文件在 theme-updater.php 之后加载（见 includes/index.php），故更新函数已就绪。
if (is_admin() && class_exists('CSF')) {
    zibll_child_csf_options();
}
