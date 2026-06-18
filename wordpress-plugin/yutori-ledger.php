<?php
/**
 * Plugin Name: Life Revolution
 * Description: Adds the Umbrella Parade Life Revolution budgeting tool to WordPress with the [life_revolution] shortcode.
 * Version: 0.1.2
 * Author: Umbrella Parade
 * License: GPL-2.0-or-later
 * Text Domain: life-revolution
 * Update URI: https://github.com/UmbrellaParade/life-revolution
 */

if (!defined('ABSPATH')) {
    exit;
}

define('YUTORI_LEDGER_VERSION', '0.1.2');
define('YUTORI_LEDGER_PATH', plugin_dir_path(__FILE__));
define('YUTORI_LEDGER_URL', plugin_dir_url(__FILE__));
define('YUTORI_LEDGER_FRONTEND_PAGE_OPTION', 'life_revolution_frontend_page_id');

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

function yutori_ledger_activate() {
    yutori_ledger_ensure_frontend_page();
}
register_activation_hook(__FILE__, 'yutori_ledger_activate');

function yutori_ledger_handle_create_frontend_page() {
    if (!current_user_can('publish_pages')) {
        wp_die(esc_html__('You do not have permission to create pages.', 'life-revolution'));
    }

    check_admin_referer('life_revolution_create_frontend_page');
    yutori_ledger_ensure_frontend_page(true);
    wp_safe_redirect(admin_url('admin.php?page=life-revolution'));
    exit;
}
add_action('admin_post_life_revolution_create_frontend_page', 'yutori_ledger_handle_create_frontend_page');

function yutori_ledger_ensure_frontend_page($force = false): int {
    $existing_page_id = yutori_ledger_find_frontend_page_id();
    if ($existing_page_id > 0) {
        return $existing_page_id;
    }

    $existing = get_page_by_path('life-revolution');
    if ($existing instanceof WP_Post && $existing->post_status !== 'trash') {
        $page_id = (int) $existing->ID;
        if ($force && !has_shortcode((string) $existing->post_content, 'life_revolution')) {
            wp_update_post(array(
                'ID' => $page_id,
                'post_content' => trim((string) $existing->post_content) . "\n\n[life_revolution]",
            ));
        }
        update_option(YUTORI_LEDGER_FRONTEND_PAGE_OPTION, $page_id, false);
        return $page_id;
    }

    $page_id = wp_insert_post(array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'Life Revolution',
        'post_name' => 'life-revolution',
        'post_content' => '[life_revolution]',
        'post_author' => get_current_user_id() ?: 1,
        'comment_status' => 'closed',
        'ping_status' => 'closed',
    ));

    if (!is_wp_error($page_id) && $page_id > 0) {
        update_option(YUTORI_LEDGER_FRONTEND_PAGE_OPTION, (int) $page_id, false);
        return (int) $page_id;
    }

    return 0;
}

function yutori_ledger_find_frontend_page_id(): int {
    $page_id = (int) get_option(YUTORI_LEDGER_FRONTEND_PAGE_OPTION, 0);
    $page = $page_id > 0 ? get_post($page_id) : null;
    if ($page instanceof WP_Post && $page->post_status !== 'trash' && has_shortcode((string) $page->post_content, 'life_revolution')) {
        return $page_id;
    }

    $existing = get_page_by_path('life-revolution');
    if ($existing instanceof WP_Post && $existing->post_status !== 'trash' && has_shortcode((string) $existing->post_content, 'life_revolution')) {
        update_option(YUTORI_LEDGER_FRONTEND_PAGE_OPTION, (int) $existing->ID, false);
        return (int) $existing->ID;
    }

    return 0;
}

function yutori_ledger_frontend_page_url(): string {
    $page_id = yutori_ledger_find_frontend_page_id();
    if ($page_id > 0) {
        return (string) get_permalink($page_id);
    }

    return '';
}

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
    $frontend_url = yutori_ledger_frontend_page_url();
    $create_page_url = wp_nonce_url(
        admin_url('admin-post.php?action=life_revolution_create_frontend_page'),
        'life_revolution_create_frontend_page'
    );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Life Revolution', 'life-revolution') . '</h1>';
    echo '<p>';
    if ($frontend_url !== '') {
        echo '<a class="button button-primary" href="' . esc_url($frontend_url) . '" target="_blank" rel="noopener">' . esc_html__('スマホ用ページを開く', 'life-revolution') . '</a> ';
        echo '<span class="description">' . esc_html__('サイト側でLife Revolutionを開けます。スマホから使う時はこちらが便利です。', 'life-revolution') . '</span>';
    } else {
        echo '<a class="button button-primary" href="' . esc_url($create_page_url) . '">' . esc_html__('スマホ用ページを作成', 'life-revolution') . '</a> ';
        echo '<span class="description">' . esc_html__('固定ページ life-revolution に [life_revolution] を入れて作成します。', 'life-revolution') . '</span>';
    }
    echo '</p>';
    echo yutori_ledger_config_script();
    echo '<div class="life-revolution-root yutori-ledger-root" data-life-revolution-root data-yutori-ledger-root></div>';
    echo '</div>';
}
