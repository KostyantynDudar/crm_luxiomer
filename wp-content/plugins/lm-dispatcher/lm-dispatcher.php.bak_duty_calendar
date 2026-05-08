<?php
/**
 * Plugin Name: LM Dispatcher
 * Description: Internal dispatcher board: staff statuses (online/offline) + comment + time in status + admin analytics.
 * Version: 0.2.0
 */

if (!defined('ABSPATH')) exit;

class LM_Dispatcher {
  const META_STATUS     = 'lm_disp_status';
  const META_COMMENT    = 'lm_disp_comment';
  const META_CHANGED_AT = 'lm_disp_changed_at';

  const ONLINE_TTL_SEC = 36000; // 10 hours
  const STATUS_WISHLIST_LEAD = 'wishlist_lead';
  const BUSINESS_TZ = 'America/Los_Angeles';
  const WORKDAY_START_HOUR = 9; // 09:00 LA
  const META_WORKDAY_KEY = 'lm_disp_workday_key';
  const META_FIRST_ACTIVE_AT = 'lm_disp_first_active_at';
  const VIEWER_LOGIN = 'viewer';

  // --- Analytics / Event log
  const DB_VERSION   = 1;
  const TABLE_SUFFIX = 'lm_disp_events';

  // Luxiomer business day (change later if needed)
  public static function table_name() {
    global $wpdb;
    return $wpdb->prefix . self::TABLE_SUFFIX;
  }

  public static function agents() {
    // Sписок РОВНО как ты дал (диспетчер + 7 человек)
    return apply_filters('lm_dispatcher_agents', [

      'cs' => [
        'title' => 'Customer service specialists',
        'members' => ['ilya','alina','katya'],
      ],
      'sales' => [
        'title' => 'Sales representatives',
        'members' => ['dima','karine','michelle','adrian'],
      ],
  'sales_support' => [
    'title'   => 'Sales support',
    'members' => ['artem'],
  ],

    ]);
  }

  public static function is_allowed_user($user_id = 0) {
    $user = $user_id ? get_user_by('id', $user_id) : wp_get_current_user();
    if (!$user || !$user->ID) return false;

    $login = strtolower($user->user_login);

// viewer: доступ к странице есть, но он не участник таблицы
if ($login === self::VIEWER_LOGIN) return true;

    foreach (self::agents() as $group) {
      foreach (($group['members'] ?? []) as $member_login) {
        if ($member_login === $login) return true;
      }
    }
    return false;
  }

public static function is_cs_login($login) {
  $agents = self::agents();
  $cs = $agents['cs']['members'] ?? [];
  return in_array($login, $cs, true);
}

public static function is_cs_user($user_id) {
  $u = get_user_by('id', $user_id);
  if (!$u || !$u->ID) return false;
  return self::is_cs_login($u->user_login);
}

public static function is_sales_login($login) {
  $agents = self::agents();
  $sales = $agents['sales']['members'] ?? [];
  return in_array($login, $sales, true);
}

public static function is_sales_user($user_id) {
  $u = get_user_by('id', $user_id);
  if (!$u || !$u->ID) return false;
  return self::is_sales_login($u->user_login);
}


/**
 * Возвращает user_id текущего Showroom lead (только в Sales) или 0.
 * Если вдруг из-за гонки оказалось 2 лидера — оставляем самого “нового”, остальных понижаем до online.
 */

public static function get_current_wishlist_lead_id() {
  $agents = self::agents();
  $sales_logins = $agents['sales']['members'] ?? [];
  $found = [];

  foreach ($sales_logins as $login) {
    $u = self::get_user_by_login_safe($login);
    if (!$u) continue;

    $st = self::normalize_status(get_user_meta($u->ID, self::META_STATUS, true));
    if ($st === self::STATUS_WISHLIST_LEAD) {
      $changed_at = (int) get_user_meta($u->ID, self::META_CHANGED_AT, true);
      $found[] = ['user_id' => (int)$u->ID, 'changed_at' => $changed_at];
    }
  }

  if (count($found) === 0) return 0;
  if (count($found) === 1) return (int)$found[0]['user_id'];

  // если несколько — оставим самого нового, остальных демотим в online
  usort($found, function($a,$b){ return ($b['changed_at'] <=> $a['changed_at']); });
  $keep = (int)$found[0]['user_id'];

  for ($i=1; $i<count($found); $i++) {
    $uid = (int)$found[$i]['user_id'];
    update_user_meta($uid, self::META_STATUS, 'online');
    update_user_meta($uid, self::META_CHANGED_AT, time());
  }

  return $keep;
}

public static function status_label($status) {
  $s = self::normalize_status($status);
  if ($s === self::STATUS_WISHLIST_LEAD) return 'Showroom lead';
  return $s; // online/offline
}


  public static function is_admin_allowed() {
    // admin analytics access
    return current_user_can('manage_options') || current_user_can('lm_dispatcher_admin');
  }

  public static function get_user_by_login_safe($login) {
    $u = get_user_by('login', $login);
    return $u ?: null;
  }

private static function normalize_status($status) {
  $s = (string)$status;
  if ($s === 'online') return 'online';
  if ($s === self::STATUS_WISHLIST_LEAD) return self::STATUS_WISHLIST_LEAD;
  return 'offline';
}


