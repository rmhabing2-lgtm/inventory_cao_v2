// Map script: load desks, add/edit markers, show hover popovers
const defaultImage = '/inventory_cao_v2/OfficeMap/assets/img/officemap.PNG';
let imageUrl = defaultImage;
let imgW = 2000,
  imgH = 1200;
const apiUrl = 'office_map_api.php';
let desks = [];

function loadDesksFromServer() {
  return fetch(apiUrl + '?action=load')
    .then(r => r.json())
    .then(j => {
      if (j.success) {
        desks = j.desks;
        return desks;
      }
      throw new Error('Load failed');
    });
}
function saveDeskToServer(desk) {
  if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) {
    return Promise.resolve({ success: false, msg: 'unauthorized' });
  }
  return fetch(apiUrl + '?action=save', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ desk: desk })
  }).then(r => r.json());
}
function deleteDeskFromServer(id) {
  if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) {
    return Promise.resolve({ success: false, msg: 'unauthorized' });
  }
  return fetch(apiUrl + '?action=delete', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: id })
  }).then(r => r.json());
}
function resetServerData() {
  return fetch(apiUrl + '?action=reset').then(r => r.json());
}

// Ask backend for selected image first; fallback to the canonical officemap PNG
const transparent1px =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAn8B9k0ncQAAAABJRU5ErkJggg==';
fetch(apiUrl + '?action=image')
  .then(r => r.json())
  .then(j => {
    if (j && j.success && j.path) {
      imageUrl = j.path;
      if (j.width) imgW = j.width;
      if (j.height) imgH = j.height;
    }
    initMap();
  })
  .catch(e => {
    // network error: still init with default
    initMap();
  });

