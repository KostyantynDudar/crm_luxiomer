(function(){
  function ready(fn){
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function(){
    const el = document.getElementById('lmw-map');
    const items = window.LMW_WORKIZ_MAP_OBJECTS || [];

    if (!el || !window.L || !items.length) return;

    const map = L.map(el, {
      scrollWheelZoom: true
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const bounds = [];

    items.forEach(function(item){
      if (!item.lat || !item.lng) return;

      const color = item.status && /won|paid|done/i.test(item.status) ? '#16a34a'
        : item.status && /lost|declin|overdue/i.test(item.status) ? '#dc2626'
        : '#2563eb';

      const marker = L.circleMarker([item.lat, item.lng], {
        radius: 7,
        color: color,
        fillColor: color,
        fillOpacity: 0.78,
        weight: 2
      }).addTo(map);

      const html = `
        <div class="lmw-popup">
          <b>${escapeHtml(item.type.toUpperCase())} #${escapeHtml(item.id)}</b>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Status:</span> ${escapeHtml(item.status)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Client:</span> ${escapeHtml(item.client)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Address:</span> ${escapeHtml(item.address)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Job:</span> ${escapeHtml(item.job)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Created by:</span> ${escapeHtml(item.created_by)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Techs:</span> ${escapeHtml(item.techs)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Total:</span> $${escapeHtml(item.total)}</div>
          <div class="lmw-popup__row"><span class="lmw-popup__label">Due:</span> $${escapeHtml(item.due)}</div>
          ${item.url ? `<div class="lmw-popup__row"><a href="${escapeAttr(item.url)}" target="_blank" rel="noopener">Open in Workiz</a></div>` : ''}
        </div>
      `;

      marker.bindPopup(html, {maxWidth: 360});
      bounds.push([item.lat, item.lng]);
    });

    if (bounds.length) {
      map.fitBounds(bounds, {padding:[30,30]});
    } else {
      map.setView([34.05, -118.24], 9);
    }
  });

  function escapeHtml(v){
    return String(v || '').replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
    });
  }

  function escapeAttr(v){
    return String(v || '').replace(/"/g, '&quot;');
  }
})();
