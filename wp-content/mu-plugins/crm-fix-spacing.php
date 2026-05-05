<?php
/*
Plugin Name: CRM Fix Spacing
*/
if (!defined('ABSPATH')) exit;

add_action('wp_head', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

    if (!in_array($path, ['dispetcher','dispetcher-admin'], true)) return;
    ?>
    <style>
        .wp-block-group.has-global-padding {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }

        .wp-block-group.is-layout-constrained {
            margin-top: 0 !important;
        }
    </style>
    <?php
}, 999);
