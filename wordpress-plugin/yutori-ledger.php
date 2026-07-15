<?php
/**
 * Plugin Name: Life Revolution
 * Description: Adds the Umbrella Parade Life Revolution budgeting tool to WordPress with the [life_revolution] shortcode.
 * Version: 0.1.13
 * Author: Umbrella Parade
 * License: GPL-2.0-or-later
 * Text Domain: life-revolution
 * Update URI: https://github.com/UmbrellaParade/life-revolution
 */

if (!defined('ABSPATH')) {
    exit;
}

define('YUTORI_LEDGER_VERSION', '0.1.13');
define('YUTORI_LEDGER_PATH', plugin_dir_path(__FILE__));
define('YUTORI_LEDGER_URL', plugin_dir_url(__FILE__));
define('YUTORI_LEDGER_FRONTEND_PAGE_OPTION', 'life_revolution_frontend_page_id');
define('YUTORI_LEDGER_STATE_META_KEY', 'life_revolution_state_v1');
define('YUTORI_LEDGER_STATE_UPDATED_META_KEY', 'life_revolution_state_updated_at_v1');

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
        'restUrl' => esc_url_raw(rest_url('life-revolution/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
        'userId' => get_current_user_id(),
        'hasWordPressStorage' => is_user_logged_in(),
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
    $atts = shortcode_atts(
        array(
            'class' => '',
        ),
        $atts,
        'yutori_ledger'
    );

    if (!is_user_logged_in()) {
        $redirect = get_permalink();
        if (!$redirect) {
            $redirect = home_url('/');
        }

        return '<div class="life-revolution-login-notice">'
            . '<p>' . esc_html__('Life Revolutionはログイン中のユーザーだけが使えます。', 'life-revolution') . '</p>'
            . '<p><a class="button button-primary" href="' . esc_url(wp_login_url($redirect)) . '">' . esc_html__('ログインして開く', 'life-revolution') . '</a></p>'
            . '</div>';
    }

    yutori_ledger_enqueue_app();

    $extra_class = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $atts['class']);
    $classes = trim('life-revolution-root yutori-ledger-root ' . $extra_class);

    return yutori_ledger_config_script() . '<div class="' . esc_attr($classes) . '" data-life-revolution-root data-yutori-ledger-root></div>';
}
add_shortcode('yutori_ledger', 'yutori_ledger_shortcode');
add_shortcode('life_revolution', 'yutori_ledger_shortcode');

function yutori_ledger_is_frontend_app_page(): bool {
    if (is_admin()) {
        return false;
    }

    $page_id = yutori_ledger_find_frontend_page_id();
    if ($page_id > 0 && is_page($page_id)) {
        return true;
    }

    $post = get_post();
    if (!$post instanceof WP_Post) {
        return false;
    }

    $content = (string) $post->post_content;
    return has_shortcode($content, 'life_revolution') || has_shortcode($content, 'yutori_ledger');
}

function yutori_ledger_frontend_body_classes(array $classes): array {
    if (yutori_ledger_is_frontend_app_page()) {
        $classes[] = 'life-revolution-app-page';
    }

    return $classes;
}
add_filter('body_class', 'yutori_ledger_frontend_body_classes');

function yutori_ledger_hide_mobile_admin_bar_styles(): void {
    if (!yutori_ledger_is_frontend_app_page()) {
        return;
    }

    echo '<style id="life-revolution-hide-mobile-admin-bar">@media screen and (max-width:782px){html:root{margin-top:0!important;}html:root body.life-revolution-app-page.admin-bar{margin-top:0!important;padding-top:0!important;}body.life-revolution-app-page #wpadminbar{display:none!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important;transform:translate3d(0,-120%,0)!important;height:0!important;min-height:0!important;overflow:hidden!important;}}</style>';
}
add_action('wp_head', 'yutori_ledger_hide_mobile_admin_bar_styles', PHP_INT_MAX);

function yutori_ledger_hide_mobile_admin_bar_script(): void {
    if (!yutori_ledger_is_frontend_app_page()) {
        return;
    }

    $script = '(function(){var query=window.matchMedia("(max-width: 782px)");var scheduled=false;var sync=function(){scheduled=false;var mobile=query.matches;var root=document.documentElement;var body=document.body;var bar=document.getElementById("wpadminbar");root.classList.toggle("life-revolution-mobile",mobile);if(body){if(mobile){body.style.setProperty("margin-top","0","important");body.style.setProperty("padding-top","0","important");}else{body.style.removeProperty("margin-top");body.style.removeProperty("padding-top");}}if(bar){if(mobile){bar.setAttribute("aria-hidden","true");bar.style.setProperty("display","none","important");bar.style.setProperty("visibility","hidden","important");bar.style.setProperty("opacity","0","important");bar.style.setProperty("pointer-events","none","important");bar.style.setProperty("transform","translate3d(0,-120%,0)","important");bar.style.setProperty("height","0","important");bar.style.setProperty("min-height","0","important");bar.style.setProperty("overflow","hidden","important");}else{bar.removeAttribute("aria-hidden");["display","visibility","opacity","pointer-events","transform","height","min-height","overflow"].forEach(function(property){bar.style.removeProperty(property);});}}};var schedule=function(){if(scheduled){return;}scheduled=true;window.requestAnimationFrame(sync);};sync();window.addEventListener("scroll",schedule,{passive:true});window.addEventListener("resize",schedule);if(query.addEventListener){query.addEventListener("change",schedule);}else{query.addListener(schedule);}if("MutationObserver" in window){new MutationObserver(schedule).observe(document.documentElement,{childList:true,subtree:true});}})();';

    wp_print_inline_script_tag($script, array('id' => 'life-revolution-hide-mobile-admin-bar-script'));
}
add_action('wp_footer', 'yutori_ledger_hide_mobile_admin_bar_script', PHP_INT_MAX);

