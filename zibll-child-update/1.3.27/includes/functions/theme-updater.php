<?php
/**
 * 子比子主题 - 在线【增量】更新器
 * ─────────────────────────────────────────────────────────────
 * 背景：父主题 zibll 的更新通道是其私有、连官方服务器的加密机制，
 *       子主题 zibll-child 无法复用，也不能改。因此这里自建一套更新。
 *
 * 与 WP 原生主题更新的本质区别（也是本模块的核心价值）：
 *   WP 原生更新 = 删除整个旧主题目录 → 解压新 zip（整包覆盖），
 *   会丢掉你在 style.css / func.php 里写的自定义。
 *   本模块走【增量更新】：只下载 metadata 清单内的「核心代码文件」并覆盖，
 *   style.css / func.php 等自定义文件【不在清单内、且被强制跳过】，永不丢失。
 *   更新成功后额外【同步清理】主题目录内「不在当前版本清单中」的孤儿文件（被移除模块 / 旧版残留），
 *   多重护栏确保绝不误删受保护文件、用户自定义文件及非代码类文件（详见下方同步删除段）。
 *   因此【不】向 WP 的 update_themes transient 注入 package，避免触发原生整包下载。
 *
 * 更新服务器侧需提供（详见项目根 build-update.php 打包脚本）：
 *   https://你的域名/路径/update.json
 *   {
 *     "version": "1.2.0",
 *     "requires_wp": "5.0",
 *     "requires_php": "7.0",
 *     "homepage": "https://...",
 *     "changelog": "本次更新说明（支持换行）",
 *     "files": [
 *       {"path":"includes/functions/functions.php",
 *        "url":"https://.../1.2.0/includes/functions/functions.php",
 *        "hash":"该文件的 sha256（十六进制，小写）"},
 *       ...
 *     ]
 *   }
 *   注意：files 只列【需要更新的核心代码文件】，不要包含 style.css / func.php。
 *
 * 配置：修改下方 ZIBLL_CHILD_UPDATE_API 常量指向你的 update.json 地址即可。
 *
 * @package zibll-child
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─── 屏蔽 WordPress 原生主题更新对子主题的误报 ──────────────
// 父主题 zibll 的私有更新机制会把自身更新（如 V9.0）注入 update_themes transient，
// WordPress 可能错误地把该更新关联到子主题卡片，显示一个"当前 V1.1 → 可更新到 V9.0"
// 的误导提示；若点它会触发整包下载、覆盖破坏子主题（style.css/func.php 等自定义全丢）。
// 子主题真正的更新走本模块自建的增量通道（见下方 admin_notices 的「立即增量更新」按钮），
// 因此这里强制把 zibll-child 从 WP 原生更新响应中剔除（读取与写入前双拦截）。
add_filter('site_transient_update_themes', 'zibll_child_block_native_update');
add_filter('pre_set_site_transient_update_themes', 'zibll_child_block_native_update');
function zibll_child_block_native_update($value)
{
    if (empty($value)) {
        return $value;
    }
    $slug = 'zibll-child';
    if (is_object($value) && isset($value->response[$slug])) {
        unset($value->response[$slug]);
    } elseif (is_array($value) && isset($value['response'][$slug])) {
        unset($value['response'][$slug]);
    }
    return $value;
}

// ─── 自清理：安装后一次性清扫主题目录内遗留的 *.bak 备份 ──────────
// 旧版更新器会在主题目录堆积「文件名.bak-时间戳」回滚备份（旧源码副本，有泄露风险且占空间）；
// 即便新版已改为固定名 .bak 并在更新成功后清理，历史 / 过渡更新产生的 *.bak-* 仍可能残留。
// 这里在【每次主题版本变更后】，于下次请求时递归删除主题目录内所有 *.bak / *.bak-时间戳，兜底清零，
// 使任何站点升级到含本逻辑的版本后都能自动自愈，不再依赖手动清理。
add_action('init', 'zibll_child_maybe_cleanup_bak_leftovers');
function zibll_child_maybe_cleanup_bak_leftovers()
{
    if (!function_exists('zibll_child_get_version')) {
        return;
    }
    $done = get_option('zibll_child_bak_cleanup_version');
    $cur  = zibll_child_get_version();
    if ($done === $cur) {
        return;
    }
    $dir = get_stylesheet_directory();
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile() && preg_match('/\.bak(-\d+)?$/', $f->getFilename())) {
                @unlink($f->getPathname());
            }
        }
    }
    update_option('zibll_child_bak_cleanup_version', $cur);
}


// ─── 配置 ──────────────────────────────────────────────────────
// 更新源（全面明文，已关闭 base64 混淆下载源）。
// 下载源域名直接明文存储；改域名时替换下方地址即可。
if (!function_exists('zibll_child_update_base_plain')) {
    function zibll_child_update_base_plain()
    {
        return 'https://download.achen-mcsever.top/zibll-child-update';
    }
}

// ─── 第二下载源：GitHub 公开镜像（双源并存，版本较新者优先） ──────────────
// 作用：与主源 download.achen-mcsever.top 并存，谁先发版（版本号较新）用户就从谁更新，互为冗余。
// 约定 / 注意：
//   1) 镜像为【公开仓库】：任何人（含分发出去的用户站）无需令牌即可从 raw.githubusercontent.com 拉取更新。
//      代价：子主题源代码随之公开。如日后改回私有，填下方 ZIBLL_CHILD_GITHUB_TOKEN 即自动切回带鉴权地址。
//   2) 仓库内目录结构须与宝塔源一致：根下 zibll-child-update/update.json + zibll-child-update/<VERSION>/...
//   3) 发布时序铁律对双源同样适用：任一版本发布时须等【宝塔 + GitHub 两处】都传完，再让用户点更新，否则另一源回退时会 404。
if (!defined('ZIBLL_CHILD_GITHUB_REPO')) {
    define('ZIBLL_CHILD_GITHUB_REPO', 'yuananchen44-prog/zibll-child-update'); // 私有镜像仓库 owner/repo（请自建并改成你的）
}
if (!defined('ZIBLL_CHILD_GITHUB_REF')) {
    define('ZIBLL_CHILD_GITHUB_REF', 'main'); // 分支或标签（也可用 v1.3.26 这类标签固定）
}
// GitHub 访问令牌（可选）。公开仓库下拉取更新【无需令牌】；仅当把仓库改回私有时才需填（Personal Access Token，repo 读取权限）。
// 建议放 wp-config.php：define('ZIBLL_CHILD_GITHUB_TOKEN', 'ghp_xxx'); 留空 = 公开、无鉴权。
if (!defined('ZIBLL_CHILD_GITHUB_TOKEN')) {
    define('ZIBLL_CHILD_GITHUB_TOKEN', ''); // 公开仓库留空即可
}
// 公开仓库：直接走 raw.githubusercontent.com，不带令牌也能拉（任何人可读）。
// 若填了令牌（私有场景），会自动以 basic-auth 形式嵌入 URL；令牌不出现在前台，报错日志只记录 HTTP 状态码与 curl 错误。
if (!defined('ZIBLL_CHILD_UPDATE_GITHUB_BASE')) {
    $gh_token = ZIBLL_CHILD_GITHUB_TOKEN !== '' ? (ZIBLL_CHILD_GITHUB_TOKEN . '@') : '';
    define(
        'ZIBLL_CHILD_UPDATE_GITHUB_BASE',
        'https://' . $gh_token . 'raw.githubusercontent.com/' . ZIBLL_CHILD_GITHUB_REPO . '/' . ZIBLL_CHILD_GITHUB_REF . '/zibll-child-update'
    );
}
if (!defined('ZIBLL_CHILD_UPDATE_API_GITHUB')) {
    define('ZIBLL_CHILD_UPDATE_API_GITHUB', rtrim(ZIBLL_CHILD_UPDATE_GITHUB_BASE, '/') . '/update.json');
}
// 单文件下载（双协议兜底）：先 WP HTTP（放宽 sslverify 兼容证书不全的主机），
// 失败再用 cURL 直连兜底。返回 array(内容字符串|false, 错误描述)。
if (!function_exists('zibll_child_fetch_url')) {
    function zibll_child_fetch_url($url)
    {
        $resp = wp_remote_get($url, array('timeout' => 30, 'sslverify' => false));
        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            return array(wp_remote_retrieve_body($resp), '');
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_TIMEOUT         => 30,
                CURLOPT_SSL_VERIFYPEER  => false,
                CURLOPT_SSL_VERIFYHOST  => false,
                CURLOPT_FOLLOWLOCATION  => true,
            ));
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($body !== false && (int) $code === 200) {
                return array($body, '');
            }
            if ($err !== '') {
                return array(false, 'cURL错误: ' . $err);
            }
            return array(false, 'HTTP ' . $code);
        }
        if (is_wp_error($resp)) {
            return array(false, $resp->get_error_message());
        }
        return array(false, 'HTTP ' . wp_remote_retrieve_response_code($resp));
    }
}
if (!defined('ZIBLL_CHILD_UPDATE_API')) {
    define('ZIBLL_CHILD_UPDATE_API', zibll_child_update_base_plain() . '/update.json');
}
// 本地当前版本号存放的 option 键（不依赖 style.css 头，因其属用户可自定义、不进更新清单）
if (!defined('ZIBLL_CHILD_VERSION_OPTION')) {
    define('ZIBLL_CHILD_VERSION_OPTION', 'zibll_child_version');
}
// 受保护的文件名：绝不覆盖（强制跳过），用于守护用户自定义
if (!defined('ZIBLL_CHILD_PROTECTED_FILES')) {
    define('ZIBLL_CHILD_PROTECTED_FILES', serialize(array('style.css', 'func.php')));
}

/* ── 版本号读取（首次从 style.css 头初始化到 option） ── */
function zibll_child_get_version()
{
    $v = get_option(ZIBLL_CHILD_VERSION_OPTION);
    if ($v) {
        return $v;
    }
    $theme = wp_get_theme(get_stylesheet());
    $v = $theme->get('Version') ?: '1.0.0';
    update_option(ZIBLL_CHILD_VERSION_OPTION, $v);
    return $v;
}

