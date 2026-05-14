<?php
/**
 * Plugin Name: LM Workiz Data Layer
 */

if (!defined('ABSPATH')) exit;

function lmw_money_float($v) {
    if ($v === null || $v === '') return 0.0;
    return (float) preg_replace('/[^0-9.\-]/', '', (string)$v);
}

function lmw_get($arr, $path, $default = '') {
    $cur = $arr;
    foreach (explode('.', $path) as $key) {
        if (!is_array($cur) || !array_key_exists($key, $cur)) return $default;
        $cur = $cur[$key];
    }
    return $cur;
}

function lmw_mask_phone($v) {
    $digits = preg_replace('/\D+/', '', (string)$v);
    if ($digits === '') return '';
    return strlen($digits) <= 4 ? '***' . $digits : substr($digits, 0, 3) . '***' . substr($digits, -2);
}

function lmw_mask_email($v) {
    $v = trim((string)$v);
    if ($v === '' || strpos($v, '@') === false) return $v;
    [$name, $domain] = explode('@', $v, 2);
    return substr($name, 0, 1) . '***' . (strlen($name) > 1 ? substr($name, -1) : '') . '@' . $domain;
}

function lmw_mask_address($v) {
    $v = trim((string)$v);
    if ($v === '') return '';
    $parts = array_map('trim', explode(',', $v));
    if (count($parts) >= 3) {
        $street_words = preg_split('/\s+/', $parts[0]);
        $safe = count($street_words) > 1 ? $street_words[0] . ' ' . $street_words[1] . ' ***' : $parts[0] . ' ***';
        return $safe . ', ' . implode(', ', array_slice($parts, 1));
    }
    return strlen($v) <= 10 ? '***' : substr($v, 0, 8) . ' *** ' . substr($v, -8);
}

function lmw_workiz_private_dir($sub) {
    return ABSPATH . 'private/' . trim($sub, '/');
}

function lmw_load_estimates($limit = 0) {
    $dir = lmw_workiz_private_dir('estimates-json');
    $files = glob($dir . '/*.json') ?: [];
    $out = [];
    $i = 0;

    foreach ($files as $file) {
        if ($limit && $i >= $limit) break;

        $json = json_decode(file_get_contents($file), true);
        if (!is_array($json)) continue;

        $e = $json['estimate'] ?? $json;
        if (!is_array($e)) continue;

        $items = $e['estimate_items'] ?? [];
        $files_list = $e['files'] ?? [];
        $signatures = $e['signatures'] ?? [];

        $client = trim(($e['client_first_name'] ?? '') . ' ' . ($e['client_last_name'] ?? ''));
        if ($client === '') $client = $e['client_company_name'] ?? $e['fullName'] ?? '';

        $lat = lmw_get($e, 'job.location_pb', '');
        $lng = lmw_get($e, 'job.location_ob', '');

        $out[] = [
            'object_type' => 'estimate',
            'id' => (string)($e['id'] ?? basename($file, '.json')),
            'uuid' => (string)($e['uuid'] ?? lmw_get($e, 'job.uuid', '')),
            'number' => (string)($e['estimateId'] ?? ''),
            'title' => (string)($e['estimate_title'] ?? $e['name'] ?? ''),
            'status' => (string)($e['status'] ?? ''),
            'status_full' => (string)($e['status'] ?? ''),
            'client_name' => $client,
            'company_name' => (string)($e['client_company_name'] ?? ''),
            'phone' => (string)($e['client_phone_number'] ?? $e['phoneNumber'] ?? ''),
            'email' => (string)($e['client_email_address'] ?? $e['email_address'] ?? ''),
            'address' => (string)($e['clientAddress'] ?? $e['billingLocation'] ?? $e['job_location_key'] ?? ''),
            'job_id' => (string)($e['job_id'] ?? ''),
            'job_serial' => (string)($e['job_serial'] ?? ''),
            'job_title' => (string)($e['job_title'] ?? ''),
            'job_status' => (string)($e['job_status'] ?? ''),
            'lead_source' => (string)lmw_get($e, 'job.job_source', ''),
            'created_by_name' => (string)lmw_get($e, 'job.user_created', ''),
            'created_by_id' => (string)($e['created_by'] ?? ''),
            'techs' => (string)($e['tech_names'] ?? ''),
            'created' => (string)($e['createdDate'] ?? $e['created'] ?? ''),
            'updated' => (string)($e['updated'] ?? ''),
            'sent' => (string)($e['sent'] ?? ''),
            'date' => (string)($e['estimateDate'] ?? $e['estimate_date'] ?? ''),
            'total' => lmw_money_float($e['job_total_price'] ?? $e['total'] ?? 0),
            'subtotal' => lmw_money_float($e['sub_total'] ?? 0),
            'tax' => lmw_money_float($e['tax_amount'] ?? 0),
            'paid_total' => lmw_money_float($e['paid_total'] ?? 0),
            'amount_due' => lmw_money_float($e['job_amount_due'] ?? 0),
            'deposit_due' => lmw_money_float($e['deposit_due'] ?? 0),
            'items_count' => is_array($items) ? count($items) : 0,
            'has_files' => is_array($files_list) && count($files_list) > 0,
            'has_signature' => is_array($signatures) && count($signatures) > 0,
            'lat' => $lat,
            'lng' => $lng,
            'url' => (string)($e['estimate_link'] ?? $e['estimate_portal_url'] ?? ''),
            'source_file' => basename($file),
        ];

        $i++;
    }

    return $out;
}