  /**
   * Workday boundary: 09:00 America/Los_Angeles.
   * If now < 09:00, boundary is yesterday 09:00.
   */
  private static function workday_start_ts() {
    try { $tz = new DateTimeZone(self::BUSINESS_TZ); }
    catch (Throwable $e) { $tz = new DateTimeZone('UTC'); }

    $now = new DateTimeImmutable('now', $tz);
    $today = $now->format('Y-m-d');
    $start_today = new DateTimeImmutable($today . ' ' . sprintf('%02d:00:00', self::WORKDAY_START_HOUR), $tz);

    if ($now < $start_today) {
      $day = $now->modify('-1 day')->format('Y-m-d');
      $start = new DateTimeImmutable($day . ' ' . sprintf('%02d:00:00', self::WORKDAY_START_HOUR), $tz);
      return $start->getTimestamp();
    }
    return $start_today->getTimestamp();
  }


  /**
   * Workday key starts at 09:00 America/Los_Angeles.
   * Returns: [workday_key 'YYYY-MM-DD', workday_start_ts]
   */
  private static function workday_key_and_start_ts() {
    try {
      $tz = new DateTimeZone(self::BUSINESS_TZ);
    } catch (Throwable $e) {
      $tz = new DateTimeZone('UTC');
    }

    $now = new DateTimeImmutable('now', $tz);
    $today = $now->format('Y-m-d');

    $start_today = new DateTimeImmutable($today . ' ' . sprintf('%02d:00:00', self::WORKDAY_START_HOUR), $tz);

    if ($now < $start_today) {
      $day = $now->modify('-1 day')->format('Y-m-d');
      $start = new DateTimeImmutable($day . ' ' . sprintf('%02d:00:00', self::WORKDAY_START_HOUR), $tz);
      return [$day, $start->getTimestamp()];
    }

    return [$today, $start_today->getTimestamp()];
  }


  // --- Activation / DB

  public static function activate() {
    self::maybe_create_table(true);
    self::ensure_admin_capability();
  }

  private static function ensure_admin_capability() {
    // 1) Add capability to Administrator role
    $role = get_role('administrator');
    if ($role && !$role->has_cap('lm_dispatcher_admin')) {
      $role->add_cap('lm_dispatcher_admin');
    }

    // 2) Optional: create a dedicated role (safe; if exists, WP ignores)
    if (!get_role('dispatcher_admin')) {
      add_role('dispatcher_admin', 'Dispatcher Admin', [
        'read' => true,
        'lm_dispatcher_admin' => true,
      ]);
    }
  }

  public static function maybe_create_table($force = false) {
    $opt = (int) get_option('lm_disp_db_ver', 0);
    if (!$force && $opt >= self::DB_VERSION) return;

    global $wpdb;
    $table = self::table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      event_type VARCHAR(32) NOT NULL,
      ts INT UNSIGNED NOT NULL,
      from_status VARCHAR(16) NULL,
      to_status VARCHAR(16) NULL,
      source VARCHAR(32) NULL,
      comment_text VARCHAR(190) NULL,
      extra LONGTEXT NULL,
      PRIMARY KEY  (id),
      KEY user_ts (user_id, ts),
      KEY type_ts (event_type, ts)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option('lm_disp_db_ver', self::DB_VERSION, true);

    // Bootstrap baseline events once (so analytics isn't blank on day-1)
    self::maybe_bootstrap_events();
  }

  private static function maybe_bootstrap_events() {
    // runs once per DB version
    $done = (int) get_option('lm_disp_bootstrap_done', 0);
    if ($done === self::DB_VERSION) return;

    global $wpdb;
    $table = self::table_name();

    foreach (self::agents() as $group) {
      foreach (($group['members'] ?? []) as $login) {
        $u = self::get_user_by_login_safe($login);
        if (!$u) continue;

        $has_any = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id=%d LIMIT 1", $u->ID));
        if ($has_any) continue;

        // ensure we have meta; do NOT create comment events
        $st = self::get_state($u->ID);
        self::log_event('status_change', $u->ID, [
          'ts'          => (int) ($st['changed_at'] ?? time()),
          'from_status' => null,
          'to_status'   => self::normalize_status($st['status'] ?? 'offline'),
          'source'      => 'bootstrap',
        ]);
      }
    }

    update_option('lm_disp_bootstrap_done', self::DB_VERSION, true);
  }

  private static function log_event($event_type, $user_id, $args = []) {
    global $wpdb;

    $table = self::table_name();
    $data = [
      'user_id'     => (int) $user_id,
      'event_type'  => sanitize_key($event_type),
      'ts'          => isset($args['ts']) ? (int)$args['ts'] : time(),
      'from_status' => isset($args['from_status']) ? sanitize_text_field((string)$args['from_status']) : null,
      'to_status'   => isset($args['to_status'])   ? sanitize_text_field((string)$args['to_status'])   : null,
      'source'      => isset($args['source'])      ? sanitize_key((string)$args['source'])             : null,
      'comment_text'=> isset($args['comment_text'])? sanitize_text_field((string)$args['comment_text']): null,
      'extra'       => isset($args['extra'])       ? wp_json_encode($args['extra'])                    : null,
    ];

    // hard-limit comment length (db column is 190)
    if (is_string($data['comment_text']) && strlen($data['comment_text']) > 190) {
      $data['comment_text'] = substr($data['comment_text'], 0, 190);
    }

    // Insert; ignore failures silently (analytics must never break UX)
    try {
      $wpdb->insert($table, $data);
    } catch (Throwable $e) {
      // noop
    }
  }

