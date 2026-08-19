<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default settings.
 */
function zibll_additional_demo01_default_settings()
{
    return array(
        'enabled'               => 1,
        'include_default_group' => 1,
        'default_group_label'   => '默认',
        'smilies_root'          => trailingslashit(get_template_directory()) . 'img/smilies',
        'smilies_base_url'      => trailingslashit(get_template_directory_uri()) . 'img/smilies',
        'allowed_exts'          => 'gif,png,jpg,jpeg,webp',
        'max_per_group'         => 200,
        'panel_width'           => 360,
        'emoji_groups'          => array(),
        'group_lines'           => '',
    );
}

/**
 * Get all settings merged with defaults.
 */
function zibll_additional_demo01_get_settings()
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $stored = get_option(ZIBLL_COMMENT_EMOJI_OPTIONS_KEY, array());
    if (!is_array($stored)) {
        $stored = array();
    }

    $settings = wp_parse_args($stored, zibll_additional_demo01_default_settings());
    $settings['enabled'] = !empty($settings['enabled']) ? 1 : 0;
    $settings['include_default_group'] = !empty($settings['include_default_group']) ? 1 : 0;
    $settings['default_group_label'] = sanitize_text_field((string) $settings['default_group_label']);
    $settings['smilies_root'] = zibll_additional_demo01_normalize_path($settings['smilies_root']);
    $settings['smilies_base_url'] = untrailingslashit((string) $settings['smilies_base_url']);
    $settings['allowed_exts'] = (string) $settings['allowed_exts'];
    $settings['max_per_group'] = max(1, min(500, absint($settings['max_per_group'])));
    $settings['panel_width'] = max(220, min(720, absint($settings['panel_width'])));
    $settings['emoji_groups'] = is_array($settings['emoji_groups']) ? $settings['emoji_groups'] : array();
    $settings['group_lines'] = (string) $settings['group_lines'];

    return $settings;
}

/**
 * Get one setting.
 */
function zibll_additional_demo01_get_setting($key, $default = false)
{
    $settings = zibll_additional_demo01_get_settings();
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Is plugin enabled.
 */
function zibll_additional_demo01_is_enabled()
{
    return (bool) zibll_additional_demo01_get_setting('enabled', 0);
}

function zibll_additional_demo01_normalize_path($path)
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    $path = str_replace('\\', '/', $path);
    return untrailingslashit($path);
}

function zibll_additional_demo01_get_allowed_exts($exts_csv)
{
    $exts = array();
    foreach (explode(',', strtolower((string) $exts_csv)) as $ext) {
        $ext = preg_replace('/[^a-z0-9]/', '', trim($ext));
        if ($ext !== '') {
            $exts[] = $ext;
        }
    }
    $exts = array_values(array_unique($exts));
    return !empty($exts) ? $exts : array('gif', 'png');
}

/**
 * Get usable storage root and base url.
 * If configured directory is not usable, auto fallback to uploads/zibll-comment-emoji/smilies.
 */
function zibll_additional_demo01_get_storage($need_writable = false, $auto_update_option = true)
{
    $settings = zibll_additional_demo01_get_settings();
    $root = zibll_additional_demo01_normalize_path($settings['smilies_root']);
    $url = untrailingslashit((string) $settings['smilies_base_url']);
    $usable = true;

    if ($root === '' || $url === '') {
        $usable = false;
    } else {
        if (is_dir($root)) {
            if (!is_readable($root)) {
                $usable = false;
            }
            if ($need_writable && !is_writable($root)) {
                $usable = false;
            }
        } else {
            $parent = dirname($root);
            if (!is_dir($parent) || !is_writable($parent) || !wp_mkdir_p($root)) {
                $usable = false;
            }
        }
    }

    if ($usable) {
        return array(
            'root' => $root,
            'url'  => $url,
        );
    }

    $uploads = wp_upload_dir();
    $fallback_root = zibll_additional_demo01_normalize_path($uploads['basedir'] . '/zibll-comment-emoji/smilies');
    $fallback_url = untrailingslashit($uploads['baseurl'] . '/zibll-comment-emoji/smilies');

    if (!is_dir($fallback_root)) {
        wp_mkdir_p($fallback_root);
    }

    if ($auto_update_option) {
        $options = get_option(ZIBLL_COMMENT_EMOJI_OPTIONS_KEY, array());
        if (!is_array($options)) {
            $options = array();
        }
        $options['smilies_root'] = $fallback_root;
        $options['smilies_base_url'] = $fallback_url;
        update_option(ZIBLL_COMMENT_EMOJI_OPTIONS_KEY, $options);
    }

    return array(
        'root' => $fallback_root,
        'url'  => $fallback_url,
    );
}