function initMap() {
  const map = L.map('map', { crs: L.CRS.Simple, minZoom: -2, maxZoom: 2 });
  // expose map globally so UI can pick coordinates
  window.officeMap = map;
  // If page was opened after assigning an item, preload assign info and let user click to place it
  try {
    const params = new URLSearchParams(window.location.search);
    if (params.get('assign_preload')) {
      fetch(apiUrl + '?action=get_assign_preload')
        .then(r => r.json())
        .then(j => {
          if (!j || !j.success || !j.preload) return;
          const p = j.preload;
          if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) {
            alert('Assignment recorded. Only ADMIN can place the assigned item on the map.');
            return;
          }
          alert('Assignment recorded. Click the map to place the assigned item location for: ' + (p.person_name || p.item_name || 'item'));
          // wait for a single click to open add popup prefilled
          map.once('click', function (e) {
            openAddPopup(map, e.latlng);
            // populate the add popup fields after it renders
            setTimeout(() => {
              try {
                const nameInput = document.getElementById('new_name');
                if (nameInput) {
                  nameInput.value = (p.person_name || '') + (p.employee_id ? ' — ' + p.employee_id : '');
                }
                const empHidden = document.getElementById('new_emp_id');
                if (empHidden) empHidden.value = p.employee_id || '';
                const itemInput = document.getElementById('new_item_search');
                if (itemInput) {
                  itemInput.value = p.item_name || '';
                  if (p.inventory_item_id) itemInput.dataset.itemId = p.inventory_item_id;
                }
                const accEl = document.getElementById('new_acc_id');
                if (accEl) accEl.value = p.accountable_id || '';
              } catch (e) {}
            }, 120);
          });
        })
        .catch(() => {});
    }
  } catch (e) {}
  const bounds = [
    [0, 0],
    [imgH, imgW]
  ]; // Leaflet uses [y,x]
  try {
    L.imageOverlay(imageUrl, bounds).addTo(map);
  } catch (e) {
    // fallback: use transparent image so map still renders
    console.warn('Failed to add image overlay, using transparent fallback', e);
    L.imageOverlay(transparent1px, bounds).addTo(map);
  }
  map.fitBounds(bounds);

  const deskLayer = L.layerGroup().addTo(map);
  const areaLayer = L.layerGroup().addTo(map);
  let areas = [];
  let areaMode = null; // 'add' | 'edit' | null
  let areaTemp = null; // store first corner or editing id

  function renderDesks() {
    deskLayer.clearLayers();
    // reset global marker map
    window.officeMapMarkers = {};
    desks.forEach(d => {
      const marker = L.circleMarker([d.y, d.x], {
        radius: 10,
        color: '#007bff',
        weight: 2,
        fill: true,
        fillOpacity: 0.9,
        fillColor: '#007bff'
      });
      marker.addTo(deskLayer);
      const tip = `<div style="font-weight:600">${escapeHtml(d.name || d.id)}</div><div style="font-size:12px;color:#666">x:${d.x || d.lng || 0} y:${d.y || d.lat || 0}</div>`;
      marker.bindTooltip(tip, { direction: 'right', offset: [12, 0], opacity: 0.95, className: 'omap-tooltip' });
      // keep reference for search highlighting
      if (d && d.id) window.officeMapMarkers[d.id] = { marker: marker, desk: d };
      marker.on('click', () => openEditPopup(marker, d));
      // enable drag by handling mouse events
      marker.on('mousedown', function (e) {
        if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) return; // view-only for non-admins
        map.dragging.disable();
        const move = function (ev) {
          marker.setLatLng(ev.latlng);
        };
        const up = function (ev) {
          map.off('mousemove', move);
          map.off('mouseup', up);
          map.dragging.enable();
          // save new coords
          const ll = marker.getLatLng();
          d.x = Math.round(ll.lng);
          d.y = Math.round(ll.lat);
          saveDeskToServer(d).then(() => loadAndRender());
        };
        map.on('mousemove', move);
        map.on('mouseup', up);
      });
    });
  }

  function loadAndRender() {
    loadDesksFromServer()
      .then(() => renderDesks())
      .catch(err => {
        console.warn('load failed', err);
        renderDesks();
      });
    loadAreas();
  }

  function loadAreas() {
    fetch(apiUrl + '?action=areas')
      .then(r => r.json())
      .then(j => {
        areas = j && j.areas ? j.areas : [];
        renderAreas();
      })
      .catch(e => {
        console.warn('areas load failed', e);
        areas = [];
        renderAreas();
      });
  }

  function renderAreas() {
    areaLayer.clearLayers();
    areas.forEach(a => {
      const bounds = [
        [a.y1, a.x1],
        [a.y2, a.x2]
      ]; // Leaflet uses [lat,y]
      const rect = L.rectangle(bounds, { color: '#28a745', weight: 2, fillOpacity: 0.06 }).addTo(areaLayer);
      rect.areaId = a.id;
      rect.on('click', () => showAreaInfo(a, rect));
    });
  }

  function showAreaInfo(a, rect) {
    const content = `<div style="min-width:260px"><div style="font-weight:600">${escapeHtml(a.name || 'Area')}</div><div style="font-size:13px;color:#666;margin-top:6px">Coords: (${a.x1},${a.y1}) — (${a.x2},${a.y2})</div><div style="margin-top:8px;display:flex;gap:8px"><button id="area_edit" class="btn btn-sm">Edit</button><button id="area_delete" class="btn btn-sm btn-danger">Delete</button><button id="area_contents" class="btn btn-sm btn-info">Show Contents</button></div></div>`;
    rect.bindPopup(content).openPopup();
    setTimeout(() => {
      const elEdit = document.getElementById('area_edit');
      const elDel = document.getElementById('area_delete');
      const elCont = document.getElementById('area_contents');
      // hide edit/delete for non-admin users
      if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) {
        if (elEdit) elEdit.style.display = 'none';
        if (elDel) elDel.style.display = 'none';
        if (elCont) elCont.onclick = () => { rect.closePopup(); showAreaContents(a); };
        return;
      }
      if (elEdit)
        elEdit.onclick = () => {
          areaMode = 'edit';
          areaTemp = a.id;
          rect.closePopup();
          alert('Area edit: click two points on the map to redefine area bounds');
        };
      if (elDel)
        elDel.onclick = () => {
          if (!confirm('Delete area ' + (a.name || a.id) + '?')) return;
          fetch(apiUrl + '?action=delete_area', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: a.id })
          }).then(() => {
            loadAreas();
            alert('Deleted');
          });
        };
      if (elCont)
        elCont.onclick = () => {
          rect.closePopup();
          showAreaContents(a);
        };
    }, 20);
  }

  function showAreaContents(a) {
    // collect desks inside area
    const x1 = Math.min(a.x1, a.x2),
      x2 = Math.max(a.x1, a.x2);
    const y1 = Math.min(a.y1, a.y2),
      y2 = Math.max(a.y1, a.y2);
    const inside = (desks || []).filter(d => {
      const x = d.x || d.lng || 0;
      const y = d.y || d.lat || 0;
      return x >= x1 && x <= x2 && y >= y1 && y <= y2;
    });
    let html = `<div style="max-height:60vh;overflow:auto"><h4>Area: ${escapeHtml(a.name || a.id)}</h4><div style="font-size:13px;color:#666">Found ${inside.length} desks</div>`;
    inside.forEach(d => {
      html += `<div style="margin-top:8px;padding:6px;border-bottom:1px solid #f1f1f1"><div style="font-weight:600">${escapeHtml(d.name || d.id)}</div><div style="font-size:12px;color:#666">x:${d.x || d.lng || 0} y:${d.y || d.lat || 0}</div>`;
      if (d.items && d.items.length) {
        html += `<div style="margin-top:6px">Items:`;
        d.items.forEach(
          it =>
            (html += `<div style="font-size:13px">${escapeHtml(it.inventory_item_name || it.name || '')} ${it.quantity ? ' x' + it.quantity : ''} ${it.accountable_employee_name ? ' — ' + escapeHtml(it.accountable_employee_name) : ''}</div>`)
        );
        html += `</div>`;
      }
      html += `</div>`;
    });
    html += `</div>`;
    // modal
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
    m.innerHTML = `<div style="width:640px;max-width:90vw;min-width:320px;box-sizing:border-box;background:#fff;padding:16px;border-radius:6px;max-height:90vh;overflow:auto">${html}<div style="text-align:right;margin-top:8px"><button id="area_close" class="btn btn-sm">Close</button></div></div>`;
    document.body.appendChild(m);
    document.getElementById('area_close').onclick = () => m.remove();
  }

  loadAndRender();

  // click to add a new desk
  map.on('click', function (e) {
    const p = e.latlng; // p.lat -> y, p.lng -> x
    if (areaMode === 'add' || areaMode === 'edit') {
      // two-click area creation/editing
      if (!areaTemp) {
        areaTemp = { x1: Math.round(p.lng), y1: Math.round(p.lat) };
        alert('First corner set. Click second corner to finish area.');
      } else {
        const x2 = Math.round(p.lng),
          y2 = Math.round(p.lat);
        const a =
          areaMode === 'add'
            ? { id: null, name: 'New Area', x1: areaTemp.x1, y1: areaTemp.y1, x2: x2, y2: y2 }
            : (() => {
                const id = areaTemp; // areaTemp holds id when editing
                // find area by id
                const found = areas.find(z => z.id === id) || { id: id, name: 'Area' };
                found.x1 = areaTemp.x1 || areaTemp.x1;
                found.y1 = areaTemp.y1 || areaTemp.y1;
                found.x2 = x2;
                found.y2 = y2;
                return found;
              })();
        // show modal to name/save
        const nm = prompt('Area name', a.name || 'New Area');
        if (nm !== null) {
          a.name = nm;
          if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) {
            alert('Saving areas is allowed for ADMIN only');
            areaMode = null;
            areaTemp = null;
            return;
          }
          fetch(apiUrl + '?action=save_area', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ area: a })
          })
            .then(r => r.json())
            .then(j => {
              if (j && j.success) {
                alert('Area saved');
                areaMode = null;
                areaTemp = null;
                loadAreas();
              } else alert('Save failed');
            });
        } else {
          areaMode = null;
          areaTemp = null;
        }
      }
      return;
    }
    if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) {
      // non-admins may view only
      return;
    }
    openAddPopup(map, p);
  });

  window.renderDesks = renderDesks;
}