  private static function log_status_change($user_id, $from, $to, $source, $extra = null) {
    $payload = [
      'from_status' => self::normalize_status($from),
      'to_status'   => self::normalize_status($to),
      'source'      => $source,
    ];
    if (is_array($extra)) {
      $payload['extra'] = $extra;
    }
    self::log_event('status_change', $user_id, $payload);
  }

  private static function log_comment_save($user_id, $comment, $source = 'ui') {
    self::log_event('comment_save', $user_id, [
      'source' => $source,
      'comment_text' => $comment,
    ]);
  }

  public static function ensure_initialized($user_id) {
    $status = get_user_meta($user_id, self::META_STATUS, true);
    $changed_at = (int) get_user_meta($user_id, self::META_CHANGED_AT, true);

    $normalized = self::normalize_status($status);
    if ($status !== $normalized) {
      update_user_meta($user_id, self::META_STATUS, $normalized);
    }
    if ($changed_at <= 0) {
      update_user_meta($user_id, self::META_CHANGED_AT, time());
    }
  }

      public static function get_state($user_id) {
    self::ensure_initialized($user_id);

    $status = self::normalize_status(get_user_meta($user_id, self::META_STATUS, true));
    $comment = get_user_meta($user_id, self::META_COMMENT, true);
    $changed_at = (int) get_user_meta($user_id, self::META_CHANGED_AT, true);
    $now = time();

    // safety: Showroom lead allowed only for Sales; auto-demote others
    if ($status === self::STATUS_WISHLIST_LEAD && !self::is_sales_user($user_id)) {
      $status = 'online';
      update_user_meta($user_id, self::META_STATUS, 'online');
      if ($changed_at <= 0) {
        update_user_meta($user_id, self::META_CHANGED_AT, $now);
        $changed_at = $now;
      }
    }

    // AUTO-OFFLINE after 10h online/lead
    if (($status === 'online' || $status === self::STATUS_WISHLIST_LEAD) && $changed_at > 0 && ($now - $changed_at) >= self::ONLINE_TTL_SEC) {
      self::log_event('auto_offline', $user_id, [
        'source' => 'auto_ttl',
        'extra'  => ['reason' => 'ttl_10h'],
      ]);
      self::log_status_change($user_id, $status, 'offline', 'auto_ttl', ['reason' => 'ttl_10h']);

      $status = 'offline';
      update_user_meta($user_id, self::META_STATUS, 'offline');
      update_user_meta($user_id, self::META_CHANGED_AT, $now);
      $changed_at = $now;
    }

    // Queue: first time user became active (OFF -> Online/Lead) within current workday
    $first_active_at = (int) get_user_meta($user_id, self::META_FIRST_ACTIVE_AT, true);
    $workday_start = self::workday_start_ts();
    if ($first_active_at > 0 && $first_active_at < $workday_start) {
      update_user_meta($user_id, self::META_FIRST_ACTIVE_AT, 0);
      $first_active_at = 0;
    }

    return [
      'status' => $status,
      'comment' => is_string($comment) ? $comment : '',
      'changed_at' => $changed_at > 0 ? $changed_at : time(),
      'first_active_at' => (int) $first_active_at,
    ];
  }



  /**
   * Частичное обновление:
   * - status можно отправить отдельно (без comment)
   * - comment можно отправить отдельно (без status)
   */
    public static function update_state_partial($user_id, $status_or_null, $comment_or_null) {
    self::ensure_initialized($user_id);

    $current_status = self::normalize_status(get_user_meta($user_id, self::META_STATUS, true));

    if ($status_or_null !== null) {
      $new_status = self::normalize_status($status_or_null);

      // Showroom lead: only Sales and only one at a time
      if ($new_status === self::STATUS_WISHLIST_LEAD) {
        if (!self::is_sales_user($user_id)) {
          return new WP_Error('forbidden', 'showroom lead is allowed only for Sales');
        }
        $lead_id = self::get_current_wishlist_lead_id();
        if ($lead_id && (int)$lead_id !== (int)$user_id) {
          return new WP_Error('lead_taken', 'showroom lead is already taken', ['lead_user_id' => (int)$lead_id]);
        }
      }

      if ($new_status !== $current_status) {
        $now = time();

        update_user_meta($user_id, self::META_STATUS, $new_status);
        update_user_meta($user_id, self::META_CHANGED_AT, $now);

        // analytics event
        self::log_status_change($user_id, $current_status, $new_status, 'ui');

        // queue rank: first OFF -> active (Online/Showroom lead) in current workday (Sales only)
        if ($current_status === 'offline' && $new_status !== 'offline' && self::is_sales_user($user_id)) {
          $workday_start = self::workday_start_ts();
          $first = (int) get_user_meta($user_id, self::META_FIRST_ACTIVE_AT, true);
          if ($first <= 0 || $first < $workday_start) {
            update_user_meta($user_id, self::META_FIRST_ACTIVE_AT, $now);
          }
        }

        $current_status = $new_status;
      }
    }

    if ($comment_or_null !== null) {
      $comment = sanitize_text_field((string)$comment_or_null);
      update_user_meta($user_id, self::META_COMMENT, $comment);

      // analytics event
      self::log_comment_save($user_id, $comment, 'ui');
    }

    return true;
  }