function zibll_additional_demo01_parse_group_lines($group_lines)
{
    $groups = array();
    $lines = preg_split('/\r\n|\r|\n/', (string) $group_lines);
    if (!$lines) {
        return $groups;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        $parts = explode('|', $line, 2);
        $dir = trim($parts[0]);
        if ($dir === '') {
            continue;
        }

        if (strpos($dir, '..') !== false || strpos($dir, '/') !== false || strpos($dir, '\\') !== false) {
            continue;
        }

        $title = isset($parts[1]) ? trim($parts[1]) : $dir;
        if ($title === '') {
            $title = $dir;
        }

        $groups[] = array(
            'dir'   => $dir,
            'title' => $title,
        );
    }

    return $groups;
}

function zibll_additional_demo01_clean_group_dir($dir)
{
    $dir = trim((string) $dir);
    if ($dir === '') {
        return '';
    }
    if (strpos($dir, '..') !== false || strpos($dir, '/') !== false || strpos($dir, '\\') !== false) {
        return '';
    }
    return $dir;
}

/**
 * Get the saved visual sort order.
 */
function zibll_additional_demo01_get_sort_order()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = array(
        'groups' => array(),
        'items'  => array(),
    );

    $stored = get_option(ZIBLL_COMMENT_EMOJI_ORDER_KEY, array());
    if (!is_array($stored)) {
        return $cache;
    }

    $seen_groups = array();
    $raw_groups = isset($stored['groups']) && is_array($stored['groups']) ? $stored['groups'] : array();
    foreach ($raw_groups as $raw_dir) {
        $dir = zibll_additional_demo01_clean_group_dir((string) $raw_dir);
        if ($dir === '' || isset($seen_groups[$dir])) {
            continue;
        }

        $seen_groups[$dir] = true;
        $cache['groups'][] = $dir;
    }

    $raw_items = isset($stored['items']) && is_array($stored['items']) ? $stored['items'] : array();
    foreach ($raw_items as $raw_dir => $raw_files) {
        $dir = zibll_additional_demo01_clean_group_dir((string) $raw_dir);
        if ($dir === '' || !is_array($raw_files)) {
            continue;
        }

        $seen_files = array();
        foreach ($raw_files as $raw_file) {
            $file_name = trim((string) $raw_file);
            if (
                $file_name === ''
                || $file_name === '.'
                || $file_name === '..'
                || strpos($file_name, '/') !== false
                || strpos($file_name, '\\') !== false
                || isset($seen_files[$file_name])
            ) {
                continue;
            }

            $seen_files[$file_name] = true;
            $cache['items'][$dir][] = $file_name;
        }
    }

    return $cache;
}

/**
 * Auto detect child folders under root.
 */
function zibll_additional_demo01_get_detected_folder_groups()
{
    $storage = zibll_additional_demo01_get_storage(false, true);
    $root = $storage['root'];
    $groups = array();

    if (!is_dir($root)) {
        return $groups;
    }

    $entries = scandir($root);
    if (!$entries) {
        return $groups;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $dir_path = $root . '/' . $entry;
        if (!is_dir($dir_path)) {
            continue;
        }

        $groups[] = array(
            'dir'   => (string) $entry,
            'title' => (string) $entry,
        );
    }

    usort($groups, function ($a, $b) {
        return strnatcasecmp($a['dir'], $b['dir']);
    });

    return $groups;
}

/**
 * Read overrides from CSF group, fallback to legacy textarea.
 */
