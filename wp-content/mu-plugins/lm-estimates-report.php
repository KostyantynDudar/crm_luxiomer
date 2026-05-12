<?php
/**
 * Plugin Name: LM Estimates Report
 * Description: CRM estimates report from Workiz JSON files.
 */

if (!defined('ABSPATH')) exit;

function lm_est_money($v) {
    if ($v === '' || $v === null) return '';
    return '$' . number_format((float)$v, 2);
}



function lm_est_mask_address($v) {
    $v = trim((string)$v);
    if ($v === '') return '';

    // Показываем только начало улицы и город/штат/zip, середину скрываем
    $parts = array_map('trim', explode(',', $v));
    if (count($parts) >= 3) {
        $street = $parts[0];
        $city_state = implode(', ', array_slice($parts, 1));

        $street_words = preg_split('/\s+/', $street);
        $safe_street = count($street_words) > 1
            ? $street_words[0] . ' ' . ($street_words[1] ?? '') . ' ***'
            : $street . ' ***';

        return $safe_street . ', ' . $city_state;
    }

    if (strlen($v) <= 10) return '***';
    return substr($v, 0, 8) . ' *** ' . substr($v, -8);
}

function lm_est_mask_phone($v) {
    $digits = preg_replace('/\D+/', '', (string)$v);
    if ($digits === '') return '';
    if (strlen($digits) <= 4) return '***' . $digits;
    return substr($digits, 0, 3) . '***' . substr($digits, -2);
}

function lm_est_mask_email($v) {
    $v = trim((string)$v);
    if ($v === '' || strpos($v, '@') === false) return $v;
    [$name, $domain] = explode('@', $v, 2);
    $first = substr($name, 0, 1);
    $last = strlen($name) > 1 ? substr($name, -1) : '';
    return $first . '***' . $last . '@' . $domain;
}

function lm_est_get($arr, $path, $default = '') {
    $cur = $arr;
    foreach (explode('.', $path) as $key) {
        if (!is_array($cur) || !array_key_exists($key, $cur)) return $default;
        $cur = $cur[$key];
    }
    return $cur;
}

function lm_est_row_from_file($file) {
    $json = json_decode(file_get_contents($file), true);
    if (!is_array($json)) return null;

    $e = $json['estimate'] ?? $json;
    if (!is_array($e)) return null;

    $items = $e['estimate_items'] ?? [];
    $files = $e['files'] ?? [];
    $signatures = $e['signatures'] ?? [];

    $client = trim(($e['client_first_name'] ?? '') . ' ' . ($e['client_last_name'] ?? ''));
    if ($client === '') $client = $e['client_company_name'] ?? $e['fullName'] ?? '';

    return [
        'id' => $e['id'] ?? '',
        'title' => $e['estimate_title'] ?? '',
        'status' => $e['status'] ?? '',
        'client' => $client,
        'phone' => $e['client_phone_number'] ?? $e['phoneNumber'] ?? '',
        'email' => $e['client_email_address'] ?? $e['email_address'] ?? '',
        'address' => $e['clientAddress'] ?? $e['billingLocation'] ?? $e['job_location_key'] ?? '',
        'job' => $e['job_title'] ?? '',
        'job_status' => $e['job_status'] ?? '',
        'created_by_name' => lm_est_get($e, 'job.user_created', ''),
        'created_by_id' => $e['created_by'] ?? '',
        'techs' => $e['tech_names'] ?? '',
        'estimate_date' => $e['estimateDate'] ?? $e['estimate_date'] ?? '',
        'created_date' => $e['createdDate'] ?? $e['created'] ?? '',
        'total' => $e['job_total_price'] ?? '',
        'deposit_due' => $e['deposit_due'] ?? '',
        'paid_total' => $e['paid_total'] ?? '',
        'amount_due' => $e['job_amount_due'] ?? '',
        'items_count' => is_array($items) ? count($items) : 0,
        'has_files' => is_array($files) && count($files) ? 'yes' : 'no',
        'has_signature' => is_array($signatures) && count($signatures) ? 'yes' : 'no',
        'link' => $e['estimate_link'] ?? $e['estimate_portal_url'] ?? '',
        'preview' => [
            'Internal ID' => $e['id'] ?? '',
            'Estimate title' => $e['estimate_title'] ?? '',
            'Estimate number' => $e['estimateId'] ?? '',
            'Status' => $e['status'] ?? '',
            'Status number' => $e['status_number'] ?? '',
            'Client' => $client,
            'Phone' => lm_est_mask_phone($e['client_phone_number'] ?? $e['phoneNumber'] ?? ''),
            'Email' => lm_est_mask_email($e['client_email_address'] ?? $e['email_address'] ?? ''),
            'Address' => lm_est_mask_address($e['clientAddress'] ?? $e['billingLocation'] ?? $e['job_location_key'] ?? ''),
            'Job ID' => $e['job_id'] ?? '',
            'Job serial' => $e['job_serial'] ?? '',
            'Job title' => $e['job_title'] ?? '',
            'Job status' => $e['job_status'] ?? '',
            'Created by' => lm_est_get($e, 'job.user_created', ''),
            'Created by ID' => $e['created_by'] ?? '',
            'Techs' => $e['tech_names'] ?? '',
            'Estimate date' => $e['estimateDate'] ?? $e['estimate_date'] ?? '',
            'Created date' => $e['createdDate'] ?? $e['created'] ?? '',
            'Updated' => $e['updated'] ?? '',
            'Sent' => $e['sent'] ?? '',
            'Last viewed' => $e['last_viewed'] ?? '',
            'Won' => $e['won'] ?? '',
            'Subtotal' => lm_est_money($e['sub_total'] ?? ''),
            'Taxable amount' => lm_est_money($e['taxable_amount'] ?? ''),
            'Tax percent' => $e['tax_precent'] ?? '',
            'Tax amount' => lm_est_money($e['tax_amount'] ?? ''),
            'Total' => lm_est_money($e['job_total_price'] ?? ''),
            'Deposit' => lm_est_money($e['deposit'] ?? ''),
            'Deposit due' => lm_est_money($e['deposit_due'] ?? ''),
            'Paid total' => lm_est_money($e['paid_total'] ?? ''),
            'Amount due' => lm_est_money($e['job_amount_due'] ?? ''),
            'Items count' => is_array($items) ? count($items) : 0,
            'Files count' => is_array($files) ? count($files) : 0,
            'Signatures count' => is_array($signatures) ? count($signatures) : 0,
            'First item' => isset($items[0]) ? (($items[0]['item_name'] ?? $items[0]['description'] ?? '') . ' — ' . lm_est_money($items[0]['total'] ?? '')) : '',
        ],
    ];
}