// Modal editor for desk -> record transaction
window.showDeskEditModal = function (desk) {
  // remove existing modal
  const existing = document.getElementById('omap_edit_modal');
  if (existing) existing.remove();
  const modal = document.createElement('div');
  modal.id = 'omap_edit_modal';
  modal.style.position = 'fixed';
  modal.style.left = '0';
  modal.style.top = '0';
  modal.style.right = '0';
  modal.style.bottom = '0';
  modal.style.background = 'rgba(0,0,0,0.4)';
  modal.style.zIndex = 99999;
  modal.style.display = 'flex';
  modal.style.alignItems = 'center';
  modal.style.justifyContent = 'center';
    modal.innerHTML = `
      <div style="width:520px;max-width:90vw;min-width:320px;box-sizing:border-box;background:#fff;padding:16px;border-radius:6px;box-shadow:0 6px 30px rgba(0,0,0,0.35);max-height:90vh;overflow:auto">
        <h4 style="margin-top:0">Edit Desk: ${escapeHtml(desk.name || desk.id)}</h4>
        <div style="display:flex;gap:8px"><div style="flex:1"><label>ID<br/><input id="edit_desk_id" style="width:100%" value="${escapeHtml(desk.id || '')}" readonly/></label></div><div style="flex:1"><label>X<br/><input id="edit_desk_x" style="width:100%" value="${escapeHtml(String(desk.x || desk.lng || 0))}"/></label></div><div style="flex:1"><label>Y<br/><input id="edit_desk_y" style="width:100%" value="${escapeHtml(String(desk.y || desk.lat || 0))}" /></label></div></div>
        <div style="margin-top:8px"><label>Name<br/><input id="edit_desk_name" type="search" style="width:100%" value="${escapeHtml(desk.name || '')}" autocomplete="off" placeholder="Search employee by name or ID"/></label>
          <div id="edit_name_suggestions" style="max-height:140px;overflow:auto;border:1px solid #eee;display:none;background:#fff"></div>
          <input type="hidden" id="edit_emp_id" value="${escapeHtml(desk.employee_id || '')}" />
        </div>
        <div style="margin-top:8px"><label>Search Inventory Item<br/><input id="edit_item_search" placeholder="item name or part" style="width:100%" autocomplete="off"/></label>
          <div id="edit_item_suggestions" style="max-height:140px;overflow:auto;border:1px solid #eee;display:none;background:#fff"></div>
        </div>
        <div style="margin-top:8px"><label>Search Accountable / Person<br/><input id="edit_acc_search" placeholder="person or property" style="width:100%" autocomplete="off"/></label>
          <div id="edit_acc_suggestions" style="max-height:140px;overflow:auto;border:1px solid #eee;display:none;background:#fff"></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:8px"><div style="flex:1"><label>Quantity<br/><input id="edit_qty" type="number" min="1" value="1" style="width:100%"/></label></div><div style="flex:1"><label>Type<br/><select id="edit_type" style="width:100%"><option value="OUT">OUT</option><option value="IN">IN</option></select></label></div></div>
        <div style="margin-top:8px"><label>Remarks<br/><input id="edit_remarks" style="width:100%"/></label></div>
        <div style="margin-top:12px;display:flex;justify-content:flex-end;gap:8px"><button id="edit_cancel" class="btn btn-sm">Cancel</button><button id="edit_save" class="btn btn-sm btn-primary">Save Transaction</button></div>
      </div>
    `;
  document.body.appendChild(modal);

  // wire search like add popup
  const api = u =>
    fetch(u)
      .then(r => r.json())
      .catch(() => ({}));
  const itemInput = document.getElementById('edit_item_search');
  const itemBox = document.getElementById('edit_item_suggestions');
  const accInput = document.getElementById('edit_acc_search');
  const accBox = document.getElementById('edit_acc_suggestions');
  let itemTimeout, accTimeout, nameTimeout;
  let selectedItemId = null,
    selectedAccId = null,
    selectedEmployeeId = document.getElementById('edit_emp_id') ? document.getElementById('edit_emp_id').value : null;
  const nameInput = document.getElementById('edit_desk_name');
  const nameBox = document.getElementById('edit_name_suggestions');
  // add small Edit Person button next to edit modal name field
  setTimeout(() => {
    const editField = document.getElementById('edit_desk_name');
    if (editField && !document.getElementById('edit_name_edit')) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.id = 'edit_name_edit';
      btn.className = 'btn btn-sm';
      btn.style.marginLeft = '6px';
      btn.textContent = 'Edit Person';
      editField.parentNode.style.display = 'flex';
      editField.parentNode.style.alignItems = 'center';
      editField.parentNode.appendChild(btn);
      btn.addEventListener('click', function () {
        const eid = document.getElementById('edit_emp_id') ? document.getElementById('edit_emp_id').value : (selectedEmployeeId || null);
        if (!eid) return alert('No employee selected');
        showEmployeeEditModal(eid);
      });
    }
  }, 20);
  itemInput.addEventListener('input', function () {
    clearTimeout(itemTimeout);
    const q = this.value.trim();
    if (!q) {
      itemBox.style.display = 'none';
      itemBox.innerHTML = '';
      return;
    }
    itemTimeout = setTimeout(() => {
      api(apiUrl + '?action=search_inventory&q=' + encodeURIComponent(q)).then(j => {
        const items = j && j.items ? j.items : [];
        if (!items.length) {
          itemBox.style.display = 'none';
          itemBox.innerHTML = '';
          return;
        }
        itemBox.innerHTML = items
          .map(
            it =>
              `<div style="padding:6px;border-bottom:1px solid #f1f1f1;cursor:pointer" data-id="${escapeAttr(it.id)}" data-name="${escapeAttr(it.item_name)}">${escapeHtml(it.item_name)}<div style="font-size:12px;color:#666">${escapeHtml(it.particulars || '')}</div></div>`
          )
          .join('');
        itemBox.style.display = 'block';
        Array.from(itemBox.children).forEach(ch =>
          ch.addEventListener('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            selectedItemId = this.getAttribute('data-id');
            itemInput.value = this.getAttribute('data-name');
            itemBox.style.display = 'none';
            itemBox.innerHTML = '';
          })
        );
      });
    }, 250);
  });
  // preload inventory list on focus for quicker selection
  if (itemInput) {
    itemInput.addEventListener('focus', function () {
      if (this.value.trim()) return;
      api(apiUrl + '?action=list_inventory').then(j => {
        const items = j && j.items ? j.items : [];
        if (!items.length) return;
        itemBox.innerHTML = items
          .map(it => `<div style="padding:6px;border-bottom:1px solid #f1f1f1;cursor:pointer" data-id="${escapeAttr(it.id)}" data-name="${escapeAttr(it.item_name)}">${escapeHtml(it.item_name)}<div style="font-size:12px;color:#666">${escapeHtml(it.particulars || '')}</div></div>`)
          .join('');
        itemBox.style.display = 'block';
        Array.from(itemBox.children).forEach(ch => ch.addEventListener('click', function (e) {
          e.stopPropagation();
          e.preventDefault();
          const iid = this.getAttribute('data-id');
          const nm = this.getAttribute('data-name');
          itemInput.value = nm;
          itemBox.style.display = 'none';
          itemBox.innerHTML = '';
          itemInput.dataset.itemId = iid;
        }));
      });
    });
  }
  accInput.addEventListener('input', function () {
    clearTimeout(accTimeout);
    const q = this.value.trim();
    if (!q) {
      accBox.style.display = 'none';
      accBox.innerHTML = '';
      return;
    }
    accTimeout = setTimeout(() => {
      api(apiUrl + '?action=search_accountables&q=' + encodeURIComponent(q)).then(j => {
        const items = j && j.accountables ? j.accountables : [];
        if (!items.length) {
          accBox.style.display = 'none';
          accBox.innerHTML = '';
          return;
        }
        accBox.innerHTML = items
          .map(it => {
            const aid = it.id || it.accountable_id || it.inventory_item_id || '';
            const person = it.person_name || it.FIRSTNAME || '';
            const prop = it.property_number || it.serial_number || '';
            return `<div style="padding:6px;border-bottom:1px solid #f1f1f1;cursor:pointer" data-id="${escapeAttr(aid)}" data-person="${escapeAttr(person)}" data-prop="${escapeAttr(prop)}">${escapeHtml(person)}<div style="font-size:12px;color:#666">${escapeHtml(prop)}</div></div>`;
          })
          .join('');
        accBox.style.display = 'block';
        Array.from(accBox.children).forEach(ch =>
          ch.addEventListener('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            selectedAccId = this.getAttribute('data-id');
            const pname = this.getAttribute('data-person');
            const prop = this.getAttribute('data-prop');
            accInput.value = pname + (prop ? ' — ' + prop : '');
            accBox.style.display = 'none';
            accBox.innerHTML = '';
          })
        );
      });
    }, 250);
  });
  // preload accountables on focus
  if (accInput) {
    accInput.addEventListener('focus', function () {
      if (this.value.trim()) return;
      api(apiUrl + '?action=list_accountables').then(j => {
        const items = j && j.accountables ? j.accountables : [];
        if (!items.length) return;
        accBox.innerHTML = items
          .map(it => `<div style="padding:6px;border-bottom:1px solid #f1f1f1;cursor:pointer" data-id="${escapeAttr(it.id || it.inventory_item_id || '')}" data-person="${escapeAttr(it.person_name || '')}" data-prop="${escapeAttr(it.property_number || it.serial_number || '')}">${escapeHtml(it.person_name || '')} <div style="font-size:12px;color:#666">${escapeHtml(it.property_number || it.serial_number || '')}</div></div>`)
          .join('');
        accBox.style.display = 'block';
        Array.from(accBox.children).forEach(ch => ch.addEventListener('click', function (e) {
          e.stopPropagation();
          e.preventDefault();
          const aid = this.getAttribute('data-id');
          const pname = this.getAttribute('data-person');
          const prop = this.getAttribute('data-prop');
          accInput.value = pname + (prop ? ' — ' + prop : '');
          accBox.style.display = 'none';
          accBox.innerHTML = '';
          const accEl = document.getElementById('edit_acc_id');
          if (accEl) accEl.value = aid;
        }));
      });
    });
  }

  // If desk has employee_id, fetch employee to populate the name field nicely
  (function populateEmployeeIfPresent() {
    try {
      const eid = document.getElementById('edit_emp_id') ? document.getElementById('edit_emp_id').value : null;
      if (!eid) return;
      api(apiUrl + '?action=get_employee&id=' + encodeURIComponent(eid)).then(j => {
        const emp = j && j.employee ? j.employee : null;
        if (!emp) return;
        const eidVal = emp.end_user_id_number || emp.ID || emp.id || '';
        const name = emp.LASTNAME ? (emp.LASTNAME + ', ' + (emp.FIRSTNAME || '')) : (emp.FIRSTNAME || emp.person_name || '');
        if (nameInput) nameInput.value = name + (eidVal ? ' — ' + eidVal : '');
        const ne = document.getElementById('edit_emp_id');
        if (ne) ne.value = eidVal;
        selectedEmployeeId = eidVal;
      });
    } catch (e) {}
  })();
  // wire employee search for edit name
  if (nameInput) {
    nameInput.addEventListener('input', function () {
      clearTimeout(nameTimeout);
      const q = this.value.trim();
      if (!q) {
        nameBox.style.display = 'none';
        nameBox.innerHTML = '';
        selectedEmployeeId = null;
        document.getElementById('edit_emp_id') && (document.getElementById('edit_emp_id').value = '');
        return;
      }
      nameTimeout = setTimeout(() => {
        api(apiUrl + '?action=search_employees&q=' + encodeURIComponent(q)).then(j => {
          const items = j && j.employees ? j.employees : [];
          if (!items.length) {
            nameBox.style.display = 'none';
            nameBox.innerHTML = '';
            return;
          }
          nameBox.innerHTML = items
            .map(it => {
              const eid = it.end_user_id_number || it.ID || it.id || '';
              const name = it.LASTNAME
                ? (it.LASTNAME + ', ' + (it.FIRSTNAME || ''))
                : it.FIRSTNAME
                ? (it.FIRSTNAME + ' ' + (it.LASTNAME || ''))
                : it.person_name || '';
              const small = it.end_user_id_number ? it.end_user_id_number : (it.DEPARTMENT_ID || '');
              return `<div class="omap-sugg-name" data-eid="${escapeAttr(eid)}" data-name="${escapeAttr(name)}" style="padding:6px;border-bottom:1px solid #f1f1f1;cursor:pointer">${escapeHtml(name)} <div style="font-size:12px;color:#666">${escapeHtml(small)}</div></div>`;
            })
            .join('');
          nameBox.style.display = 'block';
          Array.from(nameBox.children).forEach(ch =>
            ch.addEventListener('click', function (e) {
              e.stopPropagation();
              e.preventDefault();
              selectedEmployeeId = this.getAttribute('data-eid');
              const nm = this.getAttribute('data-name');
              nameInput.value = nm + (selectedEmployeeId ? ' — ' + selectedEmployeeId : '');
              nameBox.style.display = 'none';
              nameBox.innerHTML = '';
              const ne = document.getElementById('edit_emp_id');
              if (ne) ne.value = selectedEmployeeId;
            })
          );
        });
      }, 250);
    });
    // preload employees on focus for quicker selection
    nameInput.addEventListener('focus', function () {
      if (this.value.trim()) return;
      api(apiUrl + '?action=list_employees').then(j => {
        const items = j && j.employees ? j.employees : [];
        if (!items.length) return;
        nameBox.innerHTML = items
          .map(it => {
            const eid = it.end_user_id_number || it.ID || it.id || '';
            const name = it.LASTNAME ? (it.LASTNAME + ', ' + (it.FIRSTNAME || '')) : it.FIRSTNAME || it.person_name || '';
            const small = it.end_user_id_number ? it.end_user_id_number : '';
            return `<div class="omap-sugg-name" data-eid="${escapeAttr(eid)}" data-name="${escapeAttr(name)}" style="padding:6px;border-bottom:1px solid #f1f1f1;cursor:pointer">${escapeHtml(name)} <div style="font-size:12px;color:#666">${escapeHtml(small)}</div></div>`;
          })
          .join('');
        nameBox.style.display = 'block';
        Array.from(nameBox.children).forEach(ch =>
          ch.addEventListener('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            selectedEmployeeId = this.getAttribute('data-eid');
            const nm = this.getAttribute('data-name');
            nameInput.value = nm + (selectedEmployeeId ? ' — ' + selectedEmployeeId : '');
            nameBox.style.display = 'none';
            nameBox.innerHTML = '';
            const ne = document.getElementById('edit_emp_id');
            if (ne) ne.value = selectedEmployeeId;
          })
        );
      });
    });
  }

  document.getElementById('edit_cancel').onclick = function () {
    modal.remove();
  };
  document.getElementById('edit_save').onclick = function () {
    const invId = selectedItemId ? parseInt(selectedItemId, 10) : null;
    const qty = parseInt(document.getElementById('edit_qty').value || 0, 10);
    const type = document.getElementById('edit_type').value || 'OUT';
    const remarks = document.getElementById('edit_remarks').value || '';
    // update desk name/employee before recording transaction so we can persist after
    try {
      desk.name = document.getElementById('edit_desk_name').value || desk.name;
      const eidField = document.getElementById('edit_emp_id') ? document.getElementById('edit_emp_id').value : null;
      desk.employee_id = selectedEmployeeId || eidField || desk.employee_id || null;
    } catch (e) {}
    if (!invId) {
      return alert('Select an inventory item');
    }
    if (!qty || qty <= 0) return alert('Enter valid quantity');
    // include accountable/borrow info when available so borrowed_items can be created
    const accountableId = selectedAccId || (document.getElementById('edit_acc_id') ? document.getElementById('edit_acc_id').value : null);
    const toPerson = document.getElementById('edit_desk_name') ? document.getElementById('edit_desk_name').value : '';
    const borrowerEmployeeId = document.getElementById('edit_emp_id') ? document.getElementById('edit_emp_id').value : null;
    const payload = { inventory_item_id: invId, quantity: qty, transaction_type: type, remarks: remarks, accountable_id: accountableId, to_person: toPerson, borrower_employee_id: borrowerEmployeeId };
    if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) return alert('Only ADMIN can record transactions from the map');
    fetch(apiUrl + '?action=record_transaction', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(r => r.json())
      .then(j => {
        if (j && j.success) {
          alert('Transaction recorded: ' + (j.reference_no || ''));
          // persist desk changes (name/employee) to server
          saveDeskToServer(desk).then(() => {
            modal.remove();
            if (window.loadDeskList) loadDeskList();
            if (window.renderDesks) window.renderDesks();
          }).catch(()=>{
            modal.remove();
            if (window.loadDeskList) loadDeskList();
            if (window.renderDesks) window.renderDesks();
          });
        } else alert('Error: ' + (j.msg || 'unknown'));
      })
      .catch(e => alert('Request failed'));
  };
};

