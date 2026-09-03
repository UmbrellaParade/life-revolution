<?php
/**
 * Plugin Name: Life Revolution Family
 * Description: Combines two private Life Revolution ledgers into a household dashboard.
 * Version: 0.2.0
 * Author: Umbrella Parade
 * License: GPL-2.0-or-later
 * Text Domain: life-revolution-family
 * Update URI: https://github.com/UmbrellaParade/life-revolution
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LIFE_REVOLUTION_FAMILY_VERSION', '0.2.0');
define('LIFE_REVOLUTION_FAMILY_CAPABILITY', 'view_life_revolution_family');
define('LIFE_REVOLUTION_FAMILY_MEMBERS_OPTION', 'life_revolution_family_members_v1');
define('LIFE_REVOLUTION_FAMILY_VERSION_OPTION', 'life_revolution_family_installed_version');
define('LIFE_REVOLUTION_FAMILY_MEMBER_ROLE', 'life_revolution_member');
define('LIFE_REVOLUTION_FAMILY_QUERY_VAR', 'life_revolution_family_private');

function life_revolution_family_ensure_capability(): void {
    $administrator = get_role('administrator');
    if ($administrator && !$administrator->has_cap(LIFE_REVOLUTION_FAMILY_CAPABILITY)) {
        $administrator->add_cap(LIFE_REVOLUTION_FAMILY_CAPABILITY);
    }

    $member_role = get_role(LIFE_REVOLUTION_FAMILY_MEMBER_ROLE);
    if ($member_role && !$member_role->has_cap(LIFE_REVOLUTION_FAMILY_CAPABILITY)) {
        $member_role->add_cap(LIFE_REVOLUTION_FAMILY_CAPABILITY);
    }

    update_option(
        LIFE_REVOLUTION_FAMILY_VERSION_OPTION,
        LIFE_REVOLUTION_FAMILY_VERSION,
        false
    );
}

function life_revolution_family_activate(): void {
    life_revolution_family_ensure_capability();
    life_revolution_family_register_private_route();
    flush_rewrite_rules(false);
}
register_activation_hook(__FILE__, 'life_revolution_family_activate');

function life_revolution_family_maybe_upgrade(): void {
    if (get_option(LIFE_REVOLUTION_FAMILY_VERSION_OPTION) !== LIFE_REVOLUTION_FAMILY_VERSION) {
        life_revolution_family_ensure_capability();
        life_revolution_family_register_private_route();
        flush_rewrite_rules(false);
    }
}
add_action('admin_init', 'life_revolution_family_maybe_upgrade');

function life_revolution_family_can_view(): bool {
    if (!is_user_logged_in()) {
        return false;
    }

    $user = wp_get_current_user();
    $is_member = in_array(LIFE_REVOLUTION_FAMILY_MEMBER_ROLE, (array) $user->roles, true);

    return $is_member
        || current_user_can(LIFE_REVOLUTION_FAMILY_CAPABILITY)
        || current_user_can('manage_options');
}

function life_revolution_family_register_menu(): void {
    if (!life_revolution_family_can_view()) {
        return;
    }

    add_menu_page(
        __('夫婦合算', 'life-revolution-family'),
        __('夫婦合算', 'life-revolution-family'),
        'read',
        'life-revolution-family',
        'life_revolution_family_render_page',
        'dashicons-groups',
        59
    );
}
add_action('admin_menu', 'life_revolution_family_register_menu');

function life_revolution_family_register_private_route(): void {
    add_rewrite_rule(
        '^my-life-revolution-family/?$',
        'index.php?' . LIFE_REVOLUTION_FAMILY_QUERY_VAR . '=1',
        'top'
    );
}
add_action('init', 'life_revolution_family_register_private_route');

function life_revolution_family_query_vars(array $query_vars): array {
    $query_vars[] = LIFE_REVOLUTION_FAMILY_QUERY_VAR;
    return $query_vars;
}
add_filter('query_vars', 'life_revolution_family_query_vars');

function life_revolution_family_private_url(): string {
    return home_url('/my-life-revolution-family/');
}

function life_revolution_family_login_redirect($redirect_to, $requested_redirect_to, $user) {
    if (!$user instanceof WP_User || !in_array(LIFE_REVOLUTION_FAMILY_MEMBER_ROLE, (array) $user->roles, true)) {
        return $redirect_to;
    }

    $requested = wp_validate_redirect((string) $requested_redirect_to, '');
    if ($requested && strpos($requested, life_revolution_family_private_url()) === 0) {
        return $requested;
    }

    return $redirect_to;
}
add_filter('login_redirect', 'life_revolution_family_login_redirect', 20, 3);

function life_revolution_family_render_private_frontend(): void {
    if (!get_query_var(LIFE_REVOLUTION_FAMILY_QUERY_VAR)) {
        return;
    }

    if (!is_user_logged_in()) {
        auth_redirect();
        exit;
    }

    if (!life_revolution_family_can_view()) {
        wp_die(
            esc_html__('この画面を表示する権限がありません。', 'life-revolution-family'),
            esc_html__('Life Revolution Family', 'life-revolution-family'),
            array('response' => 403)
        );
    }

    show_admin_bar(false);
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow', true);
    ?>
    <!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title><?php esc_html_e('Life Revolution Family', 'life-revolution-family'); ?></title>
        <style>html,body{margin:0!important;padding:0!important;min-width:320px;background:#f6f7f2!important}body{min-height:100svh;overflow-x:hidden}.lrf-private-shell{min-height:100svh}</style>
        <?php wp_head(); ?>
    </head>
    <body class="life-revolution-family-private-page">
        <main class="lrf-private-shell">
            <?php life_revolution_family_render_page(); ?>
        </main>
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
    exit;
}
add_action('template_redirect', 'life_revolution_family_render_private_frontend', 0);

function life_revolution_family_member_ids(): array {
    $stored = get_option(LIFE_REVOLUTION_FAMILY_MEMBERS_OPTION, array());
    $ids = is_array($stored) ? array_map('absint', $stored) : array();
    $ids = array_values(array_unique(array_filter($ids, 'get_userdata')));

    if (empty($ids) && get_current_user_id() > 0) {
        $ids[] = get_current_user_id();
    }

    return array_slice($ids, 0, 2);
}

function life_revolution_family_save_members(): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('この設定を変更する権限がありません。', 'life-revolution-family'));
    }

    check_admin_referer('life_revolution_family_save_members');

    $submitted = isset($_POST['member_ids']) && is_array($_POST['member_ids'])
        ? wp_unslash($_POST['member_ids'])
        : array();
    $member_ids = array_values(array_unique(array_filter(array_map('absint', $submitted))));
    $member_ids = array_values(array_filter($member_ids, 'get_userdata'));
    $member_ids = array_slice($member_ids, 0, 2);

    update_option(LIFE_REVOLUTION_FAMILY_MEMBERS_OPTION, $member_ids, false);

    wp_safe_redirect(
        add_query_arg(
            array(
                'page' => 'life-revolution-family',
                'family-updated' => '1',
            ),
            admin_url('admin.php')
        )
    );
    exit;
}
add_action('admin_post_life_revolution_family_save_members', 'life_revolution_family_save_members');

function life_revolution_family_number($value): float {
    return is_numeric($value) ? max(0, (float) $value) : 0.0;
}

function life_revolution_family_money($value): string {
    return '￥' . number_format_i18n((int) round((float) $value));
}

function life_revolution_family_state_meta_key(): string {
    return defined('YUTORI_LEDGER_STATE_META_KEY')
        ? YUTORI_LEDGER_STATE_META_KEY
        : 'life_revolution_state_v1';
}

function life_revolution_family_updated_meta_key(): string {
    return defined('YUTORI_LEDGER_STATE_UPDATED_META_KEY')
        ? YUTORI_LEDGER_STATE_UPDATED_META_KEY
        : 'life_revolution_state_updated_at_v1';
}

function life_revolution_family_state(int $user_id): array {
    $state = get_user_meta($user_id, life_revolution_family_state_meta_key(), true);
    return is_array($state) ? $state : array();
}

function life_revolution_family_month(): string {
    $month = isset($_GET['lr_month']) ? sanitize_text_field(wp_unslash($_GET['lr_month'])) : '';
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : wp_date('Y-m');
}

function life_revolution_family_shift_month(string $month, int $offset): string {
    $date = DateTimeImmutable::createFromFormat('!Y-m', $month, wp_timezone());
    if (!$date) {
        return wp_date('Y-m');
    }

    return $date->modify(($offset >= 0 ? '+' : '') . $offset . ' month')->format('Y-m');
}

function life_revolution_family_summary(int $user_id, string $month): array {
    $user = get_userdata($user_id);
    $state = life_revolution_family_state($user_id);
    $expenses = isset($state['expenses']) && is_array($state['expenses']) ? $state['expenses'] : array();
    $fixed_costs = isset($state['fixedCosts']) && is_array($state['fixedCosts']) ? $state['fixedCosts'] : array();
    $loans = isset($state['loans']) && is_array($state['loans']) ? $state['loans'] : array();
    $settings = isset($state['settings']) && is_array($state['settings']) ? $state['settings'] : array();
    $savings_goals = isset($state['savingsGoals']) && is_array($state['savingsGoals']) ? $state['savingsGoals'] : array();

    $expense_total = 0.0;
    $expense_count = 0;
    $categories = array();
    foreach ($expenses as $expense) {
        if (!is_array($expense) || strpos((string) ($expense['date'] ?? ''), $month) !== 0) {
            continue;
        }

        $amount = life_revolution_family_number($expense['amount'] ?? 0);
        $category = trim((string) ($expense['category'] ?? __('その他', 'life-revolution-family')));
        $category = $category !== '' ? $category : __('その他', 'life-revolution-family');
        $expense_total += $amount;
        $expense_count++;
        $categories[$category] = ($categories[$category] ?? 0) + $amount;
    }

    $fixed_total = 0.0;
    $fixed_genres = array();
    foreach ($fixed_costs as $fixed_cost) {
        if (!is_array($fixed_cost) || empty($fixed_cost['active'])) {
            continue;
        }

        $funded_months = isset($fixed_cost['fundedMonths']) && is_array($fixed_cost['fundedMonths'])
            ? $fixed_cost['fundedMonths']
            : array();
        if (in_array($month, $funded_months, true)) {
            continue;
        }

        $amount = life_revolution_family_number($fixed_cost['amount'] ?? 0);
        $genre = trim((string) ($fixed_cost['genre'] ?? __('その他', 'life-revolution-family')));
        $genre = $genre !== '' ? $genre : __('その他', 'life-revolution-family');
        $fixed_total += $amount;
        $fixed_genres[$genre] = ($fixed_genres[$genre] ?? 0) + $amount;
    }

    $loan_payment_total = 0.0;
    $debt_total = 0.0;
    foreach ($loans as $loan) {
        if (!is_array($loan)) {
            continue;
        }

        $debt_total += life_revolution_family_number($loan['balance'] ?? 0);
        $debt_total += life_revolution_family_number($loan['fee'] ?? 0);
        $funded_months = isset($loan['fundedMonths']) && is_array($loan['fundedMonths'])
            ? $loan['fundedMonths']
            : array();
        if (!in_array($month, $funded_months, true)) {
            $loan_payment_total += life_revolution_family_number($loan['monthlyPayment'] ?? 0);
            $loan_payment_total += life_revolution_family_number($loan['extraPayment'] ?? 0);
        }
    }

    $saved_total = 0.0;
    foreach ($savings_goals as $goal) {
        if (is_array($goal)) {
            $saved_total += life_revolution_family_number($goal['savedAmount'] ?? 0);
        }
    }

    $income = life_revolution_family_number($settings['monthlyIncome'] ?? 0);
    $buffer = life_revolution_family_number($settings['bufferTarget'] ?? 0);
    $planned_outflow = $expense_total + $fixed_total + $loan_payment_total + $buffer;

    arsort($categories);
    arsort($fixed_genres);

    return array(
        'user_id' => $user_id,
        'name' => $user ? $user->display_name : sprintf(__('ユーザー %d', 'life-revolution-family'), $user_id),
        'has_data' => !empty($state),
        'updated_at' => (string) get_user_meta($user_id, life_revolution_family_updated_meta_key(), true),
        'income' => $income,
        'expense_total' => $expense_total,
        'expense_count' => $expense_count,
        'fixed_total' => $fixed_total,
        'loan_payment_total' => $loan_payment_total,
        'buffer' => $buffer,
        'planned_outflow' => $planned_outflow,
        'remaining' => $income - $planned_outflow,
        'debt_total' => $debt_total,
        'saved_total' => $saved_total,
        'categories' => $categories,
        'fixed_genres' => $fixed_genres,
    );
}

function life_revolution_family_combine(array $summaries): array {
    $combined = array(
        'income' => 0.0,
        'expense_total' => 0.0,
        'expense_count' => 0,
        'fixed_total' => 0.0,
        'loan_payment_total' => 0.0,
        'buffer' => 0.0,
        'planned_outflow' => 0.0,
        'remaining' => 0.0,
        'debt_total' => 0.0,
        'saved_total' => 0.0,
        'categories' => array(),
        'fixed_genres' => array(),
    );

    foreach ($summaries as $summary) {
        foreach (array('income', 'expense_total', 'fixed_total', 'loan_payment_total', 'buffer', 'planned_outflow', 'remaining', 'debt_total', 'saved_total') as $key) {
            $combined[$key] += (float) $summary[$key];
        }
        $combined['expense_count'] += (int) $summary['expense_count'];

        foreach ($summary['categories'] as $category => $amount) {
            $combined['categories'][$category] = ($combined['categories'][$category] ?? 0) + $amount;
        }
        foreach ($summary['fixed_genres'] as $genre => $amount) {
            $combined['fixed_genres'][$genre] = ($combined['fixed_genres'][$genre] ?? 0) + $amount;
        }
    }

    arsort($combined['categories']);
    arsort($combined['fixed_genres']);
    return $combined;
}

function life_revolution_family_render_breakdown(string $title, array $values, float $total): void {
    echo '<section class="lrf-panel">';
    echo '<div class="lrf-panel-heading"><h2>' . esc_html($title) . '</h2><strong>' . esc_html(life_revolution_family_money($total)) . '</strong></div>';
    if (empty($values)) {
        echo '<p class="lrf-empty">' . esc_html__('まだ集計データがありません。', 'life-revolution-family') . '</p>';
    } else {
        echo '<div class="lrf-breakdown">';
        foreach ($values as $label => $amount) {
            $percentage = $total > 0 ? min(100, max(0, ($amount / $total) * 100)) : 0;
            echo '<div class="lrf-breakdown-row">';
            echo '<div><span>' . esc_html($label) . '</span><strong>' . esc_html(life_revolution_family_money($amount)) . '</strong></div>';
            echo '<span class="lrf-bar" aria-hidden="true"><i style="width:' . esc_attr((string) round($percentage, 1)) . '%"></i></span>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</section>';
}

function life_revolution_family_render_page(): void {
    if (!life_revolution_family_can_view()) {
        wp_die(esc_html__('この画面を表示する権限がありません。', 'life-revolution-family'));
    }

    $month = life_revolution_family_month();
    $can_manage = current_user_can('manage_options');
    $is_private_frontend = (bool) get_query_var(LIFE_REVOLUTION_FAMILY_QUERY_VAR);
    $member_ids = life_revolution_family_member_ids();
    $summaries = array_map(
        static function ($user_id) use ($month) {
            return life_revolution_family_summary((int) $user_id, $month);
        },
        $member_ids
    );
    $combined = life_revolution_family_combine($summaries);
    $users = $can_manage ? get_users(array('orderby' => 'display_name', 'order' => 'ASC')) : array();
    $base_url = $is_private_frontend
        ? life_revolution_family_private_url()
        : add_query_arg('page', 'life-revolution-family', admin_url('admin.php'));
    $previous_url = add_query_arg('lr_month', life_revolution_family_shift_month($month, -1), $base_url);
    $next_url = add_query_arg('lr_month', life_revolution_family_shift_month($month, 1), $base_url);
    ?>
    <div class="wrap lrf-wrap">
        <style>
            .lrf-wrap{max-width:1080px;color:#16231f;letter-spacing:0}.lrf-title-row{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin:22px 0 18px}.lrf-title-row h1{margin:0;font-size:28px;line-height:1.25}.lrf-title-row p{margin:4px 0 0;color:#5d6b66}.lrf-month-control{display:grid;grid-template-columns:44px minmax(180px,320px) 44px;align-items:end;justify-content:center;gap:10px;margin:0 0 20px}.lrf-month-control a{display:grid;place-items:center;width:42px;height:42px;border:1px solid #ccd6d1;border-radius:6px;background:#fff;color:#175f4c;text-decoration:none}.lrf-month-control label{display:grid;gap:5px;font-weight:700}.lrf-month-control input{height:42px;border-color:#b8c6c0}.lrf-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:20px}.lrf-metric{padding:18px;border:1px solid #dbe2de;border-radius:8px;background:#fff;box-shadow:0 5px 18px rgba(24,49,40,.05)}.lrf-metric span{display:block;color:#64716c;font-weight:650;font-size:13px}.lrf-metric strong{display:block;margin-top:8px;font-size:25px;line-height:1.15}.lrf-metric-primary{background:#edf8f3;border-color:#b9dbce}.lrf-metric-warn strong{color:#a13c3c}.lrf-members{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:20px}.lrf-member{border:1px solid #dbe2de;border-radius:8px;background:#fff;padding:18px}.lrf-member-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}.lrf-member-header h2{margin:0;font-size:19px}.lrf-member-header small{color:#6d7874}.lrf-member dl{display:grid;grid-template-columns:1fr auto;gap:9px 12px;margin:0}.lrf-member dt{color:#5f6d68}.lrf-member dd{margin:0;font-weight:750}.lrf-panels{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:20px}.lrf-panel{border-top:3px solid #21775f;background:#fff;padding:18px;border-radius:0 0 8px 8px}.lrf-panel-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}.lrf-panel-heading h2{margin:0;font-size:18px}.lrf-panel-heading strong{font-size:18px}.lrf-breakdown{display:grid;gap:13px}.lrf-breakdown-row>div{display:flex;justify-content:space-between;gap:12px;margin-bottom:5px}.lrf-bar{display:block;height:7px;background:#edf1ef;border-radius:4px;overflow:hidden}.lrf-bar i{display:block;height:100%;background:#d1b45d}.lrf-empty{color:#727d79}.lrf-settings{background:#fff;border:1px solid #dbe2de;border-radius:8px;padding:0 18px}.lrf-settings summary{cursor:pointer;font-weight:750;padding:17px 0}.lrf-settings form{display:grid;gap:13px;padding:0 0 18px}.lrf-member-selects{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.lrf-member-selects label{display:grid;gap:5px;font-weight:700}.lrf-member-selects select{width:100%;max-width:none}.lrf-help{color:#5f6d68;margin:0}.lrf-notice{padding:12px 14px;border-left:4px solid #d1b45d;background:#fff9df;margin-bottom:16px}.lrf-settings-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap}@media(max-width:782px){html.wp-toolbar{padding-top:0!important}body.toplevel_page_life-revolution-family{background:#f6f7f2!important;min-width:320px;overflow-x:hidden}body.toplevel_page_life-revolution-family #wpadminbar,body.toplevel_page_life-revolution-family #adminmenumain,body.toplevel_page_life-revolution-family #wpfooter,body.toplevel_page_life-revolution-family #screen-meta,body.toplevel_page_life-revolution-family #screen-meta-links,body.toplevel_page_life-revolution-family .update-nag,body.toplevel_page_life-revolution-family .notice,body.toplevel_page_life-revolution-family .updated,body.toplevel_page_life-revolution-family .error{display:none!important}body.toplevel_page_life-revolution-family #wpwrap,body.toplevel_page_life-revolution-family #wpcontent,body.toplevel_page_life-revolution-family #wpbody,body.toplevel_page_life-revolution-family #wpbody-content{margin:0!important;padding:0!important;width:100%!important}body.toplevel_page_life-revolution-family #wpbody-content{float:none!important;min-height:100svh}.lrf-wrap{margin:0;padding:14px 14px 90px}.lrf-title-row{margin-top:0}.lrf-title-row h1{font-size:23px}.lrf-setup-notice{display:none}.lrf-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.lrf-members,.lrf-panels,.lrf-member-selects{grid-template-columns:1fr}.lrf-metric{padding:14px}.lrf-metric strong{font-size:21px}.lrf-month-control{grid-template-columns:42px minmax(0,1fr) 42px}}
        </style>

        <div class="lrf-title-row">
            <div>
                <h1><?php esc_html_e('Life Revolution Family', 'life-revolution-family'); ?></h1>
                <p><?php esc_html_e('夫婦それぞれの家計簿を、世帯全体として確認します。', 'life-revolution-family'); ?></p>
            </div>
        </div>

        <?php if (isset($_GET['family-updated'])) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('集計するメンバーを保存しました。', 'life-revolution-family'); ?></p></div>
        <?php endif; ?>

        <?php if (!defined('YUTORI_LEDGER_STATE_META_KEY')) : ?>
            <div class="lrf-notice"><?php esc_html_e('Life Revolution本体が有効か確認してください。保存済みデータがある場合は集計を続けます。', 'life-revolution-family'); ?></div>
        <?php endif; ?>

        <?php if (count($member_ids) < 2) : ?>
            <div class="lrf-notice lrf-setup-notice"><?php esc_html_e('現在は1人分です。下の「夫婦メンバー設定」で奥さまのWordPressアカウントを選ぶと合算が始まります。', 'life-revolution-family'); ?></div>
        <?php endif; ?>

        <form class="lrf-month-control" method="get" action="<?php echo esc_url($base_url); ?>">
            <?php if (!$is_private_frontend) : ?>
                <input type="hidden" name="page" value="life-revolution-family">
            <?php endif; ?>
            <a href="<?php echo esc_url($previous_url); ?>" aria-label="<?php esc_attr_e('前の月', 'life-revolution-family'); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></a>
            <label>
                <span><?php esc_html_e('表示月', 'life-revolution-family'); ?></span>
                <input type="month" name="lr_month" value="<?php echo esc_attr($month); ?>" onchange="this.form.submit()">
            </label>
            <a href="<?php echo esc_url($next_url); ?>" aria-label="<?php esc_attr_e('次の月', 'life-revolution-family'); ?>"><span class="dashicons dashicons-arrow-right-alt2"></span></a>
        </form>

        <section class="lrf-summary-grid" aria-label="<?php esc_attr_e('夫婦合算サマリー', 'life-revolution-family'); ?>">
            <article class="lrf-metric lrf-metric-primary"><span><?php esc_html_e('世帯収入', 'life-revolution-family'); ?></span><strong><?php echo esc_html(life_revolution_family_money($combined['income'])); ?></strong></article>
            <article class="lrf-metric"><span><?php esc_html_e('登録支出', 'life-revolution-family'); ?></span><strong><?php echo esc_html(life_revolution_family_money($combined['expense_total'])); ?></strong></article>
            <article class="lrf-metric"><span><?php esc_html_e('固定費・返済', 'life-revolution-family'); ?></span><strong><?php echo esc_html(life_revolution_family_money($combined['fixed_total'] + $combined['loan_payment_total'])); ?></strong></article>
            <article class="lrf-metric <?php echo $combined['remaining'] < 0 ? 'lrf-metric-warn' : ''; ?>"><span><?php esc_html_e('見込み残り', 'life-revolution-family'); ?></span><strong><?php echo esc_html(life_revolution_family_money($combined['remaining'])); ?></strong></article>
        </section>

        <section class="lrf-members" aria-label="<?php esc_attr_e('個人別内訳', 'life-revolution-family'); ?>">
            <?php foreach ($summaries as $summary) : ?>
                <article class="lrf-member">
                    <div class="lrf-member-header">
                        <h2><?php echo esc_html($summary['name']); ?></h2>
                        <small><?php echo $summary['has_data'] ? esc_html(sprintf(__('%d件', 'life-revolution-family'), $summary['expense_count'])) : esc_html__('未入力', 'life-revolution-family'); ?></small>
                    </div>
                    <dl>
                        <dt><?php esc_html_e('収入', 'life-revolution-family'); ?></dt><dd><?php echo esc_html(life_revolution_family_money($summary['income'])); ?></dd>
                        <dt><?php esc_html_e('登録支出', 'life-revolution-family'); ?></dt><dd><?php echo esc_html(life_revolution_family_money($summary['expense_total'])); ?></dd>
                        <dt><?php esc_html_e('固定費', 'life-revolution-family'); ?></dt><dd><?php echo esc_html(life_revolution_family_money($summary['fixed_total'])); ?></dd>
                        <dt><?php esc_html_e('返済', 'life-revolution-family'); ?></dt><dd><?php echo esc_html(life_revolution_family_money($summary['loan_payment_total'])); ?></dd>
                        <dt><?php esc_html_e('見込み残り', 'life-revolution-family'); ?></dt><dd><?php echo esc_html(life_revolution_family_money($summary['remaining'])); ?></dd>
                    </dl>
                </article>
            <?php endforeach; ?>
        </section>

        <div class="lrf-panels">
            <?php life_revolution_family_render_breakdown(__('支出カテゴリ合算', 'life-revolution-family'), $combined['categories'], $combined['expense_total']); ?>
            <?php life_revolution_family_render_breakdown(__('固定費ジャンル合算', 'life-revolution-family'), $combined['fixed_genres'], $combined['fixed_total']); ?>
        </div>

        <?php if ($can_manage) : ?>
            <details class="lrf-settings" <?php echo count($member_ids) < 2 ? 'open' : ''; ?>>
                <summary><?php esc_html_e('夫婦メンバー設定', 'life-revolution-family'); ?></summary>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="life_revolution_family_save_members">
                    <?php wp_nonce_field('life_revolution_family_save_members'); ?>
                    <div class="lrf-member-selects">
                        <?php for ($index = 0; $index < 2; $index++) : ?>
                            <label>
                                <span><?php echo esc_html($index === 0 ? __('メンバー1', 'life-revolution-family') : __('メンバー2', 'life-revolution-family')); ?></span>
                                <select name="member_ids[]">
                                    <option value="0"><?php esc_html_e('選択してください', 'life-revolution-family'); ?></option>
                                    <?php foreach ($users as $user) : ?>
                                        <option value="<?php echo esc_attr((string) $user->ID); ?>" <?php selected($member_ids[$index] ?? 0, $user->ID); ?>><?php echo esc_html($user->display_name . '（' . $user->user_login . '）'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <p class="lrf-help"><?php esc_html_e('奥さま用アカウントは「ユーザー → 新規追加」で、権限グループを「Life Revolution利用者」にして作成します。', 'life-revolution-family'); ?></p>
                    <div class="lrf-settings-actions">
                        <?php submit_button(__('メンバーを保存', 'life-revolution-family'), 'primary', 'submit', false); ?>
                        <a href="<?php echo esc_url(admin_url('user-new.php')); ?>"><?php esc_html_e('奥さま用アカウントを追加', 'life-revolution-family'); ?></a>
                    </div>
                </form>
            </details>
        <?php endif; ?>
    </div>
    <?php
}