function zibll_additional_demo01_get_group_overrides()
{
    $groups = array();
    $raw_groups = zibll_additional_demo01_get_setting('emoji_groups', array());

    if (is_array($raw_groups) && !empty($raw_groups)) {
        foreach ($raw_groups as $row) {
            if (!is_array($row)) {
                continue;
            }

            $dir = zibll_additional_demo01_clean_group_dir((string) ($row['dir'] ?? ''));
            if ($dir === '') {
                continue;
            }

            $title = sanitize_text_field((string) ($row['title'] ?? ''));
            if ($title === '') {
                $title = $dir;
            }

            $groups[] = array(
                'dir'   => $dir,
                'title' => $title,
            );
        }
    }

    if (empty($groups)) {
        $legacy = zibll_additional_demo01_parse_group_lines(zibll_additional_demo01_get_setting('group_lines', ''));
        foreach ($legacy as $row) {
            $dir = zibll_additional_demo01_clean_group_dir((string) ($row['dir'] ?? ''));
            if ($dir === '') {
                continue;
            }

            $title = sanitize_text_field((string) ($row['title'] ?? ''));
            if ($title === '') {
                $title = $dir;
            }

            $groups[] = array(
                'dir'   => $dir,
                'title' => $title,
            );
        }
    }

    return $groups;
}

/**
 * Effective groups: detected folders + overrides.
 */
function zibll_additional_demo01_get_effective_groups()
{
    $detected = zibll_additional_demo01_get_detected_folder_groups();
    $overrides = zibll_additional_demo01_get_group_overrides();

    $detected_map = array();
    foreach ($detected as $group) {
        $dir = zibll_additional_demo01_clean_group_dir((string) $group['dir']);
        if ($dir === '') {
            continue;
        }

        $detected_map[$dir] = array(
            'dir'   => $dir,
            'title' => (string) $group['title'],
        );
    }

    foreach ($overrides as $group) {
        $dir = zibll_additional_demo01_clean_group_dir((string) $group['dir']);
        if ($dir === '') {
            continue;
        }

        $title = sanitize_text_field((string) ($group['title'] ?? ''));
        if ($title === '') {
            $title = $dir;
        }

        if (isset($detected_map[$dir])) {
            $detected_map[$dir]['title'] = $title;
        } else {
            $detected_map[$dir] = array(
                'dir'   => $dir,
                'title' => $title,
            );
        }
    }

    $groups = array_values($detected_map);
    usort($groups, function ($a, $b) {
        return strnatcasecmp($a['dir'], $b['dir']);
    });

    $sort_order = zibll_additional_demo01_get_sort_order();
    if (!empty($sort_order['groups'])) {
        $group_map = array();
        foreach ($groups as $group) {
            $group_map[$group['dir']] = $group;
        }

        $ordered_groups = array();
        foreach ($sort_order['groups'] as $dir) {
            if (!isset($group_map[$dir])) {
                continue;
            }

            $ordered_groups[] = $group_map[$dir];
            unset($group_map[$dir]);
        }

        foreach ($groups as $group) {
            if (isset($group_map[$group['dir']])) {
                $ordered_groups[] = $group;
            }
        }

        $groups = $ordered_groups;
    }

    return $groups;
}

/**
 * Insert or update one group row.
 */
function zibll_additional_demo01_upsert_group_line($dir, $title = '')
{
    $dir = zibll_additional_demo01_clean_group_dir((string) $dir);
    if ($dir === '') {
        return false;
    }

    $title = sanitize_text_field((string) $title);

    $options = get_option(ZIBLL_COMMENT_EMOJI_OPTIONS_KEY, array());
    if (!is_array($options)) {
        $options = array();
    }

    $rows = isset($options['emoji_groups']) && is_array($options['emoji_groups']) ? $options['emoji_groups'] : array();
    $new_rows = array();
    $updated = false;

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $row_dir = zibll_additional_demo01_clean_group_dir((string) ($row['dir'] ?? ''));
        if ($row_dir === '') {
            continue;
        }

        $row_title = sanitize_text_field((string) ($row['title'] ?? ''));
        if ($row_title === '') {
            $row_title = $row_dir;
        }

        if ($row_dir === $dir) {
            $updated = true;
            $new_rows[] = array(
                'dir'   => $dir,
                'title' => ($title === '' ? $row_title : $title),
            );
        } else {
            $new_rows[] = array(
                'dir'   => $row_dir,
                'title' => $row_title,
            );
        }
    }

    if (!$updated) {
        $new_rows[] = array(
            'dir'   => $dir,
            'title' => ($title === '' ? $dir : $title),
        );
    }

    $options['emoji_groups'] = $new_rows;
    update_option(ZIBLL_COMMENT_EMOJI_OPTIONS_KEY, $options);
    return true;
}