  // --- Shortcodes

  public static function register_shortcode() {
    add_shortcode('lm_dispatcher', [__CLASS__, 'shortcode']);
    add_shortcode('lm_dispatcher_admin', [__CLASS__, 'shortcode_admin']);
  }

  public static function shortcode() {
    if (!is_user_logged_in()) {
      return '<div class="lm-disp lm-disp--denied">Login required.</div>';
    }

    // staff OR admin can view
    if (!self::is_allowed_user() && !self::is_admin_allowed()) {
      return '<div class="lm-disp lm-disp--denied">Access denied.</div>';
    }

    $current = wp_get_current_user();
    $agents = self::agents();

$lead_id = self::get_current_wishlist_lead_id(); // кто сейчас Showroom lead (user_id) или 0


    ob_start();
    ?>
    <div class="lm-disp" id="lm-disp" data-current-user="<?php echo esc_attr($current->ID); ?>">
      <?php foreach ($agents as $group_key => $group): ?>
        <h3 class="lm-disp__group"><?php echo esc_html($group['title'] ?? $group_key); ?></h3>

        <table class="lm-disp__table" data-group="<?php echo esc_attr($group_key); ?>">
          <thead>
            <tr>
              <th>User</th>
              <th>Status</th>
              <th>In status</th>
              <th>Comment</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach (($group['members'] ?? []) as $login):
            $u = self::get_user_by_login_safe($login);
            if (!$u): ?>
              <tr class="lm-disp__row lm-disp__row--missing">
                <td><?php echo esc_html($login); ?> <span class="lm-disp__muted">(user not found)</span></td>
                <td>—</td><td>—</td><td>—</td><td></td>
              </tr>
            <?php
              continue;
            endif;

            $state = self::get_state($u->ID);
            $is_me = ((int)$u->ID === (int)$current->ID);
            ?>
            <tr class="lm-disp__row"
                data-user-id="<?php echo esc_attr($u->ID); ?>"
                data-changed-at="<?php echo esc_attr($state['changed_at']); ?>">
              <td class="lm-disp__user">
                <span class="lm-disp__user-name"><?php echo esc_html($u->display_name ?: $u->user_login); ?></span>
                <?php if ($group_key === 'sales'): ?> <span class="lm-disp__rank" data-field="rank"></span><?php endif; ?>
              </td>

              <td class="lm-disp__status">
                <?php if ($is_me): ?>
                  <!-- Статус меняется БЕЗ кнопки Save (авто) -->
                  <select class="lm-disp__select" data-field="status">
  <option value="offline" <?php selected($state['status'], 'offline'); ?>>OFF</option>
  <option value="online" <?php selected($state['status'], 'online'); ?>>Online</option>

  <?php if ($group_key === 'sales'): ?>
    <?php
      // lead занят кем-то другим → нельзя выбрать
      $lead_taken = ($lead_id && (int)$lead_id !== (int)$current->ID);
      $disable_lead = ($lead_taken && $state['status'] !== self::STATUS_WISHLIST_LEAD);
    ?>
    <option value="<?php echo esc_attr(self::STATUS_WISHLIST_LEAD); ?>"
      <?php selected($state['status'], self::STATUS_WISHLIST_LEAD); ?>
      <?php echo $disable_lead ? 'disabled' : ''; ?>
    >Showroom lead</option>
  <?php endif; ?>
</select>


                <?php else: ?>
<span class="lm-disp__badge lm-disp__badge--<?php echo esc_attr($state['status']); ?>" data-field="status_badge">
  <?php
    $st = self::normalize_status($state['status']);
echo esc_html(
  $st === 'offline' ? 'OFF' :
  ($st === 'online' ? 'Online' : 'Showroom lead')
);

  ?>
</span>

                <?php endif; ?>
              </td>

              <td class="lm-disp__timer" data-field="timer">—</td>

              <td class="lm-disp__comment">
                <?php if ($is_me): ?>
                  <input class="lm-disp__input" data-field="comment"
                         value="<?php echo esc_attr($state['comment']); ?>"
                         placeholder="e.g. back in 10 min" maxlength="120"/>
                <?php else: ?>
                  <span data-field="comment"><?php echo esc_html($state['comment']); ?></span>
                <?php endif; ?>
              </td>

              <td class="lm-disp__actions">
                <?php if ($is_me): ?>
                  <!-- Save только для комментария -->
                  <button class="lm-disp__btn" data-action="save-comment">Save</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
  }