/* ── 拉取远程 metadata（带 1 小时缓存，失败返回 false） ──
 * 双源并存：同时探测主源（宝塔）与 GitHub 镜像，取【版本号较新】的一方作为更新依据
 * （版本相同则优先主源）。哪边先发版，用户就先从哪边更新。 */
function zibll_child_fetch_update_meta()
{
    $cache = get_transient('zibll_child_update_meta');
    if ($cache !== false) {
        return $cache;
    }

    $primary = zibll_child_fetch_update_meta_from(ZIBLL_CHILD_UPDATE_API);
    $github  = zibll_child_fetch_update_meta_from(ZIBLL_CHILD_UPDATE_API_GITHUB);

    $meta = false;
    if ($primary && $github) {
        // 两源都可用：较新者胜出；打标 _source 供逐文件下载沿用同一源
        if (version_compare($github['version'], $primary['version'], '>')) {
            $github['_source'] = 'github';
            $meta = $github;
        } else {
            $primary['_source'] = 'plain';
            $meta = $primary;
        }
    } elseif ($primary) {
        $primary['_source'] = 'plain';
        $meta = $primary;
    } elseif ($github) {
        $github['_source'] = 'github';
        $meta = $github;
    }

    if (!$meta) {
        return false;
    }

    set_transient('zibll_child_update_meta', $meta, HOUR_IN_SECONDS);
    return $meta;
}