function zibll_additional_demo01_get_group_files($group_path, $allowed_exts, $max_per_group, $group_dir = '')
{
    $items = array();
    if (!is_dir($group_path) || !is_readable($group_path)) {
        return $items;
    }

    $entries = scandir($group_path);
    if (!$entries) {
        return $items;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $file_path = $group_path . '/' . $entry;
        if (!is_file($file_path)) {
            continue;
        }

        $ext = strtolower((string) pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts, true)) {
            continue;
        }

        $items[] = array(
            'file_name' => $entry,
            'file_path' => zibll_additional_demo01_normalize_path($file_path),
            'name'      => (string) pathinfo($entry, PATHINFO_FILENAME),
        );
    }

    usort($items, function ($a, $b) {
        return strnatcasecmp($a['file_name'], $b['file_name']);
    });

    $group_dir = zibll_additional_demo01_clean_group_dir($group_dir);
    $sort_order = zibll_additional_demo01_get_sort_order();
    if ($group_dir !== '' && !empty($sort_order['items'][$group_dir])) {
        $item_map = array();
        foreach ($items as $item) {
            $item_map[$item['file_name']] = $item;
        }

        $ordered_items = array();
        foreach ($sort_order['items'][$group_dir] as $file_name) {
            if (!isset($item_map[$file_name])) {
                continue;
            }

            $ordered_items[] = $item_map[$file_name];
            unset($item_map[$file_name]);
        }

        foreach ($items as $item) {
            if (isset($item_map[$item['file_name']])) {
                $ordered_items[] = $item;
            }
        }

        $items = $ordered_items;
    }

    if ($max_per_group > 0 && count($items) > $max_per_group) {
        $items = array_slice($items, 0, $max_per_group);
    }

    return $items;
}

function zibll_additional_demo01_get_relative_path($root, $full_path)
{
    $root = zibll_additional_demo01_normalize_path($root);
    $full_path = zibll_additional_demo01_normalize_path($full_path);
    if ($root === '' || $full_path === '') {
        return '';
    }

    $prefix = $root . '/';
    if (stripos($full_path, $prefix) !== 0) {
        return '';
    }

    return substr($full_path, strlen($prefix));
}

function zibll_additional_demo01_build_encoded_url($base_url, $relative)
{
    $relative = trim((string) $relative);
    $relative = str_replace('\\', '/', $relative);
    $relative = ltrim($relative, '/');
    if ($relative === '') {
        return untrailingslashit($base_url);
    }

    $parts = explode('/', $relative);
    $parts = array_map('rawurlencode', $parts);
    return untrailingslashit($base_url) . '/' . implode('/', $parts);
}

/**
 * Build frontend group data and token map.
 */