  public static function shortcode_admin() {
    if (!is_user_logged_in()) {
      return '<div class="lm-disp lm-disp--denied">Login required.</div>';
    }
    if (!self::is_admin_allowed()) {
      return '<div class="lm-disp lm-disp--denied">Access denied.</div>';
    }

    // default date in BUSINESS_TZ
    $tz = new DateTimeZone(self::BUSINESS_TZ);
    $today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');

    ob_start();
    ?>
    <div class="lm-disp-admin" id="lm-disp-admin" data-default-date="<?php echo esc_attr($today); ?>">
      <div class="lm-disp-admin__controls">
        <label class="lm-disp-admin__label">Date
          <input class="lm-disp-admin__date" type="date" data-field="date" value="<?php echo esc_attr($today); ?>" />
        </label>

        <label class="lm-disp-admin__label">Group
          <select class="lm-disp__select" data-field="group">
            <option value="all">all</option>
            <?php foreach (self::agents() as $key => $g): ?>
              <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($g['title'] ?? $key); ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <button class="lm-disp__btn" data-action="refresh-stats">Refresh</button>
        <span class="lm-disp-admin__meta" data-field="meta"></span>
      </div>

      <div class="lm-disp-admin__table-wrap">
        <table class="lm-disp-admin__table">
          <thead>
            <tr>
              <th>User</th>
<th>Status</th>
              <th>Online</th>
              <th>Sessions</th>
              <th>Comments</th>
              <th>Longest</th>
              <th>Avg</th>
              <th class="lm-disp-admin__th-timeline">
  <div class="lm-disp-admin__timeline-head">
    <div>Timeline</div>
    <div class="lm-disp-admin__axis" aria-hidden="true">
      <span>00</span><span>06</span><span>12</span><span>18</span><span>24</span>
    </div>
  </div>
</th>
            </tr>
          </thead>
          <tbody data-field="tbody">
            <tr><td colspan="7">Loading…</td></tr>
          </tbody>
        </table>
      </div>

      <div class="lm-disp-admin__hint">
        Day boundary: <strong><?php echo esc_html(self::BUSINESS_TZ); ?></strong>. Storage: events are in UTC, displayed per day in business TZ.
      </div>
    </div>
    <?php
    return ob_get_clean();
  }

  // --- Assets

  public static function enqueue_assets() {
    if (!is_user_logged_in()) return;

    $should = is_page('dispetcher') || is_page('dispetcher-admin');

    global $post;
    if (!$should && $post) {
      if (has_shortcode($post->post_content, 'lm_dispatcher') || has_shortcode($post->post_content, 'lm_dispatcher_admin')) {
        $should = true;
      }
    }
    if (!$should) return;

    $base_dir = plugin_dir_path(__FILE__);

    // поддерживаем обе структуры: /assets/* и "в корне"
    $css_rel = file_exists($base_dir . 'assets/dispatcher.css') ? 'assets/dispatcher.css' : 'dispatcher.css';
    $js_rel  = file_exists($base_dir . 'assets/dispatcher.js')  ? 'assets/dispatcher.js'  : 'dispatcher.js';

    $css_abs = $base_dir . $css_rel;
    $js_abs  = $base_dir . $js_rel;

    // cache-bust: реальная дата изменения файла
    $css_ver = file_exists($css_abs) ? (string) filemtime($css_abs) : '1';
    $js_ver  = file_exists($js_abs)  ? (string) filemtime($js_abs)  : '1';

    wp_enqueue_style('lm-dispatcher', plugins_url($css_rel, __FILE__), [], $css_ver);
    wp_enqueue_script('lm-dispatcher', plugins_url($js_rel, __FILE__), [], $js_ver, true);

    // INLINE FIX: overflow issues on some themes
    $inline_css = 'html{overflow-y:auto!important;} body.page-id-' . (int) get_queried_object_id() . '{overflow:auto!important;overflow-x:hidden!important;}';
    wp_add_inline_style('lm-dispatcher', $inline_css);

    wp_localize_script('lm-dispatcher', 'LM_DISP', [
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce'    => wp_create_nonce('lm_disp_nonce'),
      'poll_ms'  => 5000,
      'build'    => $js_ver,
    ]);

    // Admin dashboard JS (only when admin shortcode/page is present)
    $is_admin_page = is_page('dispetcher-admin');
    if (!$is_admin_page && $post) {
      if (has_shortcode($post->post_content, 'lm_dispatcher_admin')) $is_admin_page = true;
    }

    if ($is_admin_page) {
      $admin_js_rel = null;
      if (file_exists($base_dir . 'assets/dispatcher-admin.js')) {
        $admin_js_rel = 'assets/dispatcher-admin.js';
      } elseif (file_exists($base_dir . 'dispatcher-admin.js')) {
        $admin_js_rel = 'dispatcher-admin.js';
      }

      if ($admin_js_rel) {
        $admin_abs = $base_dir . $admin_js_rel;
        $admin_ver = file_exists($admin_abs) ? (string) filemtime($admin_abs) : '1';
        wp_enqueue_script('lm-dispatcher-admin', plugins_url($admin_js_rel, __FILE__), ['lm-dispatcher'], $admin_ver, true);
      }
    }
  }

  // --- AJAX staff