add_shortcode('lm_estimates_report', function () {
    if (!is_user_logged_in()) {
        return '<p>Please log in.</p>';
    }

    $dir = ABSPATH . 'private/estimates-json';
    $files = glob($dir . '/*.json');

    $rows = [];
    foreach ($files ?: [] as $file) {
        $row = lm_est_row_from_file($file);
        if ($row) $rows[] = $row;
    }

    usort($rows, function($a, $b) {
        return strcmp($b['created_date'], $a['created_date']);
    });

    ob_start();
    ?>
    <style>
      .lm-est{padding:24px;max-width:100%;overflow-x:auto;overflow-y:visible;font-family:Arial,sans-serif;cursor:grab;}
      .lm-est.lm-est--dragging{cursor:grabbing;user-select:none;}
      .lm-est h1{font-size:28px;margin:0 0 8px;}
      .lm-est__meta{margin:0 0 18px;color:#666;}
      .lm-est__table{width:100%;border-collapse:collapse;background:#fff;font-size:13px;}
      .lm-est__table th,.lm-est__table td{border:1px solid #ddd;padding:8px 10px;text-align:left;white-space:nowrap;}
      .lm-est__table th{
        background:#f3f4f6;
        font-weight:800;
        position:sticky;
        top:0;
        z-index:20;
        box-shadow:0 1px 0 #ddd, 0 2px 8px rgba(0,0,0,.08);
      }
      .lm-est__table tr:nth-child(even){background:#fafafa;}
      .lm-est__badge{display:inline-block;padding:3px 8px;border-radius:999px;background:#eef2ff;font-weight:700;}
      .lm-est__money{font-weight:800;}
      .lm-est__link{font-weight:700;}
      .lm-est__preview-trigger{
        cursor:help;
        color:#2563eb;
        font-weight:900;
        border-bottom:1px dotted #2563eb;
      }
      .lm-est__preview-trigger:hover{
        color:#1d4ed8;
      }
      .lm-est-preview{
        display:none;
        position:fixed;
        z-index:999999;
        width:520px;
        max-width:calc(100vw - 32px);
        max-height:70vh;
        overflow:auto;
        background:#fff;
        border:1px solid #cbd5e1;
        border-radius:14px;
        box-shadow:0 18px 50px rgba(15,23,42,.24);
        padding:14px;
        font-size:13px;
      }
      .lm-est-preview__title{
        font-size:16px;
        font-weight:900;
        margin:0 0 10px;
      }
      .lm-est-preview__grid{
        display:grid;
        grid-template-columns:150px 1fr;
        gap:6px 10px;
      }
      .lm-est-preview__k{
        color:#64748b;
        font-weight:800;
      }
      .lm-est-preview__v{
        color:#0f172a;
        word-break:break-word;
      }
    </style>

    <div class="lm-est">
      <h1>Workiz Estimates</h1>
      <p class="lm-est__meta">Total estimates: <?php echo esc_html(count($rows)); ?></p>

      <?php if (!$rows): ?>
        <p>No JSON files found in <code>private/estimates-json/</code></p>
      <?php else: ?>
      <table class="lm-est__table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Status</th>
            <th>Client</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Address</th>
            <th>Job</th>
            <th>Job status</th>
            <th>Created by</th>
            <th>Created by ID</th>
            <th>Techs</th>
            <th>Estimate date</th>
            <th>Created</th>
            <th>Total</th>
            <th>Deposit due</th>
            <th>Paid</th>
            <th>Amount due</th>
            <th>Items</th>
            <th>Files</th>
            <th>Signature</th>
            <th>Link</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <span class="lm-est__preview-trigger" data-preview="<?php echo esc_attr(wp_json_encode($r['preview'])); ?>">
                <?php echo esc_html($r['id']); ?>
              </span>
            </td>
            <td><?php echo esc_html($r['title']); ?></td>
            <td><span class="lm-est__badge"><?php echo esc_html($r['status']); ?></span></td>
            <td><?php echo esc_html($r['client']); ?></td>
            <td><?php echo esc_html(lm_est_mask_phone($r['phone'])); ?></td>
            <td><?php echo esc_html(lm_est_mask_email($r['email'])); ?></td>
            <td><?php echo esc_html(lm_est_mask_address($r['address'])); ?></td>
            <td><?php echo esc_html($r['job']); ?></td>
            <td><?php echo esc_html($r['job_status']); ?></td>
            <td><?php echo esc_html($r['created_by_name']); ?></td>
            <td><?php echo esc_html($r['created_by_id']); ?></td>
            <td><?php echo esc_html($r['techs']); ?></td>
            <td><?php echo esc_html($r['estimate_date']); ?></td>
            <td><?php echo esc_html($r[
'created_date']); ?></td>
            <td class="lm-est__money"><?php echo esc_html(lm_est_money($r['total'])); ?></td>
            <td><?php echo esc_html(lm_est_money($r['deposit_due'])); ?></td>
            <td><?php echo esc_html(lm_est_money($r['paid_total'])); ?></td>
            <td><?php echo esc_html(lm_est_money($r['amount_due'])); ?></td>
            <td><?php echo esc_html($r['items_count']); ?></td>
            <td><?php echo esc_html($r['has_files']); ?></td>
            <td><?php echo esc_html($r['has_signature']); ?></td>
            <td>
              <?php if ($r['link']): ?>
                <a class="lm-est__link" href="<?php echo esc_url($r['link']); ?>" target="_blank" rel="noopener">Open</a>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <div id="lm-est-preview" class="lm-est-preview" aria-hidden="true">
        <div class="lm-est-preview__title">Estimate preview</div>
        <div class="lm-est-preview__grid"></div>
      </div>

      <script>
      (function(){
        const wrap = document.querySelector('.lm-est');
        if (wrap) {
          let down = false;
          let startX = 0;
          let startScrollLeft = 0;

          wrap.addEventListener('mousedown', function(e){
            if (e.button !== 0) return;
            if (e.target.closest('a, .lm-est__preview-trigger, .lm-est-preview')) return;

            down = true;
            startX = e.pageX;
            startScrollLeft = wrap.scrollLeft;
            wrap.classList.add('lm-est--dragging');
          });

          window.addEventListener('mousemove', function(e){
            if (!down) return;
            e.preventDefault();
            wrap.scrollLeft = startScrollLeft - (e.pageX - startX);
          });

          window.addEventListener('mouseup', function(){
            down = false;
            wrap.classList.remove('lm-est--dragging');
          });
        }

        const box = document.getElementById('lm-est-preview');
        if (!box) return;
        const grid = box.querySelector('.lm-est-preview__grid');

        function esc(s){
          return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
          }[c]));
        }

        function place(e){
          const pad = 18;
          let x = e.clientX + pad;
          let y = e.clientY + pad;
          const r = box.getBoundingClientRect();

          if (x + r.width > window.innerWidth - 8) x = e.clientX - r.width - pad;
          if (y + r.height > window.innerHeight - 8) y = window.innerHeight - r.height - 8;

          box.style.left = Math.max(8, x) + 'px';
          box.style.top = Math.max(8, y) + 'px';
        }

        let hideTimer = null;

        function showBox(trigger, e){
          clearTimeout(hideTimer);

          let data = {};
          try { data = JSON.parse(trigger.dataset.preview || '{}'); } catch(err) {}

          grid.innerHTML = Object.entries(data)
            .filter(([k,v]) => v !== null && v !== undefined && String(v) !== '')
            .map(([k,v]) => '<div class="lm-est-preview__k">' + esc(k) + '</div><div class="lm-est-preview__v">' + esc(v) + '</div>')
            .join('');

          box.style.display = 'block';
          box.setAttribute('aria-hidden', 'false');
          place(e);
        }

        function scheduleHide(){
          clearTimeout(hideTimer);
          hideTimer = setTimeout(() => {
            box.style.display = 'none';
            box.setAttribute('aria-hidden', 'true');
          }, 250);
        }

        document.querySelectorAll('.lm-est__preview-trigger[data-preview]').forEach(trigger => {
          trigger.addEventListener('mouseenter', e => showBox(trigger, e));
          trigger.addEventListener('mouseleave', scheduleHide);
        });

        box.addEventListener('mouseenter', () => clearTimeout(hideTimer));
        box.addEventListener('mouseleave', scheduleHide);
      })();
      </script>
    </div>
    <?php
    return ob_get_clean();
});