function lmw_load_invoices($limit = 0) {
    $file = lmw_workiz_private_dir('invoices-json/workiz-invoices-index.json');
    if (!file_exists($file)) return [];

    $json = json_decode(file_get_contents($file), true);
    if (!is_array($json)) return [];

    $rows = $json['rows'] ?? [];
    if (!is_array($rows)) return [];

    $out = [];
    $i = 0;

    foreach ($rows as $r) {
        if ($limit && $i >= $limit) break;
        if (!is_array($r)) continue;

        $client = (string)($r['client_full_name'] ?? trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')));

        $out[] = [
            'object_type' => 'invoice',
            'id' => (string)($r['id'] ?? ''),
            'uuid' => (string)($r['uuid'] ?? ''),
            'number' => (string)($r['invoice_id_interval'] ?? $r['serialId'] ?? ''),
            'title' => (string)($r['invoice_name'] ?? ''
),
            'status' => (string)($r['status'] ?? ''),
            'status_full' => (string)($r['invoice_status'] ?? $r['status'] ?? ''),
            'client_name' => $client,
            'company_name' => (string)($r['client_company_name'] ?? ''),
            'phone' => (string)($r['primary_phone'] ?? ''),
            'email' => (string)($r['email_address'] ?? ''),
            'address' => '',
            'job_id' => (string)($r['job_id'] ?? ''),
            'job_serial' => (string)($r['job_serial'] ?? $r['job'] ?? ''),
            'job_title' => (string)($r['job_name'] ?? ''),
            'job_status' => '',
            'lead_source' => '',
            'created_by_name' => '',
            'created_by_id' => '',
            'techs' => '',
            'created' => (string)($r['created'] ?? ''),
            'updated' => '',
            'sent' => (string)($r['sent'] ?? ''),
            'date' => (string)($r['created'] ?? ''),
            'total' => lmw_money_float($r['job_total_price'] ?? 0),
            'subtotal' => lmw_money_float($r['job_sub_total'] ?? 0),
            'tax' => lmw_money_float($r['job_tax'] ?? 0),
            'paid_total' => max(0, lmw_money_float($r['job_total_price'] ?? 0) - lmw_money_float($r['job_amount_due'] ?? 0)),
            'amount_due' => lmw_money_float($r['job_amount_due'] ?? 0),
            'deposit_due' => 0,
            'items_count' => 0,
            'has_files' => false,
            'has_signature' => false,
            'lat' => '',
            'lng' => '',
            'url' => !empty($r['uuid']) ? 'https://app.workiz.com/root/invoice/' . rawurlencode($r['uuid']) : '',
            'source_file' => 'workiz-invoices-index.json',
        ];

        $i++;
    }

    return $out;
}

function lmw_load_all_workiz_objects($limit_each = 0) {
    return array_merge(
        lmw_load_estimates($limit_each),
        lmw_load_invoices($limit_each)
    );
}
