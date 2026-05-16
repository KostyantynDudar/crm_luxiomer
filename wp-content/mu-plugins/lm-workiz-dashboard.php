<?php
/**
 * Plugin Name: LM Workiz Dashboard
 */

if (!defined('ABSPATH')) exit;

function lmw_dashboard_public_objects($items) {
    $out = [];

    foreach ($items as $r) {
        $lat = trim((string)($r['lat'] ?? ''));
        $lng = trim((string)($r['lng'] ?? ''));

        if ($lat === '' || $lng === '') continue;

        $out[] = [
            'type' => $r['object_type'],
            'id' => $r['id'],
            'number' => $r['number'],
            'title' => $r['title'],
            'status' => $r['status_full'] ?: $r['status'],
            'client' => $r['client_name'],
            'address' => lmw_mask_address($r['address']),
            'job' => $r['job_title'] ?: $r['job_serial'],
            'created_by' => $r['created_by_name'],
            'techs' => $r['techs'],
            'total' => number_format((float)$r['total'], 2),
            'due' => number_format((float)$r['amount_due'], 2),
            'date' => $r['date'],
            'url' => $r['url'],
            'lat' => (float)$lat,
            'lng' => (float)$lng,
        ];
    }

    return $out;
}


function lmw_dashboard_status_group($r) {
    $status = strtolower((string)(($r['status_full'] ?? '') ?: ($r['status'] ?? '')));
    $due = (float)($r['amount_due'] ?? 0);
    $paid = (float)($r['paid_total'] ?? 0);

    if (preg_match('/overdue/', $status)) return 'overdue';
    if (preg_match('/won|paid|completed/', $status) || ($paid > 0 && $due <= 0)) return 'won-paid';
    if (preg_match('/lost|declin|cancel/', $status)) return 'lost';
    if (preg_match('/unsent|not sent|draft/', $status)) return 'unsent';
    if ($due > 0) return 'unpaid';

    return 'other';
}

