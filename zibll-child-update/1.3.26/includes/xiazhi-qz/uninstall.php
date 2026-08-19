<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$meta_keys = array('titles_moshi', 'text', 'text_bg_color', 'img', 'custom_img_prefixes');

foreach ($meta_keys as $key) {
    $wpdb->delete($wpdb->postmeta, array('meta_key' => $key), array('%s'));
}

delete_option('xiazhi_qz_options');