function yutori_ledger_register_rest_routes() {
    register_rest_route('life-revolution/v1', '/state', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'yutori_ledger_rest_get_state',
            'permission_callback' => 'yutori_ledger_rest_permission',
        ),
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'yutori_ledger_rest_save_state',
            'permission_callback' => 'yutori_ledger_rest_permission',
        ),
    ));
}
add_action('rest_api_init', 'yutori_ledger_register_rest_routes');

function yutori_ledger_rest_permission(): bool {
    return is_user_logged_in() && current_user_can('read');
}

function yutori_ledger_rest_get_state() {
    $user_id = get_current_user_id();
    $data = get_user_meta($user_id, YUTORI_LEDGER_STATE_META_KEY, true);
    $updated_at = (string) get_user_meta($user_id, YUTORI_LEDGER_STATE_UPDATED_META_KEY, true);

    if (!is_array($data)) {
        $data = null;
    }

    return rest_ensure_response(array(
        'data' => $data,
        'hasData' => is_array($data),
        'updatedAt' => $updated_at,
    ));
}

function yutori_ledger_rest_save_state(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $data = is_array($params) && isset($params['data']) ? $params['data'] : null;

    if (!is_array($data)) {
        return new WP_Error(
            'life_revolution_invalid_state',
            __('Invalid Life Revolution data.', 'life-revolution'),
            array('status' => 400)
        );
    }

    $encoded = wp_json_encode($data);
    if (!is_string($encoded)) {
        return new WP_Error(
            'life_revolution_encode_failed',
            __('Could not encode Life Revolution data.', 'life-revolution'),
            array('status' => 400)
        );
    }

    if (strlen($encoded) > 5 * 1024 * 1024) {
        return new WP_Error(
            'life_revolution_state_too_large',
            __('Life Revolution data is too large.', 'life-revolution'),
            array('status' => 413)
        );
    }

    $normalized = json_decode($encoded, true);
    if (!is_array($normalized)) {
        return new WP_Error(
            'life_revolution_decode_failed',
            __('Could not normalize Life Revolution data.', 'life-revolution'),
            array('status' => 400)
        );
    }

    $updated_at = gmdate('c');
    $user_id = get_current_user_id();
    update_user_meta($user_id, YUTORI_LEDGER_STATE_META_KEY, $normalized);
    update_user_meta($user_id, YUTORI_LEDGER_STATE_UPDATED_META_KEY, $updated_at);

    return rest_ensure_response(array(
        'data' => $normalized,
        'updatedAt' => $updated_at,
    ));
}

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
        $updates = array('ID' => $page_id);
        if (!has_shortcode((string) $existing->post_content, 'life_revolution')) {
            $updates['post_content'] = trim((string) $existing->post_content) . "\n\n[life_revolution]";
        }
        if ($existing->post_status !== 'private') {
            $updates['post_status'] = 'private';
        }
        if (count($updates) > 1 && ($force || isset($updates['post_status']))) {
            wp_update_post($updates);
        }
        update_option(YUTORI_LEDGER_FRONTEND_PAGE_OPTION, $page_id, false);
        return $page_id;
    }

    $page_id = wp_insert_post(array(
        'post_type' => 'page',
        'post_status' => 'private',
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
        echo '<span class="description">' . esc_html__('ログイン中のユーザーだけが開けるスマホ用ページです。', 'life-revolution') . '</span>';
    } else {
        echo '<a class="button button-primary" href="' . esc_url($create_page_url) . '">' . esc_html__('スマホ用ページを作成', 'life-revolution') . '</a> ';
        echo '<span class="description">' . esc_html__('固定ページ life-revolution に [life_revolution] を入れて作成します。', 'life-revolution') . '</span>';
    }
    echo '</p>';
    echo yutori_ledger_config_script();
    echo '<div class="life-revolution-root yutori-ledger-root" data-life-revolution-root data-yutori-ledger-root></div>';
    echo '</div>';
}