  public static function ajax_poll() {
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'login required'], 401);
    if (!check_ajax_referer('lm_disp_nonce', 'nonce', false)) wp_send_json_error(['message' => 'bad nonce'], 403);

    // staff OR admin can poll
    if (!self::is_allowed_user() && !self::is_admin_allowed()) {
      wp_send_json_error(['message' => 'access denied'], 403);
    }

    $out = [];
    foreach (self::agents() as $group) {
      foreach (($group['members'] ?? []) as $login) {
        $u = self::get_user_by_login_safe($login);

        if (!$u) continue;
        $st = self::get_state($u->ID);
        $out[] = [
          'user_id'    => $u->ID,
          'name'       => $u->display_name ?: $u->user_login,
          'status'     => $st['status'],
          'comment'    => $st['comment'],
          'changed_at' => (int)$st['changed_at'],
          'first_active_at' => (int)($st['first_active_at'] ?? 0),
        ];
      }
    }
$lead_id = self::get_current_wishlist_lead_id();

    wp_send_json_success([
      'server_now' => time(),
      'items'      => $out,
'lead_user_id' => (int)$lead_id,

    ]);


  }

  public static function ajax_update() {
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'login required'], 401);
    if (!check_ajax_referer('lm_disp_nonce', 'nonce', false)) wp_send_json_error(['message' => 'bad nonce'], 403);

    // only staff listed users can update their own status/comment
    if (!self::is_allowed_user()) wp_send_json_error(['message' => 'access denied'], 403);

    $user_id = get_current_user_id();

$u = get_user_by('id', $user_id);
if ($u && strtolower($u->user_login) === self::VIEWER_LOGIN) {
  wp_send_json_error(['message' => 'read-only'], 403);
}

    $has_status  = array_key_exists('status', $_POST);
    $has_comment = array_key_exists('comment', $_POST);

    if (!$has_status && !$has_comment) {
      wp_send_json_error(['message' => 'nothing to update'], 400);
    }

    $status  = $has_status  ? sanitize_text_field((string)$_POST['status']) : null;
    $comment = $has_comment ? (string)($_POST['comment']) : null;

    $res = self::update_state_partial($user_id, $status, $comment);

if (is_wp_error($res)) {
  wp_send_json_error([
    'message' => $res->get_error_message(),
    'data'    => $res->get_error_data(),
  ], 409);
}

$state = self::get_state($user_id);

wp_send_json_success([
  'ok' => true,
  'state' => $state,
]);

  }

  // --- AJAX admin stats

  private static function list_users_by_group($group_key) {
    $agents = self::agents();
    $out = [];

    if ($group_key && $group_key !== 'all') {
      $g = $agents[$group_key] ?? null;
      if ($g && !empty($g['members'])) {
        foreach ($g['members'] as $login) {
          $out[] = ['group' => $group_key, 'login' => $login];
        }
      }
      return $out;
    }

    foreach ($agents as $k => $g) {
      foreach (($g['members'] ?? []) as $login) {
        $out[] = ['group' => $k, 'login' => $login];
      }
    }
    return $out;
  }

  private static function day_bounds_utc($date_ymd) {
    $tz = new DateTimeZone(self::BUSINESS_TZ);
    $start_local = new DateTimeImmutable($date_ymd . ' 00:00:00', $tz);
    $end_local = $start_local->modify('+1 day');

    $start_utc = $start_local->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
    $end_utc   = $end_local->setTimezone(new DateTimeZone('UTC'))->getTimestamp();

    return [$start_utc, $end_utc];
  }

  private static function compute_user_stats_for_day($user_id, $start_utc, $end_utc) {
    global $wpdb;

    $table = self::table_name();

    // 1) initial status from last status_change BEFORE day start
    $initial = $wpdb->get_var($wpdb->prepare(
      "SELECT to_status FROM {$table} WHERE user_id=%d AND event_type='status_change' AND ts < %d ORDER BY ts DESC LIMIT 1",
      $user_id, $start_utc
    ));
    $current_status = self::normalize_status($initial ?: 'offline');

    // 2) status changes inside day
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT ts, from_status, to_status, source FROM {$table} WHERE user_id=%d AND event_type='status_change' AND ts >= %d AND ts < %d ORDER BY ts ASC",
      $user_id, $start_utc, $end_utc
    ), ARRAY_A);

    // 3) comment count inside day
    $comment_count = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(1) FROM {$table} WHERE user_id=%d AND event_type='comment_save' AND ts >= %d AND ts < %d",
      $user_id, $start_utc, $end_utc
    ));

    $online_intervals = [];
    $durations = [];
    $sessions = 0;

    $session_start = null;
    if ($current_status === 'online') {
      $session_start = $start_utc;
      $sessions = 1; // carry-over session counts for the day
    }

    foreach ($rows as $r) {
      $to = self::normalize_status($r['to_status'] ?? 'offline');
      $ts = (int)($r['ts'] ?? 0);
      if ($ts <= 0) continue;

      if ($to === $current_status) {
        continue;
      }

      if ($current_status === 'online' && $session_start !== null) {
        $end = max($start_utc, min($end_utc, $ts));
        if ($end > $session_start) {
          $online_intervals[] = [$session_start, $end];
          $durations[] = $end - $session_start;
        }
        $session_start = null;
      }

      if ($to === 'online') {
        $sessions += 1;
        $session_start = $ts;
      }

      $current_status = $to;
    }

    if ($current_status === 'online' && $session_start !== null) {
      $online_intervals[] = [$session_start, $end_utc];
      $durations[] = $end_utc - $session_start;
    }

    $total = 0;
    $longest = 0;
    foreach ($durations as $d) {
      $total += (int)$d;
      if ($d > $longest) $longest = (int)$d;
    }

    $avg = ($sessions > 0) ? (int) floor($total / $sessions) : 0;

    $median = 0;
    if (!empty($durations)) {
      sort($durations);
      $n = count($durations);
      $mid = (int) floor($n / 2);
      if ($n % 2 === 1) {
        $median = (int)$durations[$mid];
      } else {
        $median = (int) floor(($durations[$mid - 1] + $durations[$mid]) / 2);
      }
    }

    // convert intervals to offsets in seconds from start of day
    $online_offsets = [];
    foreach ($online_intervals as $iv) {
      $a = max(0, (int)($iv[0] - $start_utc));
      $b = max(0, (int)($iv[1] - $start_utc));
      $online_offsets[] = [$a, $b];
    }

    return [
      'total_online_sec' => $total,
      'sessions' => $sessions,
      'comments' => $comment_count,
      'longest_sec' => $longest,
      'avg_sec' => $avg,
      'median_sec' => $median,
      'online_intervals' => $online_offsets,
    ];
  }

