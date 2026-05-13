<?php
// Minimal office map content partial
?>
<style>
  .office-map-wrap {
    display: flex;
    gap: 12px;
  }

  .office-map-sidebar {
    width: 320px;
    flex: 0 0 320px;
  }

  .office-map-main {
    flex: 1;
    min-width: 0
  }

  #map {
    width: 100%;
    height: 680px;
    border: 1px solid #ddd
  }

  /* hide the small preview image so the map can use available space */
  #currentMapPreview { display: none !important; }

  .omap-toolbar {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 8px
  }

  .omap-tools {
    display: none !important;
    gap: 6px;
    flex-wrap: wrap
  }

  .desk-list {
    max-height: 540px;
    overflow: auto;
    border: 1px solid #e6e6e6;
    padding: 8px;
    background: #fff
  }

  .desk-item {
    padding: 6px;
    border-bottom: 1px solid #f1f1f1;
    display: flex;
    justify-content: space-between;
    gap: 8px
  }

  .desk-item .meta {
    font-size: 13px;
    color: #333
  }

  .small {
    font-size: 12px;
    padding: 6px
  }

  .file-input-inline {
    display: inline-block
  }

  .omap-status {
    font-size: 13px;
    color: #666
  }
</style>

<div class="card">
  <div class="card-body" style="padding:12px">
    <div class="omap-toolbar">
      <div style="font-weight:600;font-size:16px">Office Map</div>
      <div class="omap-tools" style="margin-left:8px">
        <button class="btn btn-sm btn-outline-primary" id="btnExport">Export JSON</button>
        <button class="btn btn-sm btn-outline-secondary" id="btnReset">Reset</button>
        <button class="btn btn-sm btn-outline-info" id="btnManual">Manual Add</button>
        <button class="btn btn-sm btn-outline-dark" id="btnPick">Pick Coords</button>
        <button class="btn btn-sm btn-outline-warning" id="btnCalibrate">Calibrate</button>
        <button class="btn btn-sm btn-outline-secondary" id="btnToggleGrid">Toggle Grid</button>
        <button class="btn btn-sm btn-outline-primary" id="btnAddArea">Add Area</button>
        <button class="btn btn-sm btn-outline-secondary" id="btnEditArea">Edit Area</button>
        <button class="btn btn-sm btn-outline-info" id="btnListAreas">List Areas</button>
      </div>
      <div style="margin-left:auto;text-align:right">
        <div id="currentImageInfo" class="omap-status">Loading current map image...</div>
      </div>
    </div>

    <div class="office-map-wrap">
      <div class="office-map-sidebar">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
          <input id="deskSearch" placeholder="Search desks" class="form-control" style="flex:1" />
          <input id="csv_import" type="file" accept="text/csv" style="display:none" />
          <button class="btn btn-sm" id="btnImport">Import CSV</button>
        </div>
        <div class="desk-list" id="deskList">Loading desks...</div>
        <div style="margin-top:8px;display:flex;gap:8px;justify-content:space-between">
          <button class="btn btn-sm" id="btnCenterAll">Fit All</button>
          <button class="btn btn-sm" id="btnClearSelection">Clear</button>
        </div>
        <div style="margin-top:8px;font-size:12px;color:#666">Quick tips: Click map to add, drag markers to move, use Manual Add for detailed edits.</div>
      </div>

      <div class="office-map-main">
        <div style="margin-bottom:8px"><img id="currentMapPreview" style="max-width:360px;height:auto;border:1px solid #ccc;display:none" /></div>
        <div id="map"></div>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
  // expose sanitized user role to the client so UI/JS can disable interactive controls for non-admins
  window.__USER_ROLE__ = '<?php echo isset($_SESSION["role"]) ? htmlspecialchars($_SESSION["role"], ENT_QUOTES) : "GUEST"; ?>';
  window.__IS_ADMIN__ = (window.__USER_ROLE__ === 'ADMIN');
  document.addEventListener('DOMContentLoaded', function(){
    try{
      const tools = document.querySelectorAll('.omap-tools');
      tools && Array.from(tools).forEach(el => { el.style.display = window.__IS_ADMIN__ ? 'flex' : 'none'; });
    }catch(e){}
  });
</script>

<script src="office_map.js"></script>

