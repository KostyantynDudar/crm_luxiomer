(function(){
  let map, markers = [];

  function ready(fn){
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function(){
    initMap();
    initFilters();
  });

  function initMap(){
    const el = document.getElementById('lmw-map');
    const items = window.LMW_WORKIZ_MAP_OBJECTS || [];
    if (!el || !window.L || !items.length) return;

    map = L.map(el, {scrollWheelZoom:true});

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom:19,
      attribution:'&copy; OpenStreetMap'
    }).addTo(map);

    const bounds = [];

    items.forEach(function(item){
      if (!item.lat || !item.lng) return;

      const color = item.type === 'invoice' ? '#7c3aed'
        : item.status && /won|paid|done/i.test(item.status) ? '#16a34a'
        : item.status && /lost|declin|overdue/i.test(item.status) ? '#dc2626'
        : '#2563eb';

      const marker = L.circleMarker([item.lat,item.lng], {
        radius:item.type === 'invoice' ? 8 : 6,
        color:color,
        fillColor:color,
        fillOpacity:.78,
        weight:2
      }).addTo(map);

      marker.__lmw = item;

      marker.bindPopup(`
        <div class="lmw-popup">
          <b>${esc(item.type.toUpperCase())} #${esc(item.id)}</b>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Status:</span> ${esc(item.status)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Client:</span> ${esc(item.client)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Address:</span> ${esc(item.address)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Job:</span> ${esc(item.job)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Manager:</span> ${esc(item.created_by)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Techs:</span> ${esc(item.techs)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Source:</span> ${esc(item.source)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Geo:</span> ${esc(item.geo_match_type)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Total:</span> $${esc(item.total)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Due:</span> $${esc(item.due)}</div>
          ${item.url ? `<div class="lmw-popup__row"><a href="${attr(item.url)}" target="_blank" rel="noopener">Open in Workiz</a></div>` : ''}
        </div>
      `, {maxWidth:380});

      markers.push(marker);
      bounds.push([item.lat,item.lng]);
    });

    if (bounds.length) map.fitBounds(bounds, {padding:[30,30]});
  }

  function initFilters(){
    const filters = {
      search: document.getElementById('lmw-search'),
      type: document.getElementById('lmw-type'),
      statusGroup: document.getElementById('lmw-status-group'),
      status: document.getElementById('lmw-status'),
      manager: document.getElementById('lmw-manager'),
      tech: document.getElementById('lmw-tech'),
      source: document.getElementById('lmw-source'),
      geo: document.getElementById('lmw-geo'),
      totalMin: document.getElementById('lmw-total-min'),
      totalMax: document.getElementById('lmw-total-max')
    };

    const rows = Array.from(document.querySelectorAll('.lmw-table tbody tr'));

    rebuildDynamicFilters();

    Object.values(filters).forEach(el => {
      if (!el) return;
      el.addEventListener('input', apply);
      el.addEventListener('change', apply);
    });

    if (filters.type) {
      filters.type.addEventListener('change', function(){
        rebuildDynamicFilters();
        apply();
      });
    }

    document.querySelectorAll('.lmw-quick button[data-quick]').forEach(btn => {
      btn.addEventListener('click', function(){
        document.querySelectorAll('.lmw-quick button').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');

        const q = btn.dataset.quick || '';

        resetFilters(false);

        if (q === 'estimate' || q === 'invoice') filters.type.value = q;
        if (q === 'won-paid') filters.statusGroup.value = 'won-paid';
        if (q === 'due') filters.statusGroup.value = 'unpaid';
        if (q === 'overdue') filters.statusGroup.value = 'overdue';
        if (q === 'mapped') filters.geo.value = 'mapped';
        if (q === 'no-geo') filters.geo.value = 'no_geo';

        rebuildDynamicFilters();
        apply();
      });
    });

    function rebuildDynamicFilters(){
      const currentType = filters.type ? filters.type.value : '';
      const scopedRows = currentType ? rows.filter(r => r.dataset.type === currentType) : rows;

      rebuildSelect(filters.status, 'All statuses', unique(scopedRows.map(r => r.dataset.status).filter(Boolean)), filters.status ? filters.status.value : '');
      rebuildSelect(filters.manager, 'All managers', unique(scopedRows.map(r => r.dataset.manager).filter(Boolean)), filters.manager ? filters.manager.value : '');
      rebuildSelect(filters.tech, 'All techs', unique(scopedRows.map(r => r.dataset.tech).filter(Boolean)), filters.tech ? filters.tech.value : '');
      rebuildSelect(filters.source, 'All sources', unique(scopedRows.map(r => r.dataset.source).filter(Boolean)), filters.source ? filters.source.value : '');

      const geoValues = unique(scopedRows.map(r => r.dataset.geo).filter(Boolean));
      rebuildSelect(filters.geo, 'All geo matches', ['mapped'].concat(geoValues), filters.geo ? filters.geo.value : '');
    }

    function resetFilters(keepSearch){
      if (!keepSearch && filters.search) filters.search.value = '';
      ['type','statusGroup','status','manager','tech','source','geo','totalMin','totalMax'].forEach(k =>
 {
        if (filters[k]) filters[k].value = '';
      });
    }

    function apply(){
      const q = val(filters.search).toLowerCase().trim();
      const t = val(filters.type);
      const sg = val(filters.statusGroup);
      const st = val(filters.status);
      const m = val(filters.manager);
      const tech = val(filters.tech);
      const src = val(filters.source);
      const geo = val(filters.geo);
      const min = parseFloat(val(filters.totalMin));
      const max = parseFloat(val(filters.totalMax));

      rows.forEach(row => {
        const total = parseFloat(row.dataset.total || '0');
        const ok =
          (!q || (row.dataset.search || '').includes(q)) &&
          (!t || row.dataset.type === t) &&
          (!sg || row.dataset.statusGroup === sg) &&
          (!st || row.dataset.status === st) &&
          (!m || row.dataset.manager === m) &&
          (!tech || row.dataset.tech === tech) &&
          (!src || row.dataset.source === src) &&
          (!geo || (geo === 'mapped' ? row.dataset.geo !== 'no_geo' : row.dataset.geo === geo)) &&
          (isNaN(min) || total >= min) &&
          (isNaN(max) || total <= max);

        row.style.display = ok ? '' : 'none';
      });

      applyMap(q,t,sg,st,m,tech,src,geo,min,max);
    }

    function applyMap(q,t,sg,st,m,tech,src,geo,min,max){
      if (!map) return;

      const visibleBounds = [];

      markers.forEach(marker => {
        const item = marker.__lmw || {};
        const hay = JSON.stringify(item).toLowerCase();
        const total = parseFloat(item.total_raw || item.total || 0);
        const itemStatusGroup = statusGroup(item);

        const ok =
          (!q || hay.includes(q)) &&
          (!t || item.type === t) &&
          (!sg || itemStatusGroup === sg) &&
          (!st || item.status === st) &&
          (!m || item.created_by === m) &&
          (!tech || item.techs === tech) &&
          (!src || item.source === src) &&
          (!geo || (geo === 'mapped' ? item.geo_match_type !== 'no_geo' : item.geo_match_type === geo)) &&
          (isNaN(min) || total >= min) &&
          (isNaN(max) || total <= max);

        if (ok) {
          marker.addTo(map);
          visibleBounds.push(marker.getLatLng());
        } else {
          marker.remove();
        }
      });

      if (visibleBounds.length) map.fitBounds(visibleBounds, {padding:[30,30]});
    }
  }

  function statusGroup(item){
    const s = String(item.status || '').toLowerCase();
    const due = parseFloat(item.due_raw || item.due || 0);

    if (/overdue/.test(s)) return 'overdue';
    if (/won|paid|completed/.test(s)) return 'won-paid';
    if (/lost|declin|cancel/.test(s)) return 'lost';
    if (/unsent|not sent|draft/.test(s)) return 'unsent';
    if (due > 0) return 'unpaid';
    return 'other';
  }

  function val(el){ return el ? el.value : ''; }

  function rebuildSelect(select, label, values, current){
    if (!select) return;

    select.innerHTML = '';
    const first = document.createElement('option');
    first.value = '';
    first.textContent = label;
    select.appendChild(first);

    values.forEach(v => {
      if (!v) return;
      const o = document.createElement('option');
      o.value = v;
      o.textContent = v;
      select.appendChild(o);
    });

    if (current && Array.from(select.options).some(o => o.value === current)) {
      select.value = current;
    }
  }

  function unique(arr){
    return Array.from(new Set(arr)).sort((a,b) => String(a).localeCompare(String(b)));
  }

  function esc(v){
    return String(v || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  function attr(v){
    return String(v || '').replace(/"/g, '&quot;');
  }
})();
