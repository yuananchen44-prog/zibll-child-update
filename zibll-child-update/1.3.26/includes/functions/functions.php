<?php
/**
 * 子比子主题 - 功能函数
 *
 * 加载时机：
 *   本文件由 includes/index.php 通过父主题的 zib_require() 加载，
 *   而 includes/index.php 又在父主题 inc/inc.php 之后被 require。
 *   因此本文件执行时，父主题所有模块、函数、类均已就绪，可安全调用
 *   _pz()、zib_send_json_error()、Zibpay 等任何父主题 API。
 *
 * 设计原则（子主题开发的「三不」）：
 *   1. 不修改、不劫持父主题的主题识别机制
 *      （stylesheet / template / wp_get_theme）。这些是 WordPress 与
 *      依赖子比主题的插件判断「当前主题」的依据，擅自篡改会引入
 *      难以排查的兼容性问题。
 *   2. 只通过 WordPress 标准钩子（action / filter）做「加法」或「移除」，
 *      不在运行时改写核心选项。
 *   3. 涉及输出拦截时，优先用服务端 remove_action，其次才是客户端脚本。
 *
 * 关于「插件兼容性」的说明（本文件【不】拦截 stylesheet）：
 *   依赖 zibll 的插件应自行用 get_template()（父/子主题下均返回 'zibll'）
 *   判断主题，而非 get_stylesheet()（子主题下返回子主题 slug，会误报）。
 *   因此子主题侧【不做】stylesheet 拦截，以避免破坏模板定位与主题设置；
 *   若某第三方插件误用 get_stylesheet() 而报错，请在该插件内改为 get_template()。
 *
 * @package zibll-child
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─── 子主题独立设置读取封装（最先加载，供后续所有模块调用） ──
// 选项独立存于 option key `zibll_child_options`，不污染父主题 `zibll_options`。
if (!function_exists('zibll_child_get_option')) {
    function zibll_child_get_option($key = '', $default = false)
    {
        $options = get_option('zibll_child_options', array());
        if (empty($key)) {
            return $options;
        }
        return isset($options[$key]) ? $options[$key] : $default;
    }
}

// 把「启用开关」的存储值（select 返回字符串 '1'/'0'，未设置时为 true）统一成布尔。
// 注意：不能用空字符串/0 的字符串直接当 falsy 判断（'0' 在 PHP 里为真），
// 因此以「等于 '0' 才视作关闭」为准。
if (!function_exists('zibll_child_option_enabled')) {
    function zibll_child_option_enabled($key, $default = true)
    {
        $v = zibll_child_get_option($key, $default);
        if ($v === true || $v === 1 || $v === '1') {
            return true;
        }
        if ($v === false || $v === 0 || $v === '0') {
            return false;
        }
        return (bool) $default;
    }
}


/* ─────────────────────────────────────────────────────────────
 * 1. 控制台调试信息屏蔽（服务端）
 * ─────────────────────────────────────────────────────────────
 * 父主题 inc/functions/zib-footer.php 通过
 *   add_action('wp_footer',    'zib_win_console', 99);
 *   add_action('admin_footer', 'zib_win_console', 99);
 * 向页面注入一段 <script>，在浏览器控制台打印：
 *     console.log("数据库查询：X次 | 页面生成耗时：Xms");
 * 该输出由服务端生成，直接 remove_action 即可彻底移除，零前端开销。
 *
 * 挂载在 zib_require_end：此时父主题已将 zib_win_console 注册到钩子上，
 * 移除方能生效；同时又早于前端 wp_footer 输出，时机正确。
 */
add_action('zib_require_end', 'zibll_child_init');

function zibll_child_init()
{
    remove_action('wp_footer',    'zib_win_console', 99);
    remove_action('admin_footer', 'zib_win_console', 99);
}

/* ─────────────────────────────────────────────────────────────
 * 2. 子主题样式加载
 * ─────────────────────────────────────────────────────────────
 * 父主题样式（句柄 _main，对应 main.min.css / bootstrap.min.css 等）
 * 已由父主题 _load_scripts() → _cssloader() 自动注册并加载。
 *
 * 此处将子主题 style.css 声明为「依赖 _main」，WordPress 会保证它
 * 在父主题样式【之后】输出，从而实现标准、可靠的样式层叠覆盖。
 * 缓存戳使用子主题自身版本号：子主题样式变更时只需递增 style.css
 * 中的 Version 即可强制浏览器刷新。
 */