// helper to pick a single coordinate on the map - callback receives {x:lng,y:lat}
window.pickMapCoords = function (cb) {
  if (!window.officeMap) return alert('Map not initialized yet');
  alert('Click on the map to pick coordinates for the desk.');
  const handler = function (e) {
    window.officeMap.off('click', handler);
    const latlng = e.latlng;
    if (cb && typeof cb === 'function') cb({ x: Math.round(latlng.lng), y: Math.round(latlng.lat) });
  };
  window.officeMap.on('click', handler);
};

// toolbar controls
document.getElementById('btnAddArea') &&
  (document.getElementById('btnAddArea').onclick = function () {
    if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) return alert('Add Area is allowed for ADMIN only');
    areaMode = 'add';
    areaTemp = null;
    alert('Add Area: click first corner on map');
  });
document.getElementById('btnEditArea') &&
  (document.getElementById('btnEditArea').onclick = function () {
    if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) return alert('Edit Area is allowed for ADMIN only');
    areaMode = 'edit';
    areaTemp = null;
    alert('Edit Area: click an area to open its popup and select Edit');
  });
document.getElementById('btnListAreas') &&
  (document.getElementById('btnListAreas').onclick = function () {
    loadAreas();
    setTimeout(() => {
      if (areas.length === 0) return alert('No areas defined');
      let html = '<div style="max-height:60vh;overflow:auto">';
      areas.forEach(
        a =>
          (html += `<div style="padding:8px;border-bottom:1px solid #eee"><div style="font-weight:600">${escapeHtml(a.name || a.id)}</div><div style="font-size:12px;color:#666">(${a.x1},${a.y1}) — (${a.x2},${a.y2})</div><div style="margin-top:6px"><button class="btn btn-sm show_contents" data-id="${escapeAttr(a.id)}">Show Contents</button> <button class="btn btn-sm delete_area" data-id="${escapeAttr(a.id)}">Delete</button></div></div>`)
      );
      html += '</div>';
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
      m.innerHTML = `<div style="width:640px;max-width:90vw;min-width:320px;box-sizing:border-box;background:#fff;padding:16px;border-radius:6px;max-height:90vh;overflow:auto">${html}<div style="text-align:right;margin-top:8px"><button id="areas_close" class="btn btn-sm">Close</button></div></div>`;
      document.body.appendChild(m);
      document.getElementById('areas_close').onclick = () => m.remove();
      Array.from(m.querySelectorAll('.show_contents')).forEach(b =>
        b.addEventListener('click', function () {
          const id = this.getAttribute('data-id');
          const a = areas.find(x => x.id === id);
          if (a) showAreaContents(a);
        })
      );
      Array.from(m.querySelectorAll('.delete_area')).forEach(b =>
        b.addEventListener('click', function () {
          if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) return alert('Delete area is allowed for ADMIN only');
          const id = this.getAttribute('data-id');
          if (!confirm('Delete?')) return;
          fetch(apiUrl + '?action=delete_area', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
          }).then(() => {
            loadAreas();
            m.remove();
            alert('Deleted');
          });
        })
      );
    }, 100);
  });

