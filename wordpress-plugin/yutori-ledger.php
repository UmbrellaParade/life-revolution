<?php
/**
 * Plugin Name: Life Revolution
 * Description: Adds the Umbrella Parade Life Revolution budgeting tool to WordPress with the [life_revolution] shortcode.
 * Version: 0.4.0
 * Author: Umbrella Parade
 * License: GPL-2.0-or-later
 * Text Domain: life-revolution
 * Update URI: https://github.com/UmbrellaParade/life-revolution
 */

if (!defined('ABSPATH')) {
    exit;
}

define('YUTORI_LEDGER_VERSION', '0.4.0');
define('YUTORI_LEDGER_PATH', plugin_dir_path(__FILE__));
define('YUTORI_LEDGER_URL', plugin_dir_url(__FILE__));
define('YUTORI_LEDGER_FRONTEND_PAGE_OPTION', 'life_revolution_frontend_page_id');
define('YUTORI_LEDGER_STATE_META_KEY', 'life_revolution_state_v1');
define('YUTORI_LEDGER_STATE_UPDATED_META_KEY', 'life_revolution_state_updated_at_v1');
define('YUTORI_LEDGER_USE_CAPABILITY', 'use_life_revolution');
define('YUTORI_LEDGER_MEMBER_ROLE', 'life_revolution_member');
define('YUTORI_LEDGER_INSTALLED_VERSION_OPTION', 'life_revolution_installed_version');
define('YUTORI_LEDGER_PRIVATE_QUERY_VAR', 'life_revolution_private');

function yutori_ledger_ensure_roles(): void {
    $member_role = get_role(YUTORI_LEDGER_MEMBER_ROLE);
    if (!$member_role) {
        $member_role = add_role(
            YUTORI_LEDGER_MEMBER_ROLE,
            __('Life Revolution利用者', 'life-revolution'),
            array(
                'read' => true,
                YUTORI_LEDGER_USE_CAPABILITY => true,
            )
        );
    }

    if ($member_role) {
        if (!$member_role->has_cap('read')) {
            $member_role->add_cap('read');
        }
        if (!$member_role->has_cap(YUTORI_LEDGER_USE_CAPABILITY)) {
            $member_role->add_cap(YUTORI_LEDGER_USE_CAPABILITY);
        }
    }

    $administrator = get_role('administrator');
    if ($administrator && !$administrator->has_cap(YUTORI_LEDGER_USE_CAPABILITY)) {
        $administrator->add_cap(YUTORI_LEDGER_USE_CAPABILITY);
    }

    update_option(YUTORI_LEDGER_INSTALLED_VERSION_OPTION, YUTORI_LEDGER_VERSION, false);
}

function yutori_ledger_activate(): void {
    yutori_ledger_ensure_roles();
    yutori_ledger_register_private_route();
    flush_rewrite_rules(false);
}
register_activation_hook(__FILE__, 'yutori_ledger_activate');

function yutori_ledger_maybe_upgrade(): void {
    if (get_option(YUTORI_LEDGER_INSTALLED_VERSION_OPTION) !== YUTORI_LEDGER_VERSION) {
        yutori_ledger_ensure_roles();
        yutori_ledger_register_private_route();
        flush_rewrite_rules(false);
    }
}
add_action('admin_init', 'yutori_ledger_maybe_upgrade');

function yutori_ledger_grant_administrator_capability(array $allcaps): array {
    if (!empty($allcaps['manage_options'])) {
        $allcaps[YUTORI_LEDGER_USE_CAPABILITY] = true;
    }

    return $allcaps;
}
add_filter('user_has_cap', 'yutori_ledger_grant_administrator_capability');

function yutori_ledger_can_use_private(): bool {
    if (!is_user_logged_in()) {
        return false;
    }

    $user = wp_get_current_user();
    $has_member_role = in_array(YUTORI_LEDGER_MEMBER_ROLE, (array) $user->roles, true);

    return $has_member_role
        || current_user_can(YUTORI_LEDGER_USE_CAPABILITY)
        || current_user_can('manage_options');
}