function zibll_additional_demo01_get_emoji_data()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = array(
        'groups' => array(),
        'map'    => array(),
    );

    if (!zibll_additional_demo01_is_enabled()) {
        return $cache;
    }

    $storage = zibll_additional_demo01_get_storage(false, true);
    $root = $storage['root'];
    $base_url = $storage['url'];

    if ($root === '' || $base_url === '' || !is_dir($root)) {
        return $cache;
    }

    $allowed_exts = zibll_additional_demo01_get_allowed_exts(zibll_additional_demo01_get_setting('allowed_exts', 'gif,png'));
    $max_per_group = max(1, min(500, absint(zibll_additional_demo01_get_setting('max_per_group', 200))));

    $scan_groups = zibll_additional_demo01_get_effective_groups();

    foreach ($scan_groups as $group_index => $group) {
        $dir_name = trim((string) $group['dir']);
        if ($dir_name === '') {
            continue;
        }
        if (strpos($dir_name, '..') !== false || strpos($dir_name, '/') !== false || strpos($dir_name, '\\') !== false) {
            continue;
        }

        $group_path = zibll_additional_demo01_normalize_path($root . '/' . $dir_name);
        if (!is_dir($group_path)) {
            continue;
        }

        $items = zibll_additional_demo01_get_group_files($group_path, $allowed_exts, $max_per_group, $dir_name);
        if (empty($items)) {
            continue;
        }

        $group_id = sanitize_title($dir_name);
        if ($group_id === '') {
            $group_id = 'group-' . substr(md5($dir_name . '|' . $group_index), 0, 8);
        }

        $group_title = trim((string) $group['title']);
        if ($group_title === '') {
            $group_title = $dir_name;
        }

        $frontend_items = array();
        foreach ($items as $item) {
            $relative = zibll_additional_demo01_get_relative_path($root, $item['file_path']);
            if ($relative === '') {
                continue;
            }

            $token = 'zcem_' . substr(md5($relative), 0, 18);
            $url = zibll_additional_demo01_build_encoded_url($base_url, $relative);
            $name = $item['name'];

            $frontend_items[] = array(
                'token' => $token,
                'name'  => $name,
                'url'   => $url,
                'file'  => $item['file_name'],
            );

            $cache['map'][$token] = array(
                'name' => $name,
                'url'  => $url,
            );
        }

        if (!empty($frontend_items)) {
            $cache['groups'][] = array(
                'id'    => $group_id,
                'dir'   => $dir_name,
                'title' => $group_title,
                'items' => $frontend_items,
            );
        }
    }

    return $cache;
}

/**
 * Replace [g=token] in comment content with img.
 *
 * @param string $text       Content containing custom emoji tokens or images.
 * @param bool   $email_mode Whether to add email-safe inline image dimensions.
 */
function zibll_additional_demo01_replace_custom_emoji_in_comment_text($text, $email_mode = false)
{
    if (!is_string($text) || (strpos($text, '[g=') === false && stripos($text, 'zcem_') === false)) {
        return $text;
    }

    $data = zibll_additional_demo01_get_emoji_data();
    if (empty($data['map'])) {
        return $text;
    }

    if (strpos($text, '[g=') !== false) {
        $text = preg_replace_callback('/\[g=([^\]]+)\]/', function ($match) use ($data, $email_mode) {
            $token = $match[1];
            if (!isset($data['map'][$token])) {
                return $match[0];
            }

            $name = $data['map'][$token]['name'];
            $url = esc_url($data['map'][$token]['url']);
            $email_attributes = $email_mode
                ? ' width="30" height="30" style="display:inline-block;width:30px;height:30px;max-width:30px;max-height:30px;margin:0 2px;border:0;vertical-align:middle;object-fit:contain;"'
                : '';
            return '<img class="smilie-icon" src="' . $url . '" alt="' . esc_attr('表情[' . $name . ']') . '"' . $email_attributes . '>';
        }, $text);
    }

    if (stripos($text, 'zcem_') === false || stripos($text, '<img') === false || !class_exists('WP_HTML_Tag_Processor')) {
        return $text;
    }

    $processor = new WP_HTML_Tag_Processor($text);
    while ($processor->next_tag('img')) {
        $class_name = (string) $processor->get_attribute('class');
        if (!preg_match('/(?:^|\s)smilie-icon(?:\s|$)/', $class_name)) {
            continue;
        }

        $token = '';
        foreach (array('data-src', 'src', 'alt') as $attribute_name) {
            $attribute_value = $processor->get_attribute($attribute_name);
            if (is_string($attribute_value) && preg_match('/zcem_[a-z0-9]{18}/i', $attribute_value, $matches)) {
                $token = strtolower($matches[0]);
                break;
            }
        }

        if ($token === '' || empty($data['map'][$token])) {
            continue;
        }

        $real_url = esc_url_raw($data['map'][$token]['url']);
        $processor->set_attribute('src', $real_url);
        if (null !== $processor->get_attribute('data-src')) {
            $processor->set_attribute('data-src', $real_url);
        }
        $processor->set_attribute('alt', '表情[' . sanitize_text_field($data['map'][$token]['name']) . ']');

        if ($email_mode) {
            $style = trim((string) $processor->get_attribute('style'));
            if ($style !== '' && substr($style, -1) !== ';') {
                $style .= ';';
            }

            $processor->set_attribute('width', '30');
            $processor->set_attribute('height', '30');
            $processor->set_attribute('style', $style . 'display:inline-block;width:30px;height:30px;max-width:30px;max-height:30px;margin:0 2px;border:0;vertical-align:middle;object-fit:contain;');
        }
    }

    return $processor->get_updated_html();
}
add_filter('comment_text', 'zibll_additional_demo01_replace_custom_emoji_in_comment_text', 1);