/* 从指定 update.json 地址拉取并校验元数据（不缓存）；成功返回数组，失败返回 false */
function zibll_child_fetch_update_meta_from($url)
{
    $resp = wp_remote_get($url, array(
        'timeout'  => 15,
        'sslverify' => false,
    ));
    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($data) || empty($data['version']) || empty($data['files'])) {
        return false;
    }

    return $data;
}

/* ── 是否有可用更新（远程版本 > 本地版本） ── */
function zibll_child_has_update()
{
    $meta = zibll_child_fetch_update_meta();
    if (!$meta) {
        return false;
    }
    return version_compare($meta['version'], zibll_child_get_version(), '>');
}

/* ── 维护模式（写/删站点根目录 .maintenance） ── */
function zibll_child_set_maintenance($on)
{
    $file = ABSPATH . '.maintenance';
    if ($on) {
        // 5 分钟内视为维护中（避免异常中断后一直挂维护页）
        @file_put_contents($file, '<?php $upgrading = ' . (time() + 300) . '; ?>');
    } else {
        @unlink($file);
    }
}

/* ── 后台提示：有更新时显示通知 + 「立即更新」按钮 ── */
add_action('admin_notices', 'zibll_child_update_notice');
function zibll_child_update_notice()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!zibll_child_has_update()) {
        return;
    }
    $meta = zibll_child_fetch_update_meta();

    $update_url = wp_nonce_url(
        admin_url('index.php?zibll_child_do_update=1'),
        'zibll_child_update',
        'zibll_child_update_nonce'
    );
    $log_url = admin_url('index.php?zibll_child_update_changelog=1');

    echo '<div class="notice notice-warning is-dismissible"><p>'
        . '<strong>子比子主题（zibll-child）有新版本：v' . esc_html($meta['version']) . '</strong>'
        . '（当前 v' . esc_html(zibll_child_get_version()) . '）。'
        . ' 更新为【增量模式】，只覆盖核心代码文件，不会动你的 style.css / func.php 自定义。'
        . ' <span style="color:#666;">（父主题 zibll 在「外观→主题」里的原生更新提示是其自身机制，与本子主题更新无关，请勿混淆。）</span>'
        . ' <a class="button button-primary" href="' . esc_url($update_url) . '">立即增量更新</a>'
        . ' <a class="button" href="' . esc_url($log_url) . '">查看更新说明</a>'
        . '</p></div>';
}