// File: lm-dispatcher.php

private static function is_user_logged_in_anywhere($user_id) {
  if (!$user_id) return false;
  if (!class_exists('WP_Session_Tokens')) return false;

  $mgr = WP_Session_Tokens::get_instance((int)$user_id);
  if (!$mgr) return false;

  $sessions = $mgr->get_all();
  if (empty($sessions) || !is_array($sessions)) return false;

  $now = time();
  foreach ($sessions as $s) {
    $exp = isset($s['expiration']) ? (int)$s['expiration'] : 0;
    if ($exp > $now) return true;
  }
  return false;
}


  public static function ajax_admin_stats() {
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'login required'], 401);
    if (!check_ajax_referer('lm_disp_nonce', 'nonce', false)) wp_send_json_error(['message' => 'bad nonce'], 403);
    if (!self::is_admin_allowed()) wp_send_json_error(['message' => 'access denied'], 403);

    self::maybe_create_table(false);

    $date = isset($_POST['date']) ? sanitize_text_field((string)$_POST['date']) : '';
    $group = isset($_POST['group']) ? sanitize_key((string)$_POST['group']) : 'all';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      wp_send_json_error(['message' => 'bad date (expected YYYY-MM-DD)'], 400);
    }

    [$start_utc, $end_utc] = self::day_bounds_utc($date);

    $users = self::list_users_by_group($group);
    $items = [];

    foreach ($users as $it) {
      $login = $it['login'];
      $grp   = $it['group'];
      $u = self::get_user_by_login_safe($login);
      if (!$u) {
$items[] = [
  'user_id' => 0,
  'login' => $login,
  'name' => $login,
  'group' => $grp,
  'missing' => true,
  'is_logged_in' => false,
  'stats' => null,
'status' => null,
];
        continue;
      }
$state = self::get_state((int)$u->ID);
$stats = self::compute_user_stats_for_day((int)$u->ID, $start_utc, $end_utc);

$items[] = [
  'user_id' => (int)$u->ID,
  'login' => $u->user_login,
  'name' => $u->display_name ?: $u->user_login,
  'group' => $grp,
  'missing' => false,
  'is_logged_in' => self::is_user_logged_in_anywhere((int)$u->ID),
  'stats' => $stats,
'status' => (isset($state['status']) ? $state['status'] : 'offline'),

];

    }

    wp_send_json_success([
      'date' => $date,
      'group' => $group,
      'tz' => self::BUSINESS_TZ,
      'day_sec' => 86400,
      'items' => $items,
    ]);
  }

  // --- REST (kept)

  public static function register_rest() {
    register_rest_route('lm-dispatcher/v1', '/statuses', [
      'methods' => 'GET',
      'permission_callback' => function () {
        return is_user_logged_in() && (self::is_allowed_user() || self::is_admin_allowed());
      },
      'callback' => function () {
        $out = [];
        foreach (self::agents() as $group) {
          foreach (($group['members'] ?? []) as $login) {
            $u = self::get_user_by_login_safe($login);
            if (!$u) continue;
            $st = self::get_state($u->ID);
            $out[] = [
              'user_id' => $u->ID,
              'name' => $u->display_name ?: $u->user_login,
              'status' => $st['status'],
              'comment' => $st['comment'],
              'changed_at' => $st['changed_at'],
            ];
          }
        }
        return rest_ensure_response($out);
      }
    ]);
  }




/**
 * 1) Гость пришёл на /dispetcher/ -> кидаем на логин с redirect_to обратно на /dispetcher/
 */
