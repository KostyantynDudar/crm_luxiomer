(function () {
  const root = document.getElementById('lm-disp');
  if (!root || !window.LM_DISP) return;

  const currentUserId = parseInt(root.getAttribute('data-current-user') || '0', 10) || 0;

  const pad2 = (n) => String(n).padStart(2, '0');

  function fmtDuration(sec) {
    sec = Math.max(0, Math.floor(sec));
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    if (h > 0) return `${h}:${pad2(m)}:${pad2(s)}`;
    return `${m}:${pad2(s)}`;
  }

  function statusLabel(st) {
    if (st === 'offline') return 'OFF';
    if (st === 'online') return 'Online';
    if (st === 'wishlist_lead') return 'Showroom lead';
    return String(st || '');
  }

  function syncSelectVisual(sel) {
    if (!sel) return;
    sel.classList.toggle('lm-disp__select--wishlist_lead', sel.value === 'wishlist_lead');
  }

  function applyLeadLock(leadId) {
    if (!currentUserId) return;

    const tr = root.querySelector(`tr[data-user-id="${currentUserId}"]`);
    if (!tr) return;

    const sel = tr.querySelector('select[data-field="status"]');
    if (!sel) return;


    // если у юзера нет этой опции — значит у него нет права на Showroom lead (не Sales), выходим
    const opt = sel.querySelector('option[value="wishlist_lead"]');
    if (!opt) return;

    const lid = parseInt(leadId || '0', 10) || 0;
    const takenByOther = lid && lid !== currentUserId;

    // запрещаем выбирать lead, если он занят другим, но НЕ мешаем если ты уже lead
    opt.disabled = (takenByOther && sel.value !== 'wishlist_lead');
  }

  function ensureRankSpan(tr) {
    const td = tr.querySelector('.lm-disp__user');
    if (!td) return null;

    let s = td.querySelector('[data-field="rank"]');
    if (s) return s;

    s = document.createElement('span');
    s.className = 'lm-disp__rank';
    s.setAttribute('data-field', 'rank');
    td.appendChild(document.createTextNode(' '));
    td.appendChild(s);
    return s;
  }

  function applyRanks(items) {
    const salesTable = root.querySelector('table.lm-disp__table[data-group="sales"]');
    if (!salesTable || !Array.isArray(items)) return;

    const allowed = new Set();
    salesTable.querySelectorAll('tr[data-user-id]').forEach(tr => {
      const id = parseInt(tr.getAttribute('data-user-id') || '0', 10) || 0;
      if (id) allowed.add(id);
    });

    const list = items
      .filter(it => it && allowed.has(parseInt(it.user_id || 0, 10)))
      .filter(it => it.status && it.status !== 'offline')
      .map(it => ({
        id: parseInt(it.user_id || 0, 10) || 0,
        t: parseInt(it.first_active_at || 0, 10) || 0
      }))
      .filter(x => x.id && x.t)
      .sort((a, b) => (a.t - b.t) || (a.id - b.id));

    const rankMap = new Map();
    list.forEach((x, i) => rankMap.set(x.id, i + 1));

    salesTable.querySelectorAll('tr[data-user-id]').forEach(tr => {
      const id = parseInt(tr.getAttribute('data-user-id') || '0', 10) || 0;
      const r = rankMap.get(id) || 0;
      const span = tr.querySelector('[data-field="rank"]') || ensureRankSpan(tr);
      if (span) span.textContent = r ? String(r) : '';
    });
  }


  async function postUpdate(fields) {
    const fd = new FormData();
    fd.append('action', 'lm_disp_update');
    fd.append('nonce', LM_DISP.nonce);

    Object.keys(fields).forEach(k => fd.append(k, fields[k]));

    const res = await fetch(LM_DISP.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' });

    let json = null;
    try { json = await res.json(); } catch (e) {}

    if (!res.ok || !json || !json.success) {
      const msg = (json && json.data && json.data.message) ? json.data.message : 'Update error';
      const err = new Error(msg);

      // wp_send_json_error({message, data:{lead_user_id}}, 409)
      const extra = json && json.data && json.data.data ? json.data.data : null;
      if (res.status === 409 && extra && extra.lead_user_id) {
        err.code = 'lead_taken';
        err.lead_user_id = extra.lead_user_id;
      }
      throw err;
    }

    return json.data && json.data.state ? json.data.state : null;
  }

  let serverOffsetSec = 0; // server_now - local_now

  function tickTimers() {
    const localNow = Math.floor(Date.now() / 1000);
    const now = localNow + serverOffsetSec;

    root.querySelectorAll('tr[data-user-id][data-changed-at]').forEach(tr => {
      const raw = tr.getAttribute('data-changed-at');
      const changedAt = parseInt(raw || '0', 10) || now;
      const td = tr.querySelector('[data-field="timer"]');
      if (td) td.textContent = fmtDuration(now - changedAt);
    });
  }

  async function poll() {
    try {
      const fd = new FormData();
      fd.append('action', 'lm_disp_poll');
      fd.append('nonce', LM_DISP.nonce);

      const res = await fetch(LM_DISP.ajax_url, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        cache: 'no-store'
      });

      const json = await res.json();
      if (!json || !json.success || !json.data || !Array.isArray(json.data.items)) return;

      // синхра времени
      const serverNow = parseInt(json.data.server_now || '0', 10);
      if (serverNow > 0) {
        const localNow = Math.floor(Date.now() / 1000);
        serverOffsetSec = serverNow - localNow;
      }

      const leadId = parseInt(json.data.lead_user_id || '0', 10) || 0;

      json.data.items.forEach(item => {
        const tr = root.querySelector(`tr[data-user-id="${item.user_id}"]`);
        if (!tr) return;

        if (item.changed_at) {
          tr.setAttribute('data-changed-at', String(item.changed_at));
        }

        const statusSel = tr.querySelector('select[data-field="status"]');
        if (statusSel) {
          // это текущий юзер
          statusSel.value = item.status;
          syncSelectVisual(statusSel);

          const commentInp = tr.querySelector('input[data-field="comment"]');
          if (commentInp && document.activeElement !== commentInp) {
            commentInp.value = item.comment || '';
          }
        } else {
          // чужие юзеры
          const badge = tr.querySelector('[data-field="status_badge"]');
          if (badge) {
            badge.textContent = statusLabel(item.status);

            badge.classList.remove(
              'lm-disp__badge--online',
              'lm-disp__badge--offline',
              'lm-disp__badge--wishlist_lead'
            );
            badge.classList.add(`lm-disp__badge--${item.status}`);
          }

          const c = tr.querySelector('[data-field="comment"]');
          if (c) c.textContent = item.comment || '';
        }
      });

      // динамический лок на lead-опцию
      applyLeadLock(leadId);

      applyRanks(json.data.items);

    } catch (e) {
      // тихо
    }
  }

  async function saveStatus(tr, status) {
    const sel = tr.querySelector('select[data-field="status"]');
    if (sel) sel.disabled = true;

    try {
      const state = await postUpdate({ status });
      if (state && state.changed_at) {
        tr.setAttribute('data-changed-at', String(state.changed_at));
      }
      await poll();
    } catch (e) {
      if (e && e.code === 'lead_taken') {
        alert('Showroom lead is already taken.');
        // восстановить UI в реальное состояние + применить lock
        await poll();
        applyLeadLock(e.lead_user_id);
      } else {
        alert(e.message || 'Status update error');
      }
    } finally {
      if (sel) {
        sel.disabled = false;
        syncSelectVisual(sel);
      }
    }
  }

  async function saveComment(tr) {
    const inp = tr.querySelector('input[data-field="comment"]');
    const btn = tr.querySelector('button[data-action="save-comment"]');
    if (!inp) return;

    btn && (btn.disabled = true);
    try {
      await postUpdate({ comment: inp.value || '' });
      await poll();
    } catch (e) {
      alert(e.message || 'Comment save error');
    } finally {
      btn && (btn.disabled = false);
    }
  }

  // статус: авто-сейв по change
  root.addEventListener('change', (e) => {
    const sel = e.target.closest('select[data-field="status"]');
    if (!sel) return;
    const tr = sel.closest('tr[data-user-id]');
    if (!tr) return;

    syncSelectVisual(sel);
    saveStatus(tr, sel.value);
  });

  // комментарий: Save
  root.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-action="save-comment"]');
    if (!btn) return;

    e.preventDefault();
    const tr = btn.closest('tr[data-user-id]');
    if (tr) saveComment(tr);
  });

  // timers
  tickTimers();
  setInterval(tickTimers, 1000);

  // polling
  poll();
  setInterval(poll, Math.max(2000, LM_DISP.poll_ms || 5000));
})();
