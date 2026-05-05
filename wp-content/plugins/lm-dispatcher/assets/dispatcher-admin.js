(function () {
  var root = document.getElementById('lm-disp-admin');
  if (!root || !window.LM_DISP) return;

  function pad2(n) { return String(n).padStart(2, '0'); }

  function fmtDuration(sec) {
    sec = Math.max(0, Math.floor(sec));
    var h = Math.floor(sec / 3600);
    var m = Math.floor((sec % 3600) / 60);
    var s = sec % 60;
    if (h > 0) return h + ':' + pad2(m) + ':' + pad2(s);
    return m + ':' + pad2(s);
  }

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
    });
  }

  async function postStats(date, group) {
    var fd = new FormData();
    fd.append('action', 'lm_disp_admin_stats');
    fd.append('nonce', LM_DISP.nonce);
    fd.append('date', date);
    fd.append('group', group);

    var res = await fetch(LM_DISP.ajax_url, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      cache: 'no-store'
    });

    var json = await res.json();
    if (!json || !json.success) {
      var msg = (json && json.data && json.data.message) ? json.data.message : 'Request error';
      throw new Error(msg);
    }

    return json.data || null;
  }

  var dateInp = root.querySelector('[data-field="date"]');
  var groupSel = root.querySelector('[data-field="group"]');
  var btn = root.querySelector('[data-action="refresh-stats"]');
  var tbody = root.querySelector('[data-field="tbody"]');
  var meta = root.querySelector('[data-field="meta"]');

  if (!dateInp || !tbody) return;

  var defDate = root.getAttribute('data-default-date');
  if (defDate && !dateInp.value) dateInp.value = defDate;

  function render(items, daySec) {
    tbody.innerHTML = '';

    if (!items || !items.length) {
      tbody.innerHTML = '<tr><td colspan="8">No users</td></tr>';
      return;
    }

    items.forEach(function (it) {
      var tr = document.createElement('tr');

      var name = it && it.name ? it.name : (it && it.login ? it.login : '—');

      if (it.missing) {
        tr.innerHTML = '<td>' + esc(name) + ' <span class="lm-disp__muted">(missing)</span></td>' +
          '<td colspan="7">—</td>';
        tbody.appendChild(tr);
        return;
      }

      var st = it.stats || {};
      var total = st.total_online_sec || 0;
      var sessions = st.sessions || 0;
      var comments = st.comments || 0;
      var longest = st.longest_sec || 0;
      var avg = st.avg_sec || 0;

      var bar = document.createElement('div');
      bar.className = 'lm-disp-admin__bar';

      var intervals = Array.isArray(st.online_intervals) ? st.online_intervals : [];
      intervals.forEach(function (iv) {
        var a = iv[0] || 0;
        var b = iv[1] || 0;
        if (b <= a) return;

        var seg = document.createElement('div');
        seg.className = 'lm-disp-admin__seg lm-disp-admin__seg--online';
        seg.style.left = (a / daySec * 100).toFixed(4) + '%';
        seg.style.width = ((b - a) / daySec * 100).toFixed(4) + '%';
        bar.appendChild(seg);
      });




var rawStatus = (it && it.status) ? it.status : 'offline';
var stKey = (rawStatus === 'online' || rawStatus === 'offline' || rawStatus === 'wishlist_lead') ? rawStatus : 'offline';
var stLabel = (stKey === 'online') ? 'Online' : (stKey === 'wishlist_lead' ? 'Wishlist lead' : 'OFF');

var statusHtml = '<span class="lm-disp__badge lm-disp__badge--' + stKey + '">' + esc(stLabel) + '</span>';



var dotCls = (it && it.is_logged_in) ? 'is-on' : 'is-off';

tr.innerHTML =
  '<td class="lm-disp__user">' +
    '<span class="lm-disp-admin__login-dot ' + dotCls + '" aria-hidden="true"></span>' +
    esc(name) +
  '</td>' +
  '<td>' + statusHtml + '</td>' +
  '<td>' + esc(fmtDuration(total)) + '</td>' +
  '<td>' + esc(String(sessions)) + '</td>' +
  '<td>' + esc(String(comments)) + '</td>' +
  '<td>' + esc(fmtDuration(longest)) + '</td>' +
  '<td>' + esc(fmtDuration(avg)) + '</td>' +
  '<td></td>';


      tr.lastElementChild.appendChild(bar);
      tbody.appendChild(tr);
    });
  }

  async function refresh() {
    if (btn) btn.disabled = true;
    tbody.innerHTML = '<tr><td colspan="8">Loading…</td></tr>';
    if (meta) meta.textContent = '';

    try {
      var date = dateInp.value;
      var group = groupSel ? (groupSel.value || 'all') : 'all';

      var data = await postStats(date, group);
      render(data.items || [], data.day_sec || 86400);

      if (meta) {
        meta.textContent = (data.tz ? ('TZ: ' + data.tz) : '') + (data.group ? (' | group: ' + data.group) : '');
      }
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="8">Error: ' + esc(e.message || 'request failed') + '</td></tr>';
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  if (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      refresh();
    });
  }

  dateInp.addEventListener('change', refresh);
  if (groupSel) groupSel.addEventListener('change', refresh);

  refresh();
})();