add_action('wp_enqueue_scripts', 'zibll_child_enqueue_styles', 20);

function zibll_child_enqueue_styles()
{
    if (is_admin()) {
        return;
    }

    wp_enqueue_style(
        'zibll-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('_main'),                       // 依赖父主题 _main，保证加载顺序
        wp_get_theme()->get('Version')        // 子主题版本号，作为缓存戳
    );
}

/* ─────────────────────────────────────────────────────────────
 * 3. 控制台品牌横幅过滤脚本（客户端）
 * ─────────────────────────────────────────────────────────────
 * 父主题 JS 会在浏览器控制台打印品牌横幅，这些来自前端 JS，服务端
 * 无法用 remove_action 处理：
 *   - js/main.js / js/admin-main.js : " %c Zibll Theme %c https://zibll.com "
 *   - js/widget-set.js              : "Zibll Widget"
 *
 * 解决方式：以最高优先级（action 优先级 1）在 <head> 注入一段极轻量
 * 的 monkey-patch 脚本，抢在父主题 JS（位于 footer）之前包装 console.log，
 * 按规则静默上述输出。仅拦截 console.log，error / warn / info 等
 * 其他日志方法不受影响，正常告警仍可显示。
 */
add_action('wp_enqueue_scripts',    'zibll_child_enqueue_console_filter', 1);
add_action('admin_enqueue_scripts', 'zibll_child_enqueue_console_filter', 1);

function zibll_child_enqueue_console_filter()
{
    wp_enqueue_script(
        'zibll-child-console-filter',
        get_stylesheet_directory_uri() . '/js/console-filter.js',
        array(),                              // 无依赖，确保在最前面加载
        wp_get_theme()->get('Version'),
        false                                 // false = 在 <head> 输出，早于 footer 中的父主题 JS
    );
}

/* ─────────────────────────────────────────────────────────────
 * 4. 站点自定义扩展点（子主题标准扩展方式）
 * ─────────────────────────────────────────────────────────────
 * 本文件只放「通用、长期」的子主题逻辑。你的站点专属定制推荐写在
 * 根目录 func.php（在线更新子主题时不会被覆盖）。三种扩展机制：
 *
 *   (a) 模板覆盖：在子主题根目录放与父主题同名的模板文件
 *       （如 footer.php / single.php / archive.php），WordPress 会
 *       优先使用子主题版本，父主题原文件作为回退。无需任何 PHP 代码。
 *
 *   (b) 钩子扩展：通过 add_action / add_filter 挂接父主题暴露的钩子。
 *       例：给 <body> 追加 class（父主题 zib-theme.php 提供该过滤器）
 *           add_filter('zib_add_bodyclass', function ($class) {
 *               return trim($class . ' zibll-child-active');
 *           });
 *
 *   (c) 样式覆盖：直接在子主题 style.css 书写更高优先级的规则，
 *       覆盖 _main 中的样式（见 style.css 顶部注释）。
 *
 * 长期、可复用的功能请新建 includes/ 下的模块文件，并在
 * includes/index.php 中通过 zib_require() 注册加载。
 */