function openAddPopup(map, latlng) {
  const content = `<div style="min-width:320px;">
    <div style="display:flex;gap:8px"><div style="flex:1"><label>ID:<br/><input id="new_id" style="width:100%"/></label></div><div style="flex:2"><label>Name:<br/><div style="display:flex;gap:6px"><input id="new_name" type="search" style="width:100%" autocomplete="off" placeholder="Search employee by name or ID"/><button id="new_name_edit" class="btn btn-sm" type="button">Edit Person</button></div></label>
      <div id="new_name_suggestions" style="max-height:140px;overflow:auto;border:1px solid #eee;display:none;background:#fff"></div>
    </div></div>
    <div style="margin-top:6px"><label>Search Inventory Item:<br/><input id="new_item_search" placeholder="type item name or part" style="width:100%" autocomplete="off"/></label>
      <div id="new_item_suggestions" style="max-height:140px;overflow:auto;border:1px solid #eee;display:none;background:#fff"></div>
    </div>
    <div style="margin-top:6px"><label>Search Accountable Person/Record:<br/><input id="new_acc_search" placeholder="type person or property number" style="width:100%" autocomplete="off"/></label>
      <div id="new_acc_suggestions" style="max-height:140px;overflow:auto;border:1px solid #eee;display:none;background:#fff"></div>
    </div>
    <input type="hidden" id="new_acc_id" />
    <input type="hidden" id="new_emp_id" />
    <div style="margin-top:8px;display:flex;gap:8px"><button id="save_new" class="btn btn-sm btn-primary">Save</button><button id="cancel_new" class="btn btn-sm">Cancel</button></div>
  </div>`;
  const popup = L.popup({ maxWidth: 320 }).setLatLng(latlng).setContent(content).openOn(map);
  setTimeout(() => {
    // setup search behaviors
    const api = u =>
      fetch(u)
        .then(r => r.json())
        .catch(() => ({}));
    const nameInput = document.getElementById('new_name');
    const nameBox = document.getElementById('new_name_suggestions');
    const itemInput = document.getElementById('new_item_search');
    const itemBox = document.getElementById('new_item_suggestions');
    const accInput = document.getElementById('new_acc_search');
    const accBox = document.getElementById('new_acc_suggestions');
    const accId = document.getElementById('new_acc_id');
    // hidden employee id for selected name
    let selectedEmployeeId = null;

    let itemTimeout, accTimeout, nameTimeout;
    itemInput.addEventListener('input', function () {
      clearTimeout(itemTimeout);
      const q = this.value.trim();
      if (!q) {
        itemBox.style.display = 'none';
        itemBox.innerHTML = '';
        return;
      }
      itemTimeout = setTimeout(() => {
        api(apiUrl + '?action=search_inventory&q=' + encodeURIComponent(q)).then(j => {
          const items = j && j.items ? j.items : [];
          if (!items.length) {
            itemBox.style.display = 'none';
            itemBox.innerHTML = '';
            return;
          }
          itemBox.innerHTML = items
            .map(
              it =>
                `<div class="omap-sugg" data-id="${escapeAttr(it.id)}" data-name="${escapeAttr(it.item_name)}" style="padding:6px;border-bottom:1px solid #f1f1f1;cursor:pointer">${escapeHtml(it.item_name)} <div style="font-size:12px;color:#666">${escapeHtml(it.particulars || '')}</div></div>`
            )
            .join('');
          itemBox.style.display = 'block';
          Array.from(itemBox.children).forEach(ch =>
            ch.addEventListener('click', function (e) {
              e.stopPropagation();
              e.preventDefault();
              const iid = this.getAttribute('data-id');
              const nm = this.getAttribute('data-name');
              itemInput.value = nm;
              itemBox.style.display = 'none';
              itemBox.innerHTML = '';
              itemInput.dataset.itemId = iid;
            })
          );
        });
      }, 250);
    });

    accInput.addEventListener('input', function () {
      clearTimeout(accTimeout);
      const q = this.value.trim();
      if (!q) {
        accBox.style.display = 'none';
        accBox.innerHTML = '';
        return;
      }
      accTimeout = setTimeout(() => {
        api(apiUrl + '?action=search_accountables&q=' + encodeURIComponent(q)).then(j => {
          const items = j && j.accountables ? j.accountables : [];
          if (!items.length) {
            accBox.style.display = 'none';
            accBox.innerHTML = '';
            return;
          }
          accBox.innerHTML = items
            .map(it => {
              const aid = it.id || it.accountable_id || it.inventory_item_id || '';
              const person = it.person_name || it.FIRSTNAME || '';
              const prop = it.property_number || it.serial_number || '';
              return `<div class="omap-sugg-acc" data-id="${escapeAttr(aid)}" data-person="${escapeAttr(person)}" data-prop="${escapeAttr(prop)}" style="padding:6px;border-bottom:1px solid #f1f1f1;cursor:pointer">${escapeHtml(person)} <div style="font-size:12px;color:#666">${escapeHtml(prop)}</div></div>`;
            })
            .join('');
          accBox.style.display = 'block';
          Array.from(accBox.children).forEach(ch =>
            ch.addEventListener('click', function (e) {
              e.stopPropagation();
              e.preventDefault();
              const aid = this.getAttribute('data-id');
              const pname = this.getAttribute('data-person');
              const prop = this.getAttribute('data-prop');
              accInput.value = pname + (prop ? ' — ' + prop : '');
              accBox.style.display = 'none';
              accBox.innerHTML = '';
              accId.value = aid;
            })
          );
        });
      }, 250);
    });
    // preload accountables on focus in add popup
    if (accInput) {
      accInput.addEventListener('focus', function () {
        if (this.value.trim()) return;
        api(apiUrl + '?action=list_accountables').then(j => {
          const items = j && j.accountables ? j.accountables : [];
          if (!items.length) return;
          accBox.innerHTML = items
            .map(it => `<div class="omap-sugg-acc" data-id="${escapeAttr(it.id || '')}" data-person="${escapeAttr(it.person_name || '')}" data-prop="${escapeAttr(it.property_number || it.serial_number || '')}" style="padding:6px;border-bottom:1px solid #f1f1f1;cursor:pointer">${escapeHtml(it.person_name || '')} <div style="font-size:12px;color:#666">${escapeHtml(it.property_number || it.serial_number || '')}</div></div>`)
            .join('');
          accBox.style.display = 'block';
          Array.from(accBox.children).forEach(ch => ch.addEventListener('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            const aid = this.getAttribute('data-id');
            const pname = this.getAttribute('data-person');
            const prop = this.getAttribute('data-prop');
            accInput.value = pname + (prop ? ' — ' + prop : '');
            accBox.style.display = 'none';
            accBox.innerHTML = '';
            accId.value = aid;
          }));
        });
      });
    }

    // name (employee) autocomplete using search_employees
    nameInput.addEventListener('input', function () {
      clearTimeout(nameTimeout);
      const q = this.value.trim();
      if (!q) {
        nameBox.style.display = 'none';
        nameBox.innerHTML = '';
        selectedEmployeeId = null;
        document.getElementById('new_emp_id') && (document.getElementById('new_emp_id').value = '');
        return;
      }
      nameTimeout = setTimeout(() => {
        api(apiUrl + '?action=search_employees&q=' + encodeURIComponent(q)).then(j => {
          const items = j && j.employees ? j.employees : [];
          if (!items.length) {
            nameBox.style.display = 'none';
            nameBox.innerHTML = '';
            return;
          }
          nameBox.innerHTML = items
            .map(it => {
              const eid = it.end_user_id_number || it.ID || it.id || '';
              const name = it.LASTNAME
                ? (it.LASTNAME + ', ' + (it.FIRSTNAME || ''))
                : it.FIRSTNAME
                ? (it.FIRSTNAME + ' ' + (it.LASTNAME || ''))
                : it.person_name || '';
              const small = it.end_user_id_number ? it.end_user_id_number : (it.DEPARTMENT_ID || '');
              return `<div class="omap-sugg-name" data-eid="${escapeAttr(eid)}" data-name="${escapeAttr(name)}" style="padding:6px;border-bottom:1px solid #f1f1f1;cursor:pointer">${escapeHtml(name)} <div style="font-size:12px;color:#666">${escapeHtml(small)}</div></div>`;
            })
            .join('');
          nameBox.style.display = 'block';
          Array.from(nameBox.children).forEach(ch =>
            ch.addEventListener('click', function (e) {
              e.stopPropagation();
              e.preventDefault();
              selectedEmployeeId = this.getAttribute('data-eid');
              const nm = this.getAttribute('data-name');
              nameInput.value = nm + (selectedEmployeeId ? ' — ' + selectedEmployeeId : '');
              nameBox.style.display = 'none';
              nameBox.innerHTML = '';
              const ne = document.getElementById('new_emp_id');
              if (ne) ne.value = selectedEmployeeId;
            })
          );
        });
      }, 250);
    });
    // When the field is focused and empty, preload a short list from DB for quick selection
    nameInput.addEventListener('focus', function () {
      if (this.value.trim()) return;
      api(apiUrl + '?action=list_employees').then(j => {
        const items = j && j.employees ? j.employees : [];
        if (!items.length) return;
        nameBox.innerHTML = items
          .map(it => {
            const eid = it.end_user_id_number || it.ID || it.id || '';
            const name = it.LASTNAME ? (it.LASTNAME + ', ' + (it.FIRSTNAME || '')) : it.FIRSTNAME || it.person_name || '';
            const small = it.end_user_id_number ? it.end_user_id_number : '';
            return `<div class="omap-sugg-name" data-eid="${escapeAttr(eid)}" data-name="${escapeAttr(name)}" style="padding:6px;border-bottom:1px solid #f1f1f1;cursor:pointer">${escapeHtml(name)} <div style="font-size:12px;color:#666">${escapeHtml(small)}</div></div>`;
          })
          .join('');
        nameBox.style.display = 'block';
        Array.from(nameBox.children).forEach(ch =>
          ch.addEventListener('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            selectedEmployeeId = this.getAttribute('data-eid');
            const nm = this.getAttribute('data-name');
            nameInput.value = nm + (selectedEmployeeId ? ' — ' + selectedEmployeeId : '');
            nameBox.style.display = 'none';
            nameBox.innerHTML = '';
            if (document.getElementById('new_emp_id')) document.getElementById('new_emp_id').value = selectedEmployeeId;
          })
        );
      });
    });

    // wire Edit Person button to open inline employee edit modal when an employee is selected
    const newNameEditBtn = document.getElementById('new_name_edit');
    if (newNameEditBtn) {
      newNameEditBtn.addEventListener('click', function () {
        const ne = document.getElementById('new_emp_id');
        const eid = (ne && ne.value) ? ne.value : selectedEmployeeId;
        if (!eid) return alert('Select an employee first from suggestions.');
        showEmployeeEditModal(eid);
      });
    }

    document.getElementById('save_new').onclick = function () {
      if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) return alert('Only ADMIN can add desks on the map');
      const id = document.getElementById('new_id').value || 'D' + Date.now();
      const name = document.getElementById('new_name').value || id;
      const selectedItemId = itemInput.dataset.itemId || null;
      const selectedAccId = accId.value || null;
      const selectedEmp =
        selectedEmployeeId ||
        (document.getElementById('new_emp_id') ? document.getElementById('new_emp_id').value : null);
      // build desk.items from selected inventory/accountable if available
      const items = [];
      if (selectedItemId)
        items.push({
          inventory_item_id: selectedItemId,
          name: itemInput.value || '',
          accountable_id: selectedAccId || null,
          accountable: accInput.value || ''
        });
      else if (accInput.value)
        items.push({ name: '(linked)', accountable: accInput.value, accountable_id: selectedAccId || null });
      const accountableId = selectedAccId || (document.getElementById('new_acc_id') ? document.getElementById('new_acc_id').value : null);
      const toPerson = document.getElementById('new_name') ? document.getElementById('new_name').value : '';
      const borrowerEmployeeId = document.getElementById('new_emp_id') ? document.getElementById('new_emp_id').value : null;

      const desk = {
        id: id,
        name: name,
        x: Math.round(latlng.lng),
        y: Math.round(latlng.lat),
        items: items,
        employee_id: selectedEmp
      };
      saveDeskToServer(desk).then(r => {
        if (r.success) {
          desks.push(desk);
          window.renderDesks && window.renderDesks();
          map.closePopup();
        } else alert('Save failed');
      });
    };
    document.getElementById('cancel_new').onclick = function () {
      map.closePopup();
    };
  }, 10);
}

