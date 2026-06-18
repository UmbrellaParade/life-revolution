<?php
/**
 * Plugin Name: Yutori Ledger
 * Description: Adds the Umbrella Parade Life Revolution budgeting tool to WordPress with the [yutori_ledger] shortcode.
 * Version: 0.1.0
 * Author: Umbrella Parade
 * License: GPL-2.0-or-later
 * Text Domain: yutori-ledger
 */

if (!defined('ABSPATH')) {
    exit;
}

define('YUTORI_LEDGER_VERSION', '0.1.0');
define('YUTORI_LEDGER_PATH', plugin_dir_path(__FILE__));
define('YUTORI_LEDGER_URL', plugin_dir_url(__FILE__));

function yutori_ledger_find_asset($pattern) {
    $files = glob(YUTORI_LEDGER_PATH . 'assets/' . $pattern);

    if (empty($files)) {
        return null;
    }

    return basename($files[0]);
}

function yutori_ledger_enqueue_app() {
    $script_asset = yutori_ledger_find_asset('index-*.js');
    $style_asset = yutori_ledger_find_asset('index-*.css');

    if ($style_asset) {
        $style_path = YUTORI_LEDGER_PATH . 'assets/' . $style_asset;
        wp_enqueue_style(
            'yutori-ledger-app',
            YUTORI_LEDGER_URL . 'assets/' . $style_asset,
            array(),
            file_exists($style_path) ? filemtime($style_path) : YUTORI_LEDGER_VERSION
        );
    }

    if ($script_asset) {
        $script_path = YUTORI_LEDGER_PATH . 'assets/' . $script_asset;
        wp_enqueue_script(
            'yutori-ledger-app',
            YUTORI_LEDGER_URL . 'assets/' . $script_asset,
            array(),
            file_exists($script_path) ? filemtime($script_path) : YUTORI_LEDGER_VERSION,
            true
        );

        wp_add_inline_script(
            'yutori-ledger-app',
            'window.YutoriLedgerConfig = ' . wp_json_encode(array(
                'assetsUrl' => YUTORI_LEDGER_URL,
                'enableServiceWorker' => false,
            )) . ';',
            'before'
        );
    }
}

function yutori_ledger_script_loader_tag($tag, $handle, $src) {
    if ('yutori-ledger-app' !== $handle) {
        return $tag;
    }

    return '<script type="module" crossorigin src="' . esc_url($src) . '"></script>' . "\n";
}
add_filter('script_loader_tag', 'yutori_ledger_script_loader_tag', 10, 3);

function yutori_ledger_shortcode($atts = array()) {
    yutori_ledger_enqueue_app();

    $atts = shortcode_atts(
        array(
            'class' => '',
        ),
        $atts,
        'yutori_ledger'
    );

    $extra_class = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $atts['class']);
    $classes = trim('yutori-ledger-root ' . $extra_class);

    return '<div class="' . esc_attr($classes) . '" data-yutori-ledger-root></div>';
}
add_shortcode('yutori_ledger', 'yutori_ledger_shortcode');

function yutori_ledger_register_admin_page() {
    add_menu_page(
        __('Yutori Ledger', 'yutori-ledger'),
        __('Yutori Ledger', 'yutori-ledger'),
        'read',
        'yutori-ledger',
        'yutori_ledger_render_admin_page',
        'dashicons-chart-line',
        58
    );
}
add_action('admin_menu', 'yutori_ledger_register_admin_page');

function yutori_ledger_enqueue_admin_assets($hook_suffix) {
    if ('toplevel_page_yutori-ledger' === $hook_suffix) {
        yutori_ledger_enqueue_app();
    }
}
add_action('admin_enqueue_scripts', 'yutori_ledger_enqueue_admin_assets');

function yutori_ledger_render_admin_page() {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Yutori Ledger', 'yutori-ledger') . '</h1>';
    echo '<div class="yutori-ledger-root" data-yutori-ledger-root></div>';
    echo '</div>';
}