public static function maybe_redirect_guest_to_login() {
  $is_disp = self::is_disp_request();
  $is_admin = self::is_disp_admin_request();

  if (!$is_disp && !$is_admin) return;
  if (is_user_logged_in()) return;

  // ВАЖНО: чтобы не было циклов
  if (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') return;

  $target = $is_admin ? self::disp_admin_url() : self::disp_url();
  $login_url = wp_login_url($target);

  wp_safe_redirect($login_url);
  exit;
}


public static function disp_path() {
  // поддерживаем оба варианта; выбираем тот, который реально существует как Page
  foreach (['dispatcher', 'dispetcher'] as $slug) {
    $p = get_page_by_path($slug);
    if ($p && $p->post_status === 'publish') {
      return '/' . $slug . '/';
    }
  }
  // fallback
  return '/dispatcher/';
}

public static function disp_url() {
  return home_url(self::disp_path());
}

public static function is_disp_request() {
  // основной кейс: Page slug
  if (function_exists('is_page') && (is_page('dispatcher') || is_page('dispetcher'))) return true;

  // fallback: по URI
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  return (bool) preg_match('~^/(dispatcher|dispetcher)/?(\?.*)?$~', $uri);
}

public static function disp_admin_path() {
  foreach (['dispetcher-admin', 'dispatcher-admin'] as $slug) {
    $p = get_page_by_path($slug);
    if ($p && $p->post_status === 'publish') {
      return '/' . $slug . '/';
    }
  }
  return '/dispetcher-admin/';
}

public static function disp_admin_url() {
  return home_url(self::disp_admin_path());
}

public static function is_disp_admin_request() {
  if (function_exists('is_page') && (is_page('dispetcher-admin') || is_page('dispatcher-admin'))) return true;
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  return (bool) preg_match('~^/(dispetcher-admin|dispatcher-admin)/?(\?.*)?$~', $uri);
}



public static function filter_login_redirect($redirect_to, $requested_redirect_to, $user) {
  if (!($user instanceof WP_User) || !$user->ID) return $redirect_to;

  $is_staff = self::is_allowed_user($user->ID);
  $is_admin = user_can($user, 'manage_options') || user_can($user, 'lm_dispatcher_admin');

  $disp = self::disp_url();
  $adm  = self::disp_admin_url();

  $req = (string) $requested_redirect_to;
  $to  = (string) $redirect_to;
  $ref = $_SERVER['HTTP_REFERER'] ?? '';

  $wants_admin = (
    strpos($req, '/dispetcher-admin') !== false || strpos($req, '/dispatcher-admin') !== false ||
    strpos($to,  '/dispetcher-admin') !== false || strpos($to,  '/dispatcher-admin') !== false ||
    strpos($ref, '/dispetcher-admin') !== false || strpos($ref, '/dispatcher-admin') !== false
  );

  if ($wants_admin && $is_admin) return $adm;

  $wants_disp = (
    strpos($req, '/dispatcher') !== false || strpos($req, '/dispetcher') !== false ||
    strpos($to,  '/dispatcher') !== false || strpos($to,  '/dispetcher') !== false ||
    strpos($ref, '/dispatcher') !== false || strpos($ref, '/dispetcher') !== false
  );

  if ($wants_disp && ($is_staff || $is_admin)) return $disp;

  return $redirect_to;
}


public static function filter_wc_login_redirect($redirect_to, $user) {
  if (!($user instanceof WP_User) || !$user->ID) return $redirect_to;

  $is_staff = self::is_allowed_user($user->ID);
  $is_admin = user_can($user, 'manage_options') || user_can($user, 'lm_dispatcher_admin');

  $req = isset($_REQUEST['redirect_to']) ? (string) $_REQUEST['redirect_to'] : '';

  if ((strpos($req, '/dispetcher-admin') !== false || strpos($req, '/dispatcher-admin') !== false) && $is_admin) {
    return self::disp_admin_url();
  }

  if (strpos($req, '/dispatcher') !== false || strpos($req, '/dispetcher') !== false) {
    if ($is_staff || $is_admin) return self::disp_url();
  }

  return $redirect_to;
}




  public static function init() {
    add_action('init', [__CLASS__, 'register_shortcode']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

    add_action('wp_ajax_lm_disp_update', [__CLASS__, 'ajax_update']);


add_action('template_redirect', [__CLASS__, 'maybe_redirect_guest_to_login'], 1);
add_filter('login_redirect', [__CLASS__, 'filter_login_redirect'], 10, 3);

if (function_exists('wc_get_page_permalink')) {
  add_filter('woocommerce_login_redirect', [__CLASS__, 'filter_wc_login_redirect'], 10, 2);
}


    add_action('wp_ajax_lm_disp_poll', [__CLASS__, 'ajax_poll']);
    add_action('wp_ajax_nopriv_lm_disp_poll', [__CLASS__, 'ajax_poll']);

    add_action('wp_ajax_lm_disp_admin_stats', [__CLASS__, 'ajax_admin_stats']);

    add_action('rest_api_init', [__CLASS__, 'register_rest']);

    // safety: ensure table exists after updates (non-blocking)
    add_action('plugins_loaded', function () {
      self::maybe_create_table(false);
    });
  }
}

register_activation_hook(__FILE__, ['LM_Dispatcher', 'activate']);
LM_Dispatcher::init();
