<?php
/*
Plugin Name: CRM Shell
Description: Minimal shell for CRM login and service selection.
Version: 1.0.0
*/

if (!defined('ABSPATH')) exit;

add_filter('show_admin_bar', function ($show) {
    return current_user_can('manage_options') ? $show : false;
});

add_action('template_redirect', function () {
    if (is_admin()) return;
    if (wp_doing_ajax()) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;

    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = trim((string) parse_url($request_uri, PHP_URL_PATH), '/');

    // Главная "/" и "/login" => только логин
    if ($path === '' || $path === 'login') {
        if (is_user_logged_in()) {
            wp_safe_redirect(home_url('/crm-home/'));
            exit;
        }

        crm_shell_render_login();
        exit;
    }

    // Промежуточная страница выбора сервиса
    if ($path === 'crm-home') {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        crm_shell_render_home();
        exit;
    }
});

function crm_shell_render_login() {
    status_header(200);
    nocache_headers();

    $error = '';
    if (!empty($_GET['login']) && $_GET['login'] === 'failed') {
        $error = 'Wrong username or password.';
    }

    ?>
    <!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>CRM Login</title>
        <style>
            html, body {
                margin: 0 0 6px;
                padding: 0;
                min-height: 100%;
                background: #0f172a;
                color: #e5e7eb;
                font-family: Arial, sans-serif;
            }

            .crm-wrap {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                box-sizing: border-box;
            }

            .crm-card {
                width: 100%;
                max-width: 420px;
                background: #111827;
                border: 1px solid #1f2937;
                border-radius: 18px;
                padding: 32px;
                box-sizing: border-box;
                box-shadow: 0 20px 60px rgba(0,0,0,.35);
            }

            .crm-logo {
                font-size: 44px;
                font-weight: 800;
                letter-spacing: .14em;
                text-align: center;
                margin: 0 0 8px;
                color: #ffffff;
            }

            .crm-sub {
                font-size: 14px;
                color: #94a3b8;
                text-align: center;
                margin: 0 0 28px;
            }

            .crm-error {
                background: #2a1320;
                border: 1px solid #7f1d1d;
                color: #fecaca;
                padding: 12px 14px;
                border-radius: 10px;
                margin: 0 0 16px;
                font-size: 14px;
            }

            .crm-card label {
                display: block;
                font-size: 13px;
                margin: 0 0 8px;
                color: #cbd5e1;
            }

            .crm-card input[type="text"],
            .crm-card input[type="password"] {
                width: 100%;
                box-sizing: border-box;

                background: #0b1220;
                color: #ffffff;
                border: 1px solid #334155;
                border-radius: 10px;
                padding: 12px 14px;
                margin: 0 0 16px;
                outline: none;
            }

            .crm-card input[type="text"]:focus,
            .crm-card input[type="password"]:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59,130,246,.18);
            }

            .crm-card input[type="submit"] {
                width: 100%;
                background: #2563eb;
                color: #ffffff;
                border: 0;
                border-radius: 10px;
                padding: 12px 14px;
                font-weight: 700;
                font-size: 14px;
                cursor: pointer;
            }

            .crm-card input[type="submit"]:hover {
                background: #1d4ed8;
            }

            .login-remember,
            .login-lost-password {
                display: none !important;
            }

            .crm-footer-note {
                margin-top: 18px;
                text-align: center;
                color: #64748b;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="crm-wrap">
            <div class="crm-card">
                <div class="crm-logo">CRM</div>
                <div class="crm-sub">Luxiomer</div>

                <?php if ($error): ?>
                    <div class="crm-error"><?php echo esc_html($error); ?></div>
                <?php endif; ?>

                <?php
                wp_login_form([
                    'echo' => true,
                    'redirect' => home_url('/crm-home/'),
                    'label_username' => 'Username',
                    'label_password' => 'Password',
                    'label_log_in'   => 'Log in',
                    'remember'       => false,
                ]);
                ?>

                <div class="crm-footer-note">Internal access only</div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function crm_shell_render_home() {
    status_header(200);
    nocache_headers();

    $current_user = wp_get_current_user();
    $name = $current_user && !empty($current_user->user_login) ? $current_user->user_login : 'user';

    ?>
    <!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>CRM Home</title>
        <style>
            html, body {
                margin: 0;
                padding: 0;
                min-height: 100%;
                background: #0f172a;
                color: #ffffff;
                font-family: Arial, sans-serif;
            }

            .crm-home-wrap {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                box-sizing: border-box;
            }

            .crm-home-card {
                width: 100%;
                max-width: 560px;
                background: #111827;
                border: 1px solid #1f2937;
                border-radius: 22px;
                padding: 42px 32px;
                box-sizing: border-box;
                box-shadow: 0 20px 60px rgba(0,0,0,.35);
                text-align: center;
            }

            .crm-home-top {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                margin-bottom: 24px;
                font-size: 13px;
                color: #94a3b8;
            }

            .crm-home-logout a {
                color: #cbd5e1;
                text-decoration: none;
            }

            .crm-home-logout a:hover {
                color: #ffffff;
            }

            .crm-big-logo {
                font-size: 64px;
                font-weight: 900;
                letter-spacing: .18em;
                margin: 6px 0 14px;
            }

            .crm-home-sub {
                color: #94a3b8;
                font-size: 15px;
                margin-bottom: 28px;
            }

            .crm-service-row {
                display: flex;
                justify-content: center;
                gap: 18px;
                flex-wrap: wrap;
            }

            .crm-service-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 220px;
                min-height: 110px;
                padding: 18px 30px;
                box-sizing: border-box;
                border-radius: 22px;
                background: #2563eb;
                color: #ffffff;
                text-decoration: none;
                font-size: 26px;
                font-weight: 800;
                letter-spacing: .12em;
                box-shadow: 0 14px 30px rgba(37,99,235,.28);
            }

            .crm-service-btn:hover {
                background: #1d4ed8;
            }
        </style>
    </head>
    <body>
        <div class="crm-home-wrap">
            <div class="crm-home-card">
                <div class="crm-home-top">
                    <div>Logged in as: <?php echo esc_html($name); ?></div>
                    <div class="crm-home-logout">
                        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Log out</a>
                    </div>
                </div>

                <div class="crm-big-logo">CRM</div>
                <div class="crm-home-sub">Choose a service</div>

                <div class="crm-service-row">
                    <a class="crm-service-btn" href="<?php echo esc_url(home_url('/dispetcher/')); ?>">DISPETCHER</a>
                    <a class="crm-service-btn" href="<?php echo esc_url(home_url('/estimates/')); ?>">ESTIMATES</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
