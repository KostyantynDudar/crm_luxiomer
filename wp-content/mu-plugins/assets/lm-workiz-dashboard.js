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

      const color = item.status && /won|paid|done/i.test(item.status) ? '#16a34a'
        : item.status && /lost|declin|overdue/i.test(item.status) ? '#dc2626'
        : '#2563eb';

      const marker = L.circleMarker([item.lat,item.lng], {
        radius:7,color:color,fillColor:color,fillOpacity:.78,weight:2
      }).addTo(map);

      marker.__lmw = item;

      marker.bindPopup(`
        <div class="lmw-popup">
          <b>${esc(item.type.toUpperCase())} #${esc(item.id)}</b>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Status:</span> ${esc(item.status)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Client:</span> ${esc(item.client)}</div>
          <div 
class="lmw-popup__row"><span class="lmw-popup__label">Address:</span> ${esc(item.address)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Job:</span> ${esc(item.job)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Created by:</span> ${esc(item.created_by)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Techs:</span> ${esc(item.techs)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Total:</span> $${esc(item.total)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Due:</span> $${esc(item.due)}</div>
          ${item.url ? `<div class="lmw-popup__row"><a href="${attr(item.url)}" target="_blank" rel="noopener">Open in Workiz</a></div>` : ''}
        </div>
      `, {maxWidth:360});

      markers.push(marker);
      bounds.push([item.lat,item.lng]);
    });

    if (bounds.length) map.fitBounds(bounds, {padding:[30,30]});
  }

  function initFilters(){
    const search = document.getElementById('lmw-search');
    const type = document.getElementById('lmw-type');
    const status = document.getElementById('lmw-status');
    const manager = document.getElementById('lmw-manager');

    const rows = Array.from(document.querySelectorAll('.lmw-table tbody tr'));

    function rebuildDynamicFilters(){
      const currentType = type.value || '';
      const scopedRows = currentType ? rows.filter(r => r.dataset.type === currentType) : rows;

      rebuildSelect(status, 'All statuses', unique(scopedRows.map(r => r.dataset.status).filter(Boolean)));
      rebuildSelect(manager, 'All managers', unique(scopedRows.map(r => r.dataset.manager).filter(Boolean)));
    }

    rebuildDynamicFilters();

    [search,status,manager].forEach(el => el && el.addEventListener('input', apply));
    [status,manager].forEach(el => el && el.addEventListener('change', apply));

    if (type) {
      type.addEventListener('change', function(){
        rebuildDynamicFilters();
        apply();
      });
    }

    function apply(){
      const q = (search.value || '').toLowerCase().trim();
      const t = type.value || '';
      const st = status.value || '';
      const m = manager.value || '';

      rows.forEach(row => {
        const ok =
          (!q || (row.dataset.search || '').includes(q)) &&
          (!t || row.dataset.type === t) &&
          (!st || row.dataset.status === st) &&
          (!m || row.dataset.manager === m);

        row.style.display = ok ? '' : 'none';
      });

      if (map) {
        const visibleBounds = [];
        markers.forEach(marker => {
          const item = marker.__lmw || {};
          const ok =
            (!q || JSON.stringify(item).toLowerCase().includes(q)) &&
            (!t || item.type === t) &&
            (!st || item.status === st || item.status_full === st) &&
            (!m || item.created_by === m);

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
  }

  function rebuildSelect(select, label, values){
    if (!select) return;
    select.innerHTML = '';
    const first = document.createElement('option');
    first.value = '';
    first.textContent = label;
    select.appendChild(first);

    values.forEach(v => {
      const o = document.createElement('option');
      o.value = v;
      o.textContent = v;
      select.appendChild(o);
    });
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