/**
 * Repair custom emoji URLs before Zibll stores a notification message.
 */
function zibll_additional_demo01_filter_message_values($values)
{
    if (!is_array($values) || empty($values['content']) || !is_string($values['content'])) {
        return $values;
    }

    // Zibll strips HTML before rendering private messages, so keep emoji tokens intact.
    if (isset($values['type']) && $values['type'] === 'private') {
        return $values;
    }

    $values['content'] = zibll_additional_demo01_replace_custom_emoji_in_comment_text($values['content']);
    return $values;
}
add_filter('zib_add_message_values', 'zibll_additional_demo01_filter_message_values', 20);

/**
 * Repair custom emoji images in outgoing HTML emails.
 */
function zibll_additional_demo01_filter_wp_mail($args)
{
    if (
        !is_array($args)
        || empty($args['message'])
        || !is_string($args['message'])
        || stripos($args['message'], 'zcem_') === false
    ) {
        return $args;
    }

    $args['message'] = zibll_additional_demo01_replace_custom_emoji_in_comment_text($args['message'], true);
    return $args;
}
add_filter('wp_mail', 'zibll_additional_demo01_filter_wp_mail', 99);

/**
 * Admin ajax upload.
 */
function zibll_additional_demo01_ajax_admin_upload()
{
    if (!check_ajax_referer('zibll_comment_emoji_admin_upload', 'nonce', false)) {
        wp_send_json_error(array('msg' => '请求校验失败，请刷新页面后重试'), 403);
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('msg' => '权限不足'));
    }

    $group_dir_raw = isset($_POST['group_dir']) ? sanitize_text_field(wp_unslash($_POST['group_dir'])) : '';
    $group_title = isset($_POST['group_title']) ? sanitize_text_field(wp_unslash($_POST['group_title'])) : '';
    $group_dir = sanitize_title($group_dir_raw);

    if ($group_dir === '') {
        wp_send_json_error(array('msg' => '请填写分组目录（英文）'));
    }

    if (empty($_FILES['emoji_files'])) {
        wp_send_json_error(array('msg' => '请选择要上传的图片'));
    }

    $files = $_FILES['emoji_files'];
    $names = is_array($files['name']) ? $files['name'] : array($files['name']);
    $tmp_names = is_array($files['tmp_name']) ? $files['tmp_name'] : array($files['tmp_name']);
    $errors = is_array($files['error']) ? $files['error'] : array($files['error']);
    $expected_count = isset($_POST['expected_count']) ? absint(wp_unslash($_POST['expected_count'])) : 0;
    $received_count = count($names);

    if ($expected_count > 0 && $received_count < $expected_count) {
        $server_limit = (int) ini_get('max_file_uploads');
        $limit_text = $server_limit > 0 ? '（当前 max_file_uploads=' . $server_limit . '）' : '';
        wp_send_json_error(array(
            'msg'      => '服务器只收到 ' . $received_count . '/' . $expected_count . ' 个文件' . $limit_text . '，请刷新页面后重试',
            'uploaded' => 0,
            'failed'   => $expected_count,
        ));
    }

    $storage = zibll_additional_demo01_get_storage(true, true);
    $root = $storage['root'];
    if (!is_dir($root) && !wp_mkdir_p($root)) {
        wp_send_json_error(array('msg' => '无法创建根目录'));
    }

    $group_path = zibll_additional_demo01_normalize_path($root . '/' . $group_dir);
    if (!is_dir($group_path) && !wp_mkdir_p($group_path)) {
        wp_send_json_error(array('msg' => '无法创建分组目录'));
    }
    if (!is_writable($group_path)) {
        wp_send_json_error(array('msg' => '分组目录不可写，请检查权限'));
    }

    $allowed_exts = zibll_additional_demo01_get_allowed_exts(zibll_additional_demo01_get_setting('allowed_exts', 'gif,png'));

    $ok = 0;
    $fail = 0;
    foreach ($names as $index => $name) {
        $name = (string) $name;
        $tmp_name = isset($tmp_names[$index]) ? $tmp_names[$index] : '';
        $error = isset($errors[$index]) ? (int) $errors[$index] : UPLOAD_ERR_NO_FILE;

        if ($error !== UPLOAD_ERR_OK || !$tmp_name || !is_uploaded_file($tmp_name)) {
            $fail++;
            continue;
        }

        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts, true)) {
            $fail++;
            continue;
        }

        $img_info = @getimagesize($tmp_name);
        if (!$img_info) {
            $fail++;
            continue;
        }

        $base_name = sanitize_file_name((string) pathinfo($name, PATHINFO_FILENAME));
        if ($base_name === '') {
            $base_name = 'emoji-' . substr(md5($name . microtime(true) . $index), 0, 8);
        }

        $target_name = $base_name . '.' . $ext;
        $target_path = $group_path . '/' . $target_name;
        $suffix = 1;
        while (file_exists($target_path)) {
            $target_name = $base_name . '-' . $suffix . '.' . $ext;
            $target_path = $group_path . '/' . $target_name;
            $suffix++;
        }

        if (!move_uploaded_file($tmp_name, $target_path)) {
            $fail++;
            continue;
        }
        $ok++;
    }

    if ($ok <= 0) {
        wp_send_json_error(array(
            'msg'      => '本批上传失败，请检查图片格式、文件大小或目录权限',
            'uploaded' => 0,
            'failed'   => $fail,
        ));
    }

    // Empty title means keep folder name as display name.
    zibll_additional_demo01_upsert_group_line($group_dir, $group_title);

    $msg = '上传成功 ' . $ok . ' 个';
    if ($fail > 0) {
        $msg .= '，失败 ' . $fail . ' 个';
    }

    wp_send_json_success(array(
        'msg'      => $msg,
        'uploaded' => $ok,
        'failed'   => $fail,
    ));
}
add_action('wp_ajax_zibll_comment_emoji_admin_upload', 'zibll_additional_demo01_ajax_admin_upload');