/* ── 查看更新说明 ── */
add_action('admin_notices', 'zibll_child_update_changelog_notice');
function zibll_child_update_changelog_notice()
{
    if (empty($_GET['zibll_child_update_changelog']) || !current_user_can('manage_options')) {
        return;
    }
    $meta = zibll_child_fetch_update_meta();
    if (!$meta) {
        return;
    }
    echo '<div class="notice notice-info is-dismissible"><p><strong>更新说明（v'
        . esc_html($meta['version']) . '）：</strong><br>'
        . nl2br(esc_html(isset($meta['changelog']) ? $meta['changelog'] : '无')) . '</p></div>';
}

/* ── 处理「立即更新」请求：校验权限与 nonce ── */
add_action('admin_init', 'zibll_child_run_update');
function zibll_child_run_update()
{
    if (empty($_GET['zibll_child_do_update']) || !current_user_can('manage_options')) {
        return;
    }
    check_admin_referer('zibll_child_update', 'zibll_child_update_nonce');

    $result = zibll_child_do_incremental_update();
    set_transient('zibll_child_update_result', $result, 60);
    // 更新入口已统一到「子比子主题设置 → 主题更新」面板，故跳回该面板（#主题更新 尝试停留原 section）
    wp_redirect(admin_url('admin.php?page=zibll_child_options#主题更新'));
    exit;
}

/* ── 更新结果通知 ── */
add_action('admin_notices', 'zibll_child_update_result_notice');
function zibll_child_update_result_notice()
{
    $r = get_transient('zibll_child_update_result');
    if (!$r) {
        return;
    }
    delete_transient('zibll_child_update_result');

    $cls = !empty($r['ok']) ? 'notice-success' : 'notice-error';
    echo '<div class="notice ' . $cls . ' is-dismissible"><p>' . esc_html($r['msg']) . '</p>';
    if (!empty($r['errors'])) {
        echo '<ul style="margin:0"><li>' . implode('</li><li>', array_map('esc_html', $r['errors'])) . '</li></ul>';
    }
    echo '</div>';
}

/**
 * 执行增量更新：逐个下载 metadata.files 清单内的文件，
 * 校验 sha256 → 写入主题目录（仅清单内文件）。
 * @return array ['ok'=>bool, 'updated'=>int, 'errors'=>array, 'msg'=>string]
 */