add_shortcode('lm_workiz_dashboard', function () {
    if (!is_user_logged_in()) {
        return '<p>Please log in.</p>';
    }

    $estimates = lmw_load_estimates();
    $invoices  = lmw_load_invoices();
    $map_items = lmw_dashboard_public_objects(array_merge($estimates, $invoices));

    wp_enqueue_style('lmw-leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
    wp_enqueue_script('lmw-leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);

    wp_enqueue_style(
        'lm-workiz-dashboard',
        content_url('mu-plugins/assets/lm-workiz-dashboard.css'),
        [],
        '0.2.1'
    );

    wp_enqueue_script(
        'lm-workiz-dashboard',
        content_url('mu-plugins/assets/lm-workiz-dashboard.js'),
        ['lmw-leaflet-js'],
        '0.2.1',
        true
    );

    wp_add_inline_script(
        'lm-workiz-dashboard',
        'window.LMW_WORKIZ_MAP_OBJECTS = ' . wp_json_encode($map_items) . ';',
        'before'
    );

    ob_start();
    ?>
    <div class="lmw-dashboard" style="width:calc(100vw - 48px)!important;max-width:calc(100vw - 48px)!important;margin-left:calc(50% - 50vw + 24px)!important;margin-right:calc(50% - 50vw + 24px)!important;">
      <h1>Workiz Estimates & Invoices Analytics</h1>

      <div class="lmw-kpis">
        <div><b><?php echo esc_html(count($estimates)); ?></b><span>Estimates</span></div>
        <div><b><?php echo esc_html(count($invoices)); ?></b><span>Invoices</span></div>
        <div><b><?php echo esc_html(count($map_items)); ?></b><span>Map points</span></div>
      </div>
      <section class="lmw-quick">
        <button type="button" data-quick="">All</button>
        <button type="button" data-quick="estimate">Estimates</button>
        <button type="button" data-quick="invoice">Invoices</button>
        <button type="button" data-quick="won-paid">Won / Paid</button>
        <button type="button" data-quick="due">Has Due</button>
        <button type="button" data-quick="overdue">Overdue</button>
        <button type="button" data-quick="mapped">Mapped</button>
        <button type="button" data-quick="no-geo">No Geo</button>
      </section>

      <section class="lmw-filters">
        <input id="lmw-search" type="search" placeholder="Search client, ID, job, address...">

        <select id="lmw-type">
          <option value="">All types</option>
          <option value="estimate">Estimates</option>
          <option value="invoice">Invoices</option>
        </select>

        <select id="lmw-status-group">
          <option value="">All status groups</option>
          <option value="won-paid">Won / Paid</option>
          <option value="unpaid">Unpaid / Has Due</option>
          <option value="overdue">Overdue</option>
          <option value="lost">Lost / Declined</option>
          <option value="unsent">Unsent / Not sent</option>
        </select>

        <select id="lmw-status">
          <option value="">All statuses</option>
        </select>

        <select id="lmw-manager">
          <option value="">All managers</option>
        </select>

        <select id="lmw-tech">
          <option value="">All techs</option>
        </select>

        <select id="lmw-source">
          <option value="">All sources</option>
        </select>

        <select id="lmw-geo">
          <option value="">All geo matches</option>
        </select>

        <input id="lmw-total-min" type="number" min="0" step="100" placeholder="Total from">
        <input id="lmw-total-max" type="number" min="0" step="100" placeholder="Total to">
      </section>

      <section class="lmw-map-panel">
        <h2>Map</h2>
        <div id="lmw-map"></div>
      </section>

      <div class="lmw-grid">
        <section class="lmw-panel">
          <h2>Estimates</h2>
          <div class="lmw-table-wrap">
            <table class="lmw-table">
              <thead>
                <tr>
                  <th>ID</th><th>Status</th><th>Client</th><th>Phone</th><th>Email</th><th>Address</th><th>Total</th><th>Due</th><th>Created</th><th>Link</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($estimates as $r): ?>
                <tr data-type="estimate" data-status="<?php echo esc_attr($r['status']); ?>" data-status-group="<?php echo esc_attr(lmw_dashboard_status_group($r)); ?>" data-manager="<?php echo esc_attr($r['created_by_name']); ?>" data-tech="<?php echo esc_attr($r['techs']); ?>" data-source="<?php echo esc_attr($r['lead_source']); ?>" data-geo="original" data-total="<?php echo esc_attr($r['total']); ?>" data-due="<?php echo esc_attr($r['amount_due']); ?>" data-search="<?php echo esc_attr(strtolower($r['id'].' '.$r['client_name'].' '.$r['address'].' '.$r['job_title'].' '.$r['status'].' '.$r['created_by_name'].' '.$r['techs'].' '.$r['lead_source'])); ?>">
                  <td><?php echo esc_html($r['id']); ?></td>
                  <td><?php echo esc_html($r['status']); ?></td>
                  <td><?php echo esc_html($r['client_name']); ?></td>
                  <td><?php echo esc_html(lmw_mask_phone($r['phone'])); ?></td>
                  <td><?php echo esc_html(lmw_mask_email($r['email'])); ?></td>
                  <td><?php echo esc_html(lmw_mask_address($r['address'])); ?></td>
                  <td>$<?php echo esc_html(number_format($r['total'], 2)); ?></td>
                  <td>$<?php echo esc_html(number_format($r['amount_due'], 2)); ?></td>
                  <td><?php echo esc_html($r['created']); ?></td>
                  <td><?php echo $r['url'] ? '<a href="'.esc_url($r['url']).'" target="_blank" rel="noopener">Open</a>' : '—'; ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <section class="lmw-panel">
          <h2>Invoices</h2>
          <div class="lmw-table-wrap">
            <table class="lmw-table">
              <thead>
                <tr>
                  <th>ID</th><th>No</th><th>Status</th><th>Client</th><th>Phone</th><th>Email</th><th>Job</th><th>Total</th><th>Due</th><th>Sent</th><th>Link</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($invoices as $r): ?>
                <tr data-type="invoice" data-status="<?php echo esc_attr($r['status_full']); ?>" data-status-group="<?php echo esc_attr(lmw_dashboard_status_group($r)); ?>" data-manager="<?php echo esc_attr($r['created_by_name']); ?>" data-tech="<?php echo esc_attr($r['techs']); ?>" data-source="<?php echo esc_attr($r['lead_source']); ?>" data-geo="<?php echo esc_attr($r['geo_match_type'] ?? 'no_geo'); ?>" data-total="<?php echo esc_attr($r['total']); ?>" data-due="<?php echo esc_attr($r['amount_due']); ?>" data-search="<?php echo esc_attr(strtolower($r['id'].' '.$r['number'].' '.$r['client_name'].' '.$r['job_serial'].' '.$r['status_full'].' '.$r['email'].' '.$r['phone'].' '.$r['created_by_name'].' '.$r['techs'].' '.$r['lead_source'])); ?>">
                  <td><?php echo esc_html($r['id']); ?></td>
                  <td><?php echo esc_html($r['number']); ?></td>
                  <td><?php echo esc_html($r['status_full']); ?></td>
                  <td><?php echo esc_html($r['client_name']); ?></td>
                  <td><?php echo esc_html(lmw_mask_phone($r['phone'])); ?></td>
                  <td><?php echo esc_html(lmw_mask_email($r['email'])); ?></td>
                  <td><?php echo esc_html($r['job_serial']); ?></td>
                  <td>$<?php echo esc_html(number_format($r['total'], 2)); ?></td>
                  <td>$<?php echo esc_html(number_format($r['amount_due'], 2)); ?></td>
                  <td><?php echo esc_html($r['sent']); ?></td>
                  <td><?php echo $r['url'] ? '<a href="'.esc_url($r['url']).'" target="_blank" rel="noopener">Open</a>' : '—'; ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
    <?php
    return ob_get_clean();
});