<script>
  // small helpers + protective shim for Object.entries (guards against null/undefined)
  (function() {
    try {
      const _native = Object.entries;
      Object.entries = function(obj) {
        if (obj == null) return [];
        return _native.call(Object, obj);
      };
    } catch (e) {
      /* ignore if immutable */ }
  })();

  function safeEntries(obj) {
    return obj && typeof obj === 'object' ? Object.entries(obj) : [];
  }

  // show which image path the map will try to load, and display preview
  function refreshImageInfo() {
    fetch('office_map_api.php?action=image').then(r => r.json()).then(j => {
      const el = document.getElementById('currentImageInfo');
      const preview = document.getElementById('currentMapPreview');
      if (!el) return;
      if (j && j.success && j.path) {
        el.innerText = 'Current map image: ' + j.path + (j.note ? (' (' + j.note + ')') : '');
        if (preview) {
          preview.src = j.path;
          preview.style.display = 'inline-block';
        }
      } else {
        el.innerText = 'No map image found. Place officemap.PNG in OfficeMap/assets/img/ to use default map.';
        if (preview) preview.style.display = 'none';
      }
    }).catch(e => {
      const el = document.getElementById('currentImageInfo');
      if (el) el.innerText = 'Unable to query current image.';
    });
  }

  refreshImageInfo();

  // desk list UI
  async function loadDeskList() {
    const res = await fetch('office_map_api.php?action=load');
    const j = await res.json();
    const listEl = document.getElementById('deskList');
    listEl.innerHTML = '';
    const desks = (j && j.desks) ? j.desks : [];
    const qRaw = (document.getElementById('deskSearch').value || '');
    const q = qRaw.toLowerCase();
    // helper to highlight matched substring in HTML
    function highlightHtml(text, q){
      if(!q) return escapeHtml(text);
      try{
        const re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')','ig');
        return escapeHtml(text).replace(re, '<span style="color:#d35400;font-weight:700">$1</span>');
      }catch(e){ return escapeHtml(text); }
    }

    desks.filter(d => {
      if(!q) return true;
      const hay = ((d.name||d.id||'') + ' ' + ((d.items||[]).map(it=> (it.name||it.inventory_item_name||it.accountable||it.accountable_employee_name||'')).join(' ')) + ' ' + ((d.employee_id||'')+'' )).toLowerCase();
      return hay.includes(q);
    }).forEach(d => {
      const div = document.createElement('div');
      div.className = 'desk-item';
      const meta = document.createElement('div');
      meta.className = 'meta';
      const title = (d.name||d.id||'');
      meta.innerHTML = `<div style="font-weight:600">${highlightHtml(title, q)}</div><div style="font-size:12px;color:#666">x:${d.x||d.lng||0} y:${d.y||d.lat||0}</div>`;
      const actions = document.createElement('div');
      actions.style.display = 'flex';
      actions.style.gap = '6px';
      const btnC = document.createElement('button');
      btnC.className = 'btn btn-sm small';
      btnC.innerText = 'Center';
      btnC.onclick = () => {
        if (window.officeMap) {
          window.officeMap.setView([d.y || d.lat || 0, d.x || d.lng || 0], 0);
        }
      };
      const btnE = document.createElement('button');
      btnE.className = 'btn btn-sm small';
      btnE.innerText = 'Edit';
      btnE.onclick = () => {
        if (typeof window.showDeskEditModal === 'function') window.showDeskEditModal(d);
        else openManualForDesk(d);
      };
      const btnI = document.createElement('button');
      btnI.className = 'btn btn-sm small';
      btnI.innerText = 'Items';
      btnI.onclick = () => {
        showPersonItems(d);
      };
      const btnD = document.createElement('button');
      btnD.className = 'btn btn-sm small';
      btnD.innerText = 'Delete';
      btnD.onclick = () => {
        if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) return alert('Only ADMIN can delete desks');
        if (confirm('Delete ' + d.id + '?')) {
          fetch('office_map_api.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              action: 'delete',
              id: d.id
            })
          }).then(() => {
            loadDeskList();
            if (window.renderDesks) window.renderDesks();
          });
        }
      };
      if (!window.__IS_ADMIN__) {
        btnE.disabled = true;
        btnD.disabled = true;
      }
      actions.appendChild(btnC);
      actions.appendChild(btnE);
      actions.appendChild(btnI);
      actions.appendChild(btnD);
      div.appendChild(meta);
      div.appendChild(actions);
      listEl.appendChild(div);
    });
    // trigger marker highlight / popover via map script
    if (typeof window.highlightDesks === 'function') window.highlightDesks(qRaw);
    if ((desks || []).length === 0) listEl.innerText = 'No desks plotted yet.';
  }

  // show assigned and borrowed items modal for a desk/person
  function showPersonItems(desk) {
    const empId = desk.employee_id || '';
    // prefer plain person name without appended ID (split on ' — ')
    let personName = desk.name || '';
    if (personName.indexOf(' — ') !== -1) personName = personName.split(' — ')[0];
    fetch('office_map_api.php?action=get_person_items&employee_id=' + encodeURIComponent(empId) + '&person_name=' + encodeURIComponent(personName))
      .then(r => r.json())
      .then(j => {
        const data = (j && j.data) ? j.data : { assigned: [], borrowed: [] };
        const m = document.createElement('div');
        m.style.position = 'fixed';
        m.style.left = '0';
        m.style.top = '0';
        m.style.right = '0';
        m.style.bottom = '0';
        m.style.background = 'rgba(0,0,0,0.45)';
        m.style.zIndex = 99999;
        m.style.display = 'flex';
        m.style.alignItems = 'center';
        m.style.justifyContent = 'center';
        let html = `<div style="width:720px;max-width:95vw;min-width:320px;box-sizing:border-box;background:#fff;padding:16px;border-radius:6px;max-height:90vh;overflow:auto;position:relative">`;
        // top-right close
        html += `<button id="items_close_top" style="position:absolute;right:8px;top:8px;border:0;background:transparent;font-size:20px;line-height:1;cursor:pointer" aria-label="Close">&times;</button>`;
        html += `<h4 style="margin-top:0">Items for ${escapeHtml(personName || desk.id || '')}</h4>`;
        html += `<h5 style="margin-top:6px">Assigned Items</h5>`;
        if ((data.assigned || []).length === 0) html += `<div class="small-muted">No assigned items found.</div>`;
        else {
          html += `<div style="margin-top:6px">`;
          data.assigned.forEach(a => {
            html += `<div style="padding:8px;border-bottom:1px solid #f1f1f1"><div style="font-weight:600">${escapeHtml(a.item_name || '')}</div><div style="font-size:13px;color:#444">Assigned to: ${escapeHtml(a.person_name || '')} — Qty: ${parseInt(a.assigned_quantity||0,10)}</div><div style="font-size:12px;color:#666">${escapeHtml(a.property_number||'')} ${escapeHtml(a.serial_number||'')}</div></div>`;
          });
          html += `</div>`;
        }
        html += `<h5 style="margin-top:10px">Borrowed Items</h5>`;
        if ((data.borrowed || []).length === 0) html += `<div class="small-muted">No borrowed items found.</div>`;
        else {
          html += `<div style="margin-top:6px">`;
          data.borrowed.forEach(b => {
              // format dates
              function formatDateShort(s){
                  if(!s) return '';
                  try{ const d = new Date(s.replace(' ', 'T')); return d.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }); }catch(e){ return s; }
              }
              const itemName = b.item_name || '';
              const returned = b.is_returned && parseInt(b.is_returned) == 1;
              html += `<div style="padding:8px;border-bottom:1px solid #f1f1f1">
                          <div style="font-weight:600">Ref: ${escapeHtml(b.reference_no||'')}</div>
                          <div style="font-size:13px;color:#444">Item: ${escapeHtml(itemName)}</div>
                          <div style="font-size:13px;color:#444">From: ${escapeHtml(b.from_person||'')} To: ${escapeHtml(b.to_person||'')} — Qty: ${parseInt(b.quantity||0,10)} ${returned?'<span style="color:#28a745">(Returned)</span>':''}</div>
                          <div style="font-size:12px;color:#666">Borrowed: ${escapeHtml(b.borrow_date||'')}</div>
                          ${ returned && b.return_date ? `<div style="font-size:12px;color:#666">Returned: ${escapeHtml(formatDateShort(b.return_date))}</div>` : '' }
                        </div>`;
            });
          html += `</div>`;
        }
        // Related transactions
        html += `<h5 style="margin-top:10px">Related Transactions</h5>`;
        if ((data.transactions || []).length === 0) html += `<div class="small-muted">No related transactions found.</div>`;
        else {
          html += `<div style="margin-top:6px">`;
          data.transactions.forEach(t => {
            const typ = (t.transaction_type || '').toUpperCase();
            const badgeClass = typ === 'IN' ? 'badge-in' : 'badge-out';
            const qty = parseInt(t.quantity||0,10);
            html += `<div style="padding:8px;border-bottom:1px solid #f1f1f1"><div style="font-weight:600">${escapeHtml(t.item_name||'')}</div><div style="font-size:13px;color:#444"><span class="${badgeClass}">${escapeHtml(typ)}</span> Qty: ${qty} Ref: ${escapeHtml(t.reference_no||'')}</div><div style="font-size:12px;color:#666">Holder: ${escapeHtml(t.current_holder_name||'Warehouse / Available')} ${t.current_assigned_qty?('(<small>'+parseInt(t.current_assigned_qty,10)+')</small>'):''}</div><div style="font-size:12px;color:#666">Borrowed status: ${escapeHtml(t.borrowed_status||'Available')}</div></div>`;
          });
          html += `</div>`;
        }
        html += `<div style="text-align:right;margin-top:8px"><button id="items_close" class="btn btn-sm">Close</button></div></div>`;
        m.innerHTML = html;
        document.body.appendChild(m);
        const topBtn = document.getElementById('items_close_top');
        const botBtn = document.getElementById('items_close');
        if (topBtn) topBtn.onclick = () => m.remove();
        if (botBtn) botBtn.onclick = () => m.remove();
      })
      .catch(e => alert('Failed to fetch items'));
  }

  function openManualForDesk(desk) {
    // use existing manual form if present
    if (typeof showManualAddForm === 'function') {
      showManualAddForm({
        x: desk.x || desk.lng || 0,
        y: desk.y || desk.lat || 0
      });
      setTimeout(() => {
        const setv = id => {
          const el = document.getElementById(id);
          if (el) el.value = (desk[id.replace('m_', '')] || '');
        };
        if (document.getElementById('m_id')) document.getElementById('m_id').value = desk.id || '';
        if (document.getElementById('m_name')) document.getElementById('m_name').value = desk.name || '';
        if (document.getElementById('m_x')) document.getElementById('m_x').value = desk.x || desk.lng || 0;
        if (document.getElementById('m_y')) document.getElementById('m_y').value = desk.y || desk.lat || 0;
        if (document.getElementById('m_items')) document.getElementById('m_items').value = (desk.items && desk.items.length) ? desk.items.map(it => `${it.name} | ${it.holder||''} | ${it.accountable||''}`).join('\n') : '';
      }, 120);
    } else {
      alert('Manual edit not available');
    }
  }

  document.getElementById('deskSearch').addEventListener('input', function() {
    loadDeskList();
  });

  document.getElementById('btnExport').onclick = function() {
    if (!window.__IS_ADMIN__) return alert('Export is allowed for ADMIN only');
    if (window.exportData) return window.exportData();
    fetch('office_map_api.php?action=load').then(r => r.json()).then(j => {
      const blob = new Blob([JSON.stringify(j.desks || [], null, 2)], {
        type: 'application/json'
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'office_map_data.json';
      a.click();
    });
  };
  document.getElementById('btnReset').onclick = function() {
    if (!window.__IS_ADMIN__) return alert('Reset is allowed for ADMIN only');
    if (!confirm('Reset sample data?')) return;
    fetch('office_map_api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        action: 'reset'
      })
    }).then(() => {
      loadDeskList();
      if (window.renderDesks) window.renderDesks();
    });
  };
  document.getElementById('btnManual').onclick = function() {
    if (!window.__IS_ADMIN__) return alert('Manual add is allowed for ADMIN only');
    if (typeof showManualAddForm === 'function') showManualAddForm();
    else alert('Manual add not available');
  };
  // Upload disabled: map image is taken from default officemap.PNG

  // pick coordinates helper
  document.getElementById('btnPick').onclick = function() {
    if (window.pickMapCoords) {
      window.pickMapCoords(function(pos) {
        navigator.clipboard && navigator.clipboard.writeText(`x=${pos.x}, y=${pos.y}`);
        alert('Picked coords: x=' + pos.x + ' y=' + pos.y + '\n(copied to clipboard)');
      });
    } else alert('Map not ready yet');
  };

  // import csv
  document.getElementById('btnImport').onclick = function() {
    if (!window.__IS_ADMIN__) return alert('Import is allowed for ADMIN only');
    document.getElementById('csv_import').click();
  };
  document.getElementById('csv_import').addEventListener('change', function(e) {
    if (!window.__IS_ADMIN__) return alert('Import is allowed for ADMIN only');
    const f = this.files[0];
    if (!f) return;
    const reader = new FileReader();
    reader.onload = function(ev) {
      const txt = ev.target.result; // simple CSV parse: id,name,x,y,items
      const lines = txt.split(/\r?\n/).filter(Boolean);
      lines.forEach(line => {
        const cols = line.split(',').map(s => s.trim());
        if (cols.length >= 4) {
          const desk = {
            id: cols[0],
            name: cols[1],
            x: parseInt(cols[2], 10) || 0,
            y: parseInt(cols[3], 10) || 0,
            items: []
          };
          fetch('office_map_api.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              desk: desk
            })
          });
        }
      });
      setTimeout(() => {
        loadDeskList();
        if (window.renderDesks) window.renderDesks();
        alert('Import queued');
      }, 600);
    };
    reader.readAsText(f);
  });

  // center all
  document.getElementById('btnCenterAll').onclick = function() {
    if (!window.officeMap || !window.renderDesks) return;
    fetch('office_map_api.php?action=load').then(r => r.json()).then(j => {
      const ds = j.desks || [];
      if (!ds.length) return;
      let minx = Infinity,
        miny = Infinity,
        maxx = -Infinity,
        maxy = -Infinity;
      ds.forEach(d => {
        const x = d.x || d.lng || 0;
        const y = d.y || d.lat || 0;
        minx = Math.min(minx, x);
        miny = Math.min(miny, y);
        maxx = Math.max(maxx, x);
        maxy = Math.max(maxy, y);
      });
      const bounds = [
        [miny, minx],
        [maxy, maxx]
      ];
      window.officeMap.fitBounds(bounds);
    });
  };

  // simple grid and calibration state stored in localStorage
  const OM_CAL_KEY = 'office_map_calibration_v1';

  function getCal() {
    try {
      return JSON.parse(localStorage.getItem(OM_CAL_KEY) || 'null') || {};
    } catch (e) {
      return {};
    }
  }

  function setCal(c) {
    localStorage.setItem(OM_CAL_KEY, JSON.stringify(c || {}));
  }

  let gridOn = false;
  document.getElementById('btnToggleGrid').onclick = function() {
    gridOn = !gridOn;
    applyGrid();
  };

  function applyGrid() {
    const mapEl = document.getElementById('map');
    const cal = getCal();
    const px = (cal.pixelsPerUnit || 100);
    if (gridOn) {
      mapEl.style.backgroundImage = `repeating-linear-gradient(0deg, rgba(0,0,0,0.03) 0 ${px}px, transparent ${px}px ${px*2}px), repeating-linear-gradient(90deg, rgba(0,0,0,0.03) 0 ${px}px, transparent ${px}px ${px*2}px)`;
    } else {
      mapEl.style.backgroundImage = '';
    }
  }

  // calibration: user picks two points and enters real-world distance
  document.getElementById('btnCalibrate').onclick = function() {
    if (!window.pickMapCoords) return alert('Map not ready');
    alert('You will pick two points on the map. First pick:');
    window.pickMapCoords(function(p1) {
      alert('First point recorded. Now pick second point.');
      window.pickMapCoords(function(p2) {
        const dx = p2.x - p1.x;
        const dy = p2.y - p1.y;
        const pixels = Math.sqrt(dx * dx + dy * dy);
        const meters = parseFloat(prompt('Enter the real-world distance between these two points (meters):', '1'));
        if (!meters || isNaN(meters) || meters <= 0) return alert('Invalid distance');
        const pixelsPerUnit = pixels / meters;
        setCal({
          p1: p1,
          p2: p2,
          pixelsPerUnit: pixelsPerUnit,
          unit: 'm'
        });
        alert('Calibration saved: ' + (pixelsPerUnit.toFixed(2)) + ' pixels per meter');
        applyGrid();
      });
    });
  };

  // initial load
  loadDeskList();

  // file preview/upload removed; system uses the default officemap.PNG image

  // refresh desk list when external map JS finishes loading markers
  setInterval(function() {
    if (window.renderDesks) {
      loadDeskList();
      clearInterval(this);
    }
  }, 800);
</script>