// Inline employee edit modal
function showEmployeeEditModal(eid) {
  // remove existing
  const existing = document.getElementById('omap_emp_edit_modal');
  if (existing) existing.remove();
  const modal = document.createElement('div');
  modal.id = 'omap_emp_edit_modal';
  modal.style.position = 'fixed';
  modal.style.left = '0';
  modal.style.top = '0';
  modal.style.right = '0';
  modal.style.bottom = '0';
  modal.style.background = 'rgba(0,0,0,0.45)';
  modal.style.zIndex = 100000;
  modal.style.display = 'flex';
  modal.style.alignItems = 'center';
  modal.style.justifyContent = 'center';
  modal.innerHTML = `<div style="width:680px;max-width:95vw;min-width:320px;box-sizing:border-box;background:#fff;padding:16px;border-radius:6px;max-height:90vh;overflow:auto">
    <h4 style="margin-top:0">Edit Employee</h4>
    <div style="display:flex;gap:8px"><div style="flex:1"><label>First Name<br/><input id="emp_first" style="width:100%"/></label></div><div style="flex:1"><label>Last Name<br/><input id="emp_last" style="width:100%"/></label></div></div>
    <div style="margin-top:8px"><label>Employee No.<br/><input id="emp_no" style="width:100%"/></label></div>
    <div style="margin-top:8px"><label>Department<br/><input id="emp_dept" style="width:100%"/></label></div>
    <div style="margin-top:12px;text-align:right"><button id="emp_cancel" class="btn btn-sm">Cancel</button> <button id="emp_save" class="btn btn-sm btn-primary">Save</button></div>
  </div>`;
  document.body.appendChild(modal);

  const api = u => fetch(u).then(r => r.json()).catch(() => ({}));
  // load employee data
  api(apiUrl + '?action=get_employee&id=' + encodeURIComponent(eid)).then(j => {
    const emp = j && j.employee ? j.employee : null;
    if (!emp) {
      // prefill with id only
      document.getElementById('emp_no').value = eid;
      return;
    }
    document.getElementById('emp_first').value = emp.FIRSTNAME || emp.first_name || '';
    document.getElementById('emp_last').value = emp.LASTNAME || emp.last_name || '';
    document.getElementById('emp_no').value = emp.end_user_id_number || emp.end_user || emp.ID || '';
    document.getElementById('emp_dept').value = emp.DEPARTMENT_ID || emp.department || '';
  });

  document.getElementById('emp_cancel').onclick = function () { modal.remove(); };
  document.getElementById('emp_save').onclick = function () {
    if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) return alert('Updating employee is allowed for ADMIN only');
    const payload = {
      employee: {
        FIRSTNAME: document.getElementById('emp_first').value || '',
        LASTNAME: document.getElementById('emp_last').value || '',
        end_user_id_number: document.getElementById('emp_no').value || ''
      }
    };
    fetch(apiUrl + '?action=update_employee', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(r => r.json())
      .then(j => {
        if (j && j.success) {
          alert('Employee updated');
          modal.remove();
        } else alert('Update failed: ' + (j && j.msg ? j.msg : ''));
      })
      .catch(e => alert('Request failed'));
  };
}