function yutori_ledger_register_private_route(): void {
    add_rewrite_rule(
        '^my-life-revolution/?$',
        'index.php?' . YUTORI_LEDGER_PRIVATE_QUERY_VAR . '=1',
        'top'
    );
}
add_action('init', 'yutori_ledger_register_private_route');

function yutori_ledger_private_query_vars(array $query_vars): array {
    $query_vars[] = YUTORI_LEDGER_PRIVATE_QUERY_VAR;
    return $query_vars;
}
add_filter('query_vars', 'yutori_ledger_private_query_vars');

function yutori_ledger_private_url(): string {
    return home_url('/my-life-revolution/');
}

function yutori_ledger_member_login_redirect($redirect_to, $requested_redirect_to, $user) {
    if ($user instanceof WP_User && in_array(YUTORI_LEDGER_MEMBER_ROLE, (array) $user->roles, true)) {
        return yutori_ledger_private_url();
    }

    return $redirect_to;
}
add_filter('login_redirect', 'yutori_ledger_member_login_redirect', 10, 3);

function yutori_ledger_render_private_frontend(): void {
    if (!get_query_var(YUTORI_LEDGER_PRIVATE_QUERY_VAR)) {
        return;
    }

    if (!is_user_logged_in()) {
        auth_redirect();
        exit;
    }

    if (!yutori_ledger_can_use_private()) {
        wp_die(
            esc_html__('You do not have permission to use Life Revolution.', 'life-revolution'),
            esc_html__('Life Revolution', 'life-revolution'),
            array('response' => 403)
        );
    }

    show_admin_bar(false);
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow', true);
    yutori_ledger_enqueue_app('private');
    ?>
    <!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title><?php esc_html_e('Life Revolution', 'life-revolution'); ?></title>
        <style>html,body{margin:0!important;padding:0!important;min-width:320px;background:#f6f7f2!important}body{min-height:100svh;overflow-x:hidden}.life-revolution-private-shell{min-height:100svh}</style>
        <?php wp_head(); ?>
    </head>
    <body class="life-revolution-private-page">
        <main class="life-revolution-private-shell">
            <div class="life-revolution-root yutori-ledger-root" data-life-revolution-root data-yutori-ledger-root></div>
        </main>
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
    exit;
}
add_action('template_redirect', 'yutori_ledger_render_private_frontend', 0);

function yutori_ledger_find_asset($pattern) {
    $files = glob(YUTORI_LEDGER_PATH . 'assets/' . $pattern);

    if (empty($files)) {
        return null;
    }

    return basename($files[0]);
}

function yutori_ledger_enqueue_app($mode = 'private') {
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
            yutori_ledger_config_js($mode),
            'before'
        );
    }
}

function yutori_ledger_config($mode = 'private'): array {
    $is_private = 'private' === $mode && yutori_ledger_can_use_private();
    $user_id = $is_private ? get_current_user_id() : 0;

    return array(
        'assetsUrl' => YUTORI_LEDGER_URL,
        'enableServiceWorker' => false,
        'restUrl' => $is_private ? esc_url_raw(rest_url('life-revolution/v1')) : '',
        'nonce' => $is_private ? wp_create_nonce('wp_rest') : '',
        'userId' => $user_id,
        'hasWordPressStorage' => $is_private,
        'storageKey' => $is_private ? 'yutori-ledger-data-v1-user-' . $user_id : 'life-revolution-public-data-v1',
        'localUpdatedAtKey' => $is_private ? 'life-revolution-local-updated-at-v1-user-' . $user_id : 'life-revolution-public-updated-at-v1',
    );
}

function yutori_ledger_config_js($mode = 'private'): string {
    $config = wp_json_encode(yutori_ledger_config($mode));
    return 'window.LifeRevolutionConfig = ' . $config . '; window.YutoriLedgerConfig = window.LifeRevolutionConfig;';
}

function yutori_ledger_config_script($mode = 'private'): string {
    return '<script>' . yutori_ledger_config_js($mode) . '</script>';
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

    yutori_ledger_enqueue_app('public');

    $extra_class = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $atts['class']);
    $classes = trim('life-revolution-root yutori-ledger-root ' . $extra_class);

    return yutori_ledger_config_script('public') . '<div class="' . esc_attr($classes) . '" data-life-revolution-root data-yutori-ledger-root></div>';
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