/* ─────────────────────────────────────────────────────────────
 * 5. 头像增强：QQ 邮箱用户优先显示 QQ 头像
 * ─────────────────────────────────────────────────────────────
 * 背景：父主题 zib_get_avatar()（zib-theme.php:608）只处理两件事——
 *   ① 用户上传/绑定的「自定义头像」(custom_avatar meta，存图片 URL)
 *   ② 站点默认头像。它【不会】为 QQ 邮箱用户拉取腾讯系头像，导致大量
 *   QQ 邮箱注册的用户只能显示干巴巴的默认图。
 *
 * 为何不直接覆盖函数/过滤器（踩坑提示）：
 *   教程（zhutipu.com）教的是直接改父主题 zib-theme.php 里的
 *   zib_get_avatar，但这会被主题更新覆盖；且子比全站头像渲染链路是
 *     zib_get_avatar_box() → zib_get_data_avatar() → zib_get_avatar()
 *   其中 zib_get_data_avatar()（zib-theme.php:589）是【直接调用函数】，
 *   不经过 WordPress 的 pre_get_avatar 过滤器。因此无论覆盖
 *   pre_get_avatar 还是尝试重定义 zib_get_avatar（裸函数，无
 *   function_exists 守卫，重定义会 fatal）都【无法】在子主题侧生效。
 *
 * 本方案（子主题安全做法）：复用父主题已有的「自定义头像」优先级——
 *   当 QQ 邮箱用户尚未设置自定义头像时，自动把 QQ 头像 URL 写入
 *   custom_avatar user meta，父主题渲染时即用该 QQ 头像，全站
 *   （文章列表、评论、侧边栏小工具、私信、用户中心、详情页）一致生效。
 *
 * 头像优先级（与父逻辑一致）：
 *   1) 用户主动上传/绑定的自定义头像 → 不动（最高优先）
 *   2) 否则 QQ 邮箱 → 写入 QQ 头像 URL（系统自动，标记 auto=1）
 *   3) 否则 → 站点默认头像（父逻辑兜底）
 *   邮箱由 QQ 改为非 QQ 且头像是系统自动设置时，自动清除回退默认。
 */

// QQ 头像接口允许的 spec 尺寸（腾讯官方取值，选最接近请求的合法值）
if (!defined('ZIBLL_CHILD_QQ_AVATAR_SIZES')) {
    define('ZIBLL_CHILD_QQ_AVATAR_SIZES', serialize(array(40, 100, 140, 200)));
}

/**
 * 从邮箱提取 QQ 号。仅当为合法 QQ 邮箱（5-13 位数字 @qq.com）时返回。
 * @param string $email
 * @return string|false 成功返回 QQ 号，否则 false
 */
function zibll_child_get_qq_from_email($email)
{
    if (empty($email) || !is_email($email)) {
        return false;
    }
    if (preg_match('/^(\d{5,13})@qq\.com$/i', trim($email), $m)) {
        return $m[1];
    }
    return false;
}

/**
 * 生成 QQ 头像地址（相对协议 //，父 zib_get_avatar 会再归一化协议以
 * 自适应站点 HTTPS/HTTP）。spec 选用腾讯官方合法尺寸。
 */
function zibll_child_qq_avatar_url($qq, $size = 100)
{
    $valid = unserialize(ZIBLL_CHILD_QQ_AVATAR_SIZES);
    $spec  = in_array((int) $size, $valid, true) ? (int) $size : 100;
    return '//q2.qlogo.cn/headimg_dl?dst_uin=' . rawurlencode($qq) . '&spec=' . $spec;
}

/**
 * 同步单个用户的 QQ 头像到 custom_avatar meta。
 * 仅在用户【未主动设置】自定义头像时写入，避免覆盖用户上传/绑定的头像；
 * 邮箱由 QQ 改为非 QQ 时，若当前头像是系统自动设置的则清除、回退默认。
 */
function zibll_child_sync_qq_avatar($user_id)
{
    $user_id = (int) $user_id;
    if (!$user_id) {
        return;
    }

    $auto     = (bool) get_user_meta($user_id, 'zibll_child_qq_avatar_auto', true);
    $existing = zib_get_user_meta($user_id, 'custom_avatar', true);

    // 用户已主动设置头像（非系统自动）→ 不碰
    if (!empty($existing) && !$auto) {
        return;
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return;
    }

    $qq = zibll_child_get_qq_from_email($user->user_email);
    if ($qq) {
        zib_update_user_meta($user_id, 'custom_avatar', zibll_child_qq_avatar_url($qq, 100));
        update_user_meta($user_id, 'zibll_child_qq_avatar_auto', 1);
        wp_cache_delete($user_id, 'user_avatar'); // 刷新父主题头像缓存
        return;
    }

    // 非 QQ 邮箱：若此前是系统自动设置的 QQ 头像，则清除并回退默认
    if ($auto) {
        update_user_meta($user_id, 'zibll_child_qq_avatar_auto', 0);
        if (!empty($existing)) {
            zib_update_user_meta($user_id, 'custom_avatar', '');
        }
        wp_cache_delete($user_id, 'user_avatar');
    }
    // 非 QQ 且 auto 已为 0：无任何变更，无需写入
}