function openEditPopup(marker, desk) {
  const content = `<div style="min-width:320px;max-width:90vw;box-sizing:border-box;"><div><strong>Edit Desk</strong></div><div style="font-size:12px;color:#666">ID: ${escapeHtml(desk.id)}</div><div style="margin-top:6px"><label>Name:<br/><input id="e_name" style="width:100%" value="${escapeHtml(desk.name)}"/></label></div><div class="row" style="margin-top:6px;display:flex;gap:8px"><div style="flex:1"><label>X:<br/><input id="e_x" style="width:100%" value="${escapeHtml(String(desk.x || desk.lng || ''))}"/></label></div><div style="flex:1"><label>Y:<br/><input id="e_y" style="width:100%" value="${escapeHtml(String(desk.y || desk.lat || ''))}"/></label></div></div><div style="margin-top:6px"><label>Items (one per line, format: name | holder | accountable):<br/><textarea id="e_items" style="width:100%;height:90px">${escapeHtml(itemsToText(desk.items))}</textarea></label></div><div style="margin-top:6px;display:flex;gap:8px;justify-content:space-between"><div><button id="e_to_manual" class="btn btn-sm">Open Manual Edit</button></div><div><button id="e_save" class="btn">Save</button><button id="e_delete" class="btn">Delete</button></div></div></div>`;
  marker.bindPopup(content).openPopup();
  setTimeout(() => {
    if (typeof window !== 'undefined' && window.__IS_ADMIN__ === false) {
      const _es = document.getElementById('e_save');
      const _ed = document.getElementById('e_delete');
      if (_es) _es.disabled = true;
      if (_ed) _ed.disabled = true;
    }
    document.getElementById('e_save').onclick = function () {
      const name = document.getElementById('e_name').value;
      const itemsText = document.getElementById('e_items').value;
      const nx = parseInt(document.getElementById('e_x').value || 0, 10);
      const ny = parseInt(document.getElementById('e_y').value || 0, 10);
      desk.name = name;
      desk.x = nx;
      desk.y = ny;
      desk.items = textToItems(itemsText);
      saveDeskToServer(desk).then(r => {
        if (r.success) {
          loadDesksFromServer().then(() => window.renderDesks && window.renderDesks());
          marker.closePopup();
        } else alert('Save failed');
      });
    };
    document.getElementById('e_delete').onclick = function () {
      if (!confirm('Delete desk ' + desk.id + '?')) return;
      deleteDeskFromServer(desk.id).then(r => {
        if (r.success) {
          desks = desks.filter(x => x.id !== desk.id);
          window.renderDesks && window.renderDesks();
          marker.closePopup();
        } else alert('Delete failed');
      });
    };
    document.getElementById('e_to_manual').onclick = function () {
      // open the floating manual form prefilled with desk values
      closeManualForm();
      showManualAddForm({ x: desk.x || desk.lng || 0, y: desk.y || desk.lat || 0 });
      setTimeout(() => {
        document.getElementById('m_id').value = desk.id;
        document.getElementById('m_name').value = desk.name || '';
        document.getElementById('m_x').value = desk.x || desk.lng || '';
        document.getElementById('m_y').value = desk.y || desk.lat || '';
        document.getElementById('m_acc').value = desk.items && desk.items.length ? desk.items[0].accountable || '' : '';
        document.getElementById('m_items').value = itemsToText(desk.items);
      }, 50);
    };
  }, 50);
}