function zibll_child_do_incremental_update()
{
    $meta = zibll_child_fetch_update_meta();
    if (!$meta || !zibll_child_has_update()) {
        return array('ok' => false, 'updated' => 0, 'errors' => array(), 'msg' => '没有可用的更新或获取元数据失败。');
    }

    $theme_dir = get_stylesheet_directory(); // zibll-child 真实目录
    $protected = unserialize(ZIBLL_CHILD_PROTECTED_FILES);
    $updated   = 0;
    $deleted   = 0; // 本次同步清理的孤儿文件数
    $errors    = array();
    $bak_files = array(); // 本次更新生成的回滚备份，整体成功后再清理

    zibll_child_set_maintenance(true);

    foreach ((array) $meta['files'] as $f) {
        if (empty($f['path']) || empty($f['url'])) {
            $errors[] = '清单条目缺少 path/url，已跳过';
            continue;
        }
        $rel  = ltrim($f['path'], '/\\');
        $hash = isset($f['hash']) ? strtolower(trim($f['hash'])) : '';

        // 守护用户自定义：清单里若出现 style.css/func.php，强制跳过
        if (in_array(basename($rel), $protected, true)) {
            $errors[] = '已跳过受保护文件：' . $rel . '（你的自定义不会被覆盖）';
            continue;
        }

        // 下载：全面明文（已关闭混淆下载源，仅用明文源）。
        // 绝对地址（旧包）直接用；相对路径（新包）拼接明文 BASE 下载。
        $abs = preg_match('#^https?://#i', $f['url']);
        $body = false;
        if ($abs) {
            list($body, $err) = zibll_child_fetch_url($f['url']);
            if ($body === false) {
                $errors[] = '下载失败：' . $rel . ' [' . $err . '] ' . $f['url'];
                continue;
            }
        } else {
            // 沿用「版本较新」胜出源的下载地址优先；另一源作单文件失败时的兜底（同源同版本内容一致，安全）
            $winner = isset($meta['_source']) ? $meta['_source'] : 'plain';
            $candidate_bases = array();
            if ($winner === 'github') {
                $candidate_bases['github'] = ZIBLL_CHILD_UPDATE_GITHUB_BASE;
                $candidate_bases['plain']  = zibll_child_update_base_plain();
            } else {
                $candidate_bases['plain']  = zibll_child_update_base_plain();
                $candidate_bases['github'] = ZIBLL_CHILD_UPDATE_GITHUB_BASE;
            }
            $fetch_errs = array();
            foreach ($candidate_bases as $chan => $base) {
                $try_url = rtrim($base, '/') . '/' . ltrim($f['url'], '/');
                list($b, $err) = zibll_child_fetch_url($try_url);
                if ($b !== false) {
                    $body = $b;
                    break;
                }
                $fetch_errs[] = $chan . ': ' . $err;
            }
            if ($body === false) {
                $errors[] = '下载失败：' . $rel . ' [' . implode(' | ', $fetch_errs) . ']';
                continue;
            }
        }
        // 空内容但能通过下方 sha256 校验的文件（例如合法的空占位文件）视为有效；
        // 仅在下载失败（$body === false，上方已处理）时才报错，避免把「空文件」误判为更新失败。
        // 校验 sha256（提供时才校验）
        if ($hash !== '') {
            $real = hash('sha256', $body);
            if ($real !== $hash) {
                $errors[] = '校验失败（sha256 不匹配）：' . $rel;
                continue;
            }
        }

        $target     = $theme_dir . '/' . $rel;
        $target_dir = dirname($target);
        if (!wp_mkdir_p($target_dir)) {
            $errors[] = '目录不可写：' . $target_dir;
            continue;
        }

        // 备份原文件，便于回滚（单一固定名，避免多次更新堆积 *.bak-时间戳）
        if (file_exists($target)) {
            @unlink($target . '.bak');
            @copy($target, $target . '.bak');
            $bak_files[] = $target . '.bak';
        }

        // 先写临时文件再原子替换，降低半截写入风险
        $tmp = $target_dir . '/.' . basename($rel) . '.tmp-' . time();
        if (file_put_contents($tmp, $body) === false) {
            $errors[] = '写入临时文件失败：' . $rel;
            continue;
        }
        if (file_exists($target)) {
            @unlink($target);
        }
        if (!rename($tmp, $target)) {
            @unlink($tmp);
            $errors[] = '替换失败：' . $rel;
            continue;
        }
        $updated++;
    }

    // 仅当全部成功才更新本地版本号并刷新缓存；并清理回滚备份
    // （增量更新可从下载源重新拉取覆盖，本地 .bak 既占空间又有源码泄露风险，无需留存）
    if (empty($errors)) {
        update_option(ZIBLL_CHILD_VERSION_OPTION, $meta['version']);
        delete_transient('zibll_child_update_meta');
        foreach ($bak_files as $b) {
            @unlink($b);
        }

        // ── 同步删除：清理「服务器存在、但当前版本清单不再包含」的孤儿文件
        //    （被移除模块 / 旧版残留，例如曾经删除的 includes/hoarfall-media-offload/ 整套文件）
        //    安全护栏（防止误删正常文件、保证子主题核心功能不受影响）：
        //      · 仅在本次更新零错误后执行；
        //      · 只删 ALLOWED 扩展名文件，绝不碰 .html/.txt/.md/.pdf 等用户可能放置的文件；
        //      · 跳过受保护文件(style.css/func.php)、点文件、*.bak/*.tmp/*.swp/*.orig 备份；
        //      · 跳过 build 脚本本就排除的目录(.git/node_modules/license-keys/__pycache__/.workbuddy)；
        //      · 真实路径必须仍在主题目录内(realpath 前缀校验，防符号链接 / 遍历逃逸)；
        //      · 不删目录本身；孤儿数量 > 清单文件数时判定清单可能损坏，放弃删除(宁可留残留)。
        $allowed_ext = array('php', 'css', 'js', 'json', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'woff', 'woff2', 'ttf', 'eot');
        $skip_dirs   = array('.git', 'node_modules', 'license-keys', '__pycache__', '.workbuddy');
        $manifest_paths = array();
        foreach ((array) $meta['files'] as $mf) {
            if (!empty($mf['path'])) {
                $manifest_paths[ ltrim($mf['path'], '/\\') ] = true;
            }
        }
        $theme_real = realpath($theme_dir);
        if ($theme_real !== false && !empty($manifest_paths)) {
            $theme_real_norm = rtrim(str_replace('\\', '/', $theme_real), '/');
            $orphans = array();
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($theme_real, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $of) {
                if (!$of->isFile()) {
                    continue;
                }
                $oreal = realpath($of->getPathname());
                if ($oreal === false) {
                    continue;
                }
                $oreal_norm = str_replace('\\', '/', $oreal);
                if (strpos($oreal_norm, $theme_real_norm . '/') !== 0) {
                    continue; // 不在主题目录内（防逃逸）
                }
                $rel  = substr($oreal_norm, strlen($theme_real_norm) + 1);
                $base = $of->getFilename();
                if (in_array($base, $protected, true)) {
                    continue; // 受保护文件
                }
                if (isset($base[0]) && $base[0] === '.') {
                    continue; // 点文件
                }
                if (preg_match('/\.(bak|tmp|swp|orig)(-\d+)?$/', $base)) {
                    continue; // 备份残留
                }
                $rel_parts = explode('/', $rel);
                $skip = false;
                foreach ($rel_parts as $rp) {
                    if (in_array($rp, $skip_dirs, true)) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) {
                    continue; // build 排除目录
                }
                $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext, true)) {
                    continue; // 非代码类扩展名不碰
                }
                if (isset($manifest_paths[ ltrim($rel, '/\\') ])) {
                    continue; // 清单内文件不动
                }
                $orphans[] = $oreal;
            }
            // 安全校验：仅当「清单自身合法有效」才删，防止清单损坏/为空时误删全部。
            // 注意：不再用「孤儿数 <= 清单数」判断——老站点文件本就多于精简后的新清单，
            // 那样会误判为清单损坏而放弃清理（正是此前同步删除不生效的根因）。
            $core_required = array('includes/index.php', 'includes/functions/theme-updater.php');
            $core_ok = true;
            foreach ($core_required as $cr) {
                if (!isset($manifest_paths[ ltrim($cr, '/\\') ])) {
                    $core_ok = false;
                    break;
                }
            }
            if (!empty($orphans) && $core_ok && count($manifest_paths) >= 15) {
                foreach ($orphans as $op) {
                    if (@unlink($op)) {
                        $deleted++;
                    }
                }
            }
        }
    }

    zibll_child_set_maintenance(false);

    return array(
        'ok'      => empty($errors),
        'updated' => $updated,
        'errors'  => $errors,
        'msg'     => empty($errors)
            ? ('已增量更新 ' . $updated . ' 个文件' . ($deleted > 0 ? ('，并同步清理 ' . $deleted . ' 个旧版残留文件') : '') . '，当前版本 v' . $meta['version'])
            : ('更新完成，但存在 ' . count($errors) . ' 个问题，请查看下方明细'),
    );
}

// ─── 独立菜单页已移除（2026-07-12）───────────────
// 子主题更新入口统一收敛到「子比子主题设置 → 主题更新」面板内（见 theme-options.php），
// 不再单独建「设置 → 子主题更新」菜单，避免重复入口、与父主题菜单结构混淆。
// 「立即增量更新」执行后跳回面板页（zibll_child_options#主题更新），由上方 zibll_child_run_update 处理。