// 触发时机：新用户注册、资料更新（含邮箱变更）、登录时同步。
// 是否启用由子主题设置页「QQ 邮箱自动头像」开关控制（默认开启，见 theme-options.php）。
if (zibll_child_module_enabled('enable_qq_avatar', true)) {
    add_action('user_register', 'zibll_child_sync_qq_avatar');
    add_action('profile_update', 'zibll_child_sync_qq_avatar');

    // 注意：wp_login 回调签名为 ($user_login, $user)，首参是【用户名】而非用户 ID，
    // 故需经适配函数取出 $user->ID 再交给统一同步函数（直接挂会拿到字符串→int 0→跳过）。
    add_action('wp_login', 'zibll_child_sync_qq_avatar_on_login', 10, 2);
}
function zibll_child_sync_qq_avatar_on_login($user_login, $user)
{
    if ($user instanceof WP_User) {
        zibll_child_sync_qq_avatar($user->ID);
    }
}

/**
 * 用户在前台/后台保存自定义头像后：
 *   - 若清空了头像（custom_avatar 为空）且是 QQ 邮箱 → 自动补回 QQ 头像；
 *   - 若设置了头像（上传/绑定的图片） → 取消系统自动标记，避免日后被覆盖。
 * 父主题在保存自定义头像时会触发 user_save_custom_avatar（见 zib-theme.php:602）。
 */
if (zibll_child_module_enabled('enable_qq_avatar', true)) {
    add_action('user_save_custom_avatar', 'zibll_child_on_save_custom_avatar', 20);
}
function zibll_child_on_save_custom_avatar($user_id)
{
    $existing = zib_get_user_meta($user_id, 'custom_avatar', true);
    if (empty($existing)) {
        // 用户清空头像 → QQ 邮箱则自动补回
        zibll_child_sync_qq_avatar($user_id);
    } else {
        // 用户主动设置了头像 → 取消系统自动标记
        update_user_meta($user_id, 'zibll_child_qq_avatar_auto', 0);
    }
}

/* ── 老用户一次性自动同步 ── */

/**
 * 把全部 QQ 邮箱用户（仅未主动设头像者）的头像设为 QQ 头像。
 * 用户已上传自己的头像不会被覆盖；邮箱从 QQ 改为非 QQ 的会自动回退默认。
 */
function zibll_child_sync_all_qq_avatars()
{
    // 遍历全部用户，用邮箱规则过滤 QQ 邮箱（不依赖模糊 SQL，避免漏判）。
    $users = get_users(array('fields' => array('ID', 'user_email'), 'number' => 0));

    foreach ($users as $u) {
        if (!zibll_child_get_qq_from_email($u->user_email)) {
            continue;
        }
        $auto     = (bool) get_user_meta($u->ID, 'zibll_child_qq_avatar_auto', true);
        $existing = zib_get_user_meta($u->ID, 'custom_avatar', true);
        if (!empty($existing) && !$auto) {
            continue; // 用户已主动设置头像，保留不覆盖
        }
        zibll_child_sync_qq_avatar($u->ID);
    }
}

/**
 * 启用（或更新）子主题后自动同步一次老用户头像：
 *   用选项标记 zibll_child_qq_avatar_done 确保只跑一次，之后由上方三个钩子
 *   （注册 / 改资料 / 登录）实时同步新用户。无需任何手动操作。
 *   如需重新全量同步，删除该选项即可（如 delete_option('zibll_child_qq_avatar_done')）。
 *   注：万级以上的大站，可将下方 get_users 改为分批（offset/limit）调用。
 */
if (zibll_child_module_enabled('enable_qq_avatar', true)) {
    add_action('init', 'zibll_child_qq_avatar_auto_once');
}
function zibll_child_qq_avatar_auto_once()
{
    if (get_option('zibll_child_qq_avatar_done')) {
        return;
    }
    zibll_child_sync_all_qq_avatars();
    update_option('zibll_child_qq_avatar_done', 1);
}