/**
 * Save or reset the visual group and emoji order.
 */
function zibll_additional_demo01_ajax_save_sort_order()
{
    if (!check_ajax_referer('zibll_comment_emoji_save_order', 'nonce', false)) {
        wp_send_json_error(array('msg' => '请求校验失败，请刷新页面后重试'), 403);
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('msg' => '权限不足'), 403);
    }

    if (!empty($_POST['reset'])) {
        delete_option(ZIBLL_COMMENT_EMOJI_ORDER_KEY);
        wp_send_json_success(array('msg' => '已恢复默认排序'));
    }

    $order_json = isset($_POST['order']) ? wp_unslash($_POST['order']) : '';
    if (!is_string($order_json) || $order_json === '' || strlen($order_json) > MB_IN_BYTES) {
        wp_send_json_error(array('msg' => '排序数据无效'));
    }

    $submitted = json_decode($order_json, true);
    if (!is_array($submitted)) {
        wp_send_json_error(array('msg' => '排序数据格式错误'));
    }

    $storage = zibll_additional_demo01_get_storage(false, true);
    $root = $storage['root'];
    $allowed_exts = zibll_additional_demo01_get_allowed_exts(zibll_additional_demo01_get_setting('allowed_exts', 'gif,png,jpg,jpeg,webp'));
    $valid_groups = array();

    foreach (zibll_additional_demo01_get_effective_groups() as $group) {
        $dir = zibll_additional_demo01_clean_group_dir((string) ($group['dir'] ?? ''));
        if ($dir === '') {
            continue;
        }

        $group_path = zibll_additional_demo01_normalize_path($root . '/' . $dir);
        if (!is_dir($group_path)) {
            continue;
        }

        $files = zibll_additional_demo01_get_group_files($group_path, $allowed_exts, 0);
        if (empty($files)) {
            continue;
        }

        $valid_groups[$dir] = $files;
    }

    if (empty($valid_groups)) {
        wp_send_json_error(array('msg' => '未找到可排序的表情分组'));
    }

    $saved_groups = array();
    $seen_groups = array();
    $submitted_groups = isset($submitted['groups']) && is_array($submitted['groups']) ? $submitted['groups'] : array();
    foreach ($submitted_groups as $raw_dir) {
        $dir = zibll_additional_demo01_clean_group_dir((string) $raw_dir);
        if ($dir === '' || !isset($valid_groups[$dir]) || isset($seen_groups[$dir])) {
            continue;
        }

        $seen_groups[$dir] = true;
        $saved_groups[] = $dir;
    }

    foreach (array_keys($valid_groups) as $dir) {
        if (!isset($seen_groups[$dir])) {
            $seen_groups[$dir] = true;
            $saved_groups[] = $dir;
        }
    }

    $submitted_items = isset($submitted['items']) && is_array($submitted['items']) ? $submitted['items'] : array();
    $saved_items = array();

    foreach ($valid_groups as $dir => $files) {
        $valid_file_names = array();
        foreach ($files as $file) {
            $valid_file_names[$file['file_name']] = true;
        }

        $seen_files = array();
        $group_items = isset($submitted_items[$dir]) && is_array($submitted_items[$dir]) ? $submitted_items[$dir] : array();
        foreach ($group_items as $raw_file_name) {
            $file_name = (string) $raw_file_name;
            if (!isset($valid_file_names[$file_name]) || isset($seen_files[$file_name])) {
                continue;
            }

            $seen_files[$file_name] = true;
            $saved_items[$dir][] = $file_name;
        }

        foreach ($files as $file) {
            $file_name = $file['file_name'];
            if (!isset($seen_files[$file_name])) {
                $seen_files[$file_name] = true;
                $saved_items[$dir][] = $file_name;
            }
        }
    }

    $sort_order = array(
        'groups' => $saved_groups,
        'items'  => $saved_items,
    );
    update_option(ZIBLL_COMMENT_EMOJI_ORDER_KEY, $sort_order, false);

    wp_send_json_success(array('msg' => '排序已保存'));
}
add_action('wp_ajax_zibll_comment_emoji_save_order', 'zibll_additional_demo01_ajax_save_sort_order');