function yutori_ledger_mobile_frontend_styles(): void {
    if (!yutori_ledger_is_frontend_app_page()) {
        return;
    }

    echo '<style id="life-revolution-mobile-frontend">@media screen and (max-width:782px){html:root{margin-top:0!important;}body{margin-top:0!important;padding-top:0!important;}#wpadminbar,#header,#fix_header,#breadcrumb,#main_content>.l-mainContent__inner>.c-pageTitle{display:none!important;}#wpadminbar{visibility:hidden!important;opacity:0!important;pointer-events:none!important;transform:translate3d(0,-120%,0)!important;height:0!important;min-height:0!important;overflow:hidden!important;}#content.l-content{display:block!important;width:100%!important;max-width:none!important;margin:0!important;padding:0!important;}#main_content.l-mainContent,#main_content>.l-mainContent__inner,#main_content .post_content{float:none!important;width:100%!important;max-width:none!important;margin:0!important;padding:0!important;}}</style>';
}
add_action('wp_head', 'yutori_ledger_mobile_frontend_styles', PHP_INT_MAX);

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
    return yutori_ledger_can_use_private();
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

function yutori_ledger_register_admin_page() {
    if (!yutori_ledger_can_use_private()) {
        return;
    }

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
        yutori_ledger_enqueue_app('private');

        $mobile_app_css = <<<'CSS'
@media screen and (max-width: 782px) {
    html.wp-toolbar {
        padding-top: 0 !important;
    }

    body.toplevel_page_life-revolution {
        background: #f6f7f2 !important;
        min-width: 320px;
        overflow-x: hidden;
    }

    body.toplevel_page_life-revolution #wpadminbar,
    body.toplevel_page_life-revolution #adminmenumain,
    body.toplevel_page_life-revolution #wpfooter,
    body.toplevel_page_life-revolution #screen-meta,
    body.toplevel_page_life-revolution #screen-meta-links,
    body.toplevel_page_life-revolution .update-nag,
    body.toplevel_page_life-revolution .notice,
    body.toplevel_page_life-revolution .updated,
    body.toplevel_page_life-revolution .error {
        display: none !important;
    }

    body.toplevel_page_life-revolution #wpwrap,
    body.toplevel_page_life-revolution #wpcontent,
    body.toplevel_page_life-revolution #wpbody,
    body.toplevel_page_life-revolution #wpbody-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }

    body.toplevel_page_life-revolution #wpbody-content {
        float: none !important;
        min-height: 100svh;
    }

    body.toplevel_page_life-revolution .life-revolution-admin-page {
        margin: 0 !important;
        padding: 0 !important;
    }

    body.toplevel_page_life-revolution .life-revolution-admin-heading,
    body.toplevel_page_life-revolution .life-revolution-admin-description {
        display: none !important;
    }

    body.toplevel_page_life-revolution .yutori-ledger-root {
        min-height: 100svh;
    }
}
CSS;

        wp_add_inline_style('yutori-ledger-app', $mobile_app_css);
    }
}
add_action('admin_enqueue_scripts', 'yutori_ledger_enqueue_admin_assets');

function yutori_ledger_render_admin_page() {
    if (!yutori_ledger_can_use_private()) {
        wp_die(esc_html__('You do not have permission to use Life Revolution.', 'life-revolution'));
    }

    echo '<div class="wrap life-revolution-admin-page">';
    echo '<h1 class="life-revolution-admin-heading">' . esc_html__('Life Revolution', 'life-revolution') . '</h1>';
    echo '<p class="description life-revolution-admin-description">' . esc_html__('この管理画面のデータはWordPressに保存され、公開版とは分離されています。', 'life-revolution') . '</p>';
    echo yutori_ledger_config_script('private');
    echo '<div class="life-revolution-root yutori-ledger-root" data-life-revolution-root data-yutori-ledger-root></div>';
    echo '</div>';
}
