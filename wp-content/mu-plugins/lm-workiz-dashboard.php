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

add_shortcode('lm_workiz_dashboard', function () {
    if (!is_user_logged_in()) {
        return '<p>Please log in.</p>';
    }

    $estimates = lmw_load_estimates();
    $invoices  = lmw_load_invoices();
    $map_items = lmw_dashboard_public_objects($estimates);

    wp_enqueue_style('lmw-leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
    wp_enqueue_script('lmw-leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);

    wp_enqueue_style(
        'lm-workiz-dashboard',
        content_url('mu-plugins/assets/lm-workiz-dashboard.css'),
        [],
        '0.2.0'
    );

    wp_enqueue_script(
        'lm-workiz-dashboard',
        content_url('mu-plugins/assets/lm-workiz-dashboard.js'),
        ['lmw-leaflet-js'],
        '0.2.0',
        true
    );

    wp_add_inline_script(
        'lm-workiz-dashboard',
        'window.LMW_WORKIZ_MAP_OBJECTS = ' . wp_json_encode($map_items) . ';',
        'before'
    );

    ob_start();
    ?>
    <div class="lmw-dashboard">
      <h1>Workiz Estimates & Invoices Analytics</h1>

      <div class="lmw-kpis">
        <div><b><?php echo esc_html(count($estimates)); ?></b><span>Estimates</span></div>
        <div><b><?php echo esc_html(count($invoices)); ?></b><span>Invoices</span></div>
        <div><b><?php echo esc_html(count($map_items)); ?></b><span>Map points</span></div>
      </div>

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
                <tr>
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
                <tr>
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