/**
 * Admin visual sorting shell. Items are rendered by admin.js on demand.
 */
function zibll_additional_demo01_get_sort_manager_html()
{
    $data = zibll_additional_demo01_get_emoji_data();
    if (empty($data['groups'])) {
        return '<p class="description">暂无可排序的表情，请先上传表情并启用插件功能。</p>';
    }

    $html  = '<div class="zibll-emoji-sort-manager">';
    $html .= '<ul class="zibll-emoji-sort-groups" aria-label="表情分组排序"></ul>';
    $html .= '<div class="zibll-emoji-sort-heading">';
    $html .= '<strong class="zibll-emoji-sort-current-title"></strong>';
    $html .= '<span class="zibll-emoji-sort-count"></span>';
    $html .= '</div>';
    $html .= '<div class="zibll-emoji-sort-items" aria-label="组内表情排序"></div>';
    $html .= '<div class="zibll-emoji-sort-actions">';
    $html .= '<button type="button" class="button zibll-emoji-sort-reset">恢复默认排序</button>';
    $html .= '<span class="zibll-emoji-sort-status" aria-live="polite"></span>';
    $html .= '<span class="spinner"></span>';
    $html .= '<button type="button" class="button button-primary zibll-emoji-sort-save" disabled>保存排序</button>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Admin group list html.
 */
function zibll_additional_demo01_get_group_list_html()
{
    $data = zibll_additional_demo01_get_emoji_data();
    if (empty($data['groups'])) {
        return '<p class="description">暂无分组，请先上传表情。</p>';
    }

    $html = '<ul class="zibll-emoji-admin-group-list">';
    foreach ($data['groups'] as $group) {
        $html .= '<li><strong>' . esc_html($group['title']) . '</strong>（' . esc_html((string) count($group['items'])) . '）</li>';
    }
    $html .= '</ul>';
    return $html;
}
