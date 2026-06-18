<?php
/**
 * Plugin Name: Life Revolution
 * Description: Adds the Umbrella Parade Life Revolution budgeting tool to WordPress with the [life_revolution] shortcode.
 * Version: 0.1.1
 * Author: Umbrella Parade
 * License: GPL-2.0-or-later
 * Text Domain: life-revolution
 * Update URI: https://github.com/UmbrellaParade/life-revolution
 */

if (!defined('ABSPATH')) {
    exit;
}

define('YUTORI_LEDGER_VERSION', '0.1.1');
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
            yutori_ledger_config_js(),
            'before'
        );
    }
}

function yutori_ledger_config(): array {
    return array(
        'assetsUrl' => YUTORI_LEDGER_URL,
        'enableServiceWorker' => false,
    );
}

function yutori_ledger_config_js(): string {
    $config = wp_json_encode(yutori_ledger_config());
    return 'window.LifeRevolutionConfig = ' . $config . '; window.YutoriLedgerConfig = window.LifeRevolutionConfig;';
}

function yutori_ledger_config_script(): string {
    return '<script>' . yutori_ledger_config_js() . '</script>';
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
    $classes = trim('life-revolution-root yutori-ledger-root ' . $extra_class);

    return yutori_ledger_config_script() . '<div class="' . esc_attr($classes) . '" data-life-revolution-root data-yutori-ledger-root></div>';
}
add_shortcode('yutori_ledger', 'yutori_ledger_shortcode');
add_shortcode('life_revolution', 'yutori_ledger_shortcode');

function yutori_ledger_register_admin_page() {
    add_menu_page(
        __('Life Revolution', 'life-revolution'),
        __('Life Revolution', 'life-revolution'),
        'read',
        'life-revolution',
        'yutori_ledger_render_admin_page',
        'dashicons-chart-line',
        58
    );
}
add_action('admin_menu', 'yutori_ledger_register_admin_page');

function yutori_ledger_enqueue_admin_assets($hook_suffix) {
    if ('toplevel_page_life-revolution' === $hook_suffix) {
        yutori_ledger_enqueue_app();
    }
}
add_action('admin_enqueue_scripts', 'yutori_ledger_enqueue_admin_assets');

function yutori_ledger_render_admin_page() {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Life Revolution', 'life-revolution') . '</h1>';
    echo yutori_ledger_config_script();
    echo '<div class="life-revolution-root yutori-ledger-root" data-life-revolution-root data-yutori-ledger-root></div>';
    echo '</div>';
}