// Highlight desks on the map based on a search query (called from sidebar)
window.highlightDesks = function (query) {
  const q = (query || '').toLowerCase().trim();
  const map = window.officeMap;
  const markers = window.officeMapMarkers || {};
  let firstMarker = null;
  Object.keys(markers).forEach(id => {
    const obj = markers[id];
    const d = obj.desk || {};
    const parts = [];
    if (d.name) parts.push(d.name);
    if (d.items && d.items.length) {
      d.items.forEach(it => {
        parts.push(it.inventory_item_name || it.name || '');
        parts.push(it.accountable || it.accountable_employee_name || '');
      });
    }
    if (d.employee_id) parts.push(String(d.employee_id));
    const hay = parts.join(' ').toLowerCase();
    if (!q) {
      // reset
      try {
        obj.marker.setStyle({ color: '#007bff', fillColor: '#007bff' });
        obj.marker.closeTooltip();
      } catch (e) {}
      return;
    }
    if (hay.indexOf(q) !== -1) {
      try {
        obj.marker.setStyle({ color: '#ff5722', fillColor: '#ff5722' });
      } catch (e) {}
      if (!firstMarker) firstMarker = obj.marker;
    } else {
      try {
        obj.marker.setStyle({ color: '#007bff', fillColor: '#007bff' });
        obj.marker.closeTooltip();
      } catch (e) {}
    }
  });
  if (firstMarker) {
    try {
      firstMarker.openTooltip();
      if (map) map.setView(firstMarker.getLatLng());
    } catch (e) {}
  }
};

function itemsToText(items) {
  if (!items || !items.length) return '';
  return items.map(it => `${it.name} | ${it.holder || ''} | ${it.accountable || ''}`).join('\n');
}
function textToItems(txt) {
  if (!txt) return [];
  return txt.split('\n').map(line => {
    const parts = line.split('|').map(s => s.trim());
    return { name: parts[0] || '', holder: parts[1] || '', accountable: parts[2] || '' };
  });
}

function exportData() {
  fetch(apiUrl + '?action=load')
    .then(r => r.json())
    .then(j => {
      if (!j.success) return alert('Export failed');
      const dataStr = JSON.stringify(j.desks, null, 2);
      const blob = new Blob([dataStr], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'office_desks.json';
      a.click();
      URL.revokeObjectURL(url);
    });
}

function resetData() {
  if (!confirm('Reset server sample data?')) return;
  resetServerData().then(r => {
    if (r.success) loadDesksFromServer().then(() => window.renderDesks && window.renderDesks());
    else alert('Reset failed');
  });
}

// Upload image support removed; system uses single canonical officemap.PNG

// Helpers
function escapeHtml(s) {
  if (!s) return '';
  return String(s).replace(/[&<>\"]/g, function (ch) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch];
  });
}
function escapeAttr(s) {
  return String(s).replace(/'/g, "\\'");
}
