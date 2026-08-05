/**
 * floorplan.js — Mapa interativo FloorPlan GLPI
 * Tooltip, exportação como imagem, modo visualização
 */
(function () {
  "use strict";

  const TYPE_ICONS = {
    Computer         : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
    Monitor          : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
    NetworkEquipment : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="6" cy="12" r="1" fill="currentColor"/><circle cx="10" cy="12" r="1" fill="currentColor"/></svg>',
    Printer          : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><rect x="2" y="9" width="20" height="10" rx="2"/><path d="M6 14h12M6 18h8"/></svg>',
    Phone            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="17" r="1" fill="currentColor"/></svg>',
    Peripheral       : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="8" width="20" height="10" rx="2"/><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/></svg>',
    Rack             : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h8M8 14h8M8 18h8"/></svg>',
    Appliance        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2"/><circle cx="12" cy="12" r="4"/></svg>',
    _default         : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>',
  };

  // Ícones Tabler SVG por nome de tipo
  const TABLER_ICONS = {
    // Nativos GLPI
    'Computer'         : 'ti-desktop',
    'Monitor'          : 'ti-device-desktop',
    'NetworkEquipment' : 'ti-network',
    'Printer'          : 'ti-printer',
    'Phone'            : 'ti-phone',
    'Peripheral'       : 'ti-keyboard',
    'Rack'             : 'ti-server',
    'SoftwareLicense'  : 'ti-license',
    'Certificate'      : 'ti-certificate',
    'Unmanaged'        : 'ti-question-mark',
    'Appliance'        : 'ti-cpu',
    // Custom Assets comuns
    'Celular'          : 'ti-device-mobile',
    'Notebook'         : 'ti-device-laptop',
    'Tablet'           : 'ti-device-tablet',
    'Switch'           : 'ti-switches',
    'AccessPoint'      : 'ti-wifi',
    'Desktop'          : 'ti-desktop',
    'Televisao'        : 'ti-device-tv',
    'TV'               : 'ti-device-tv',
    'Projetor'         : 'ti-device-projector',
    'Camera'           : 'ti-camera',
    'NoBreak'          : 'ti-plug',
    'Impressora'       : 'ti-printer',
    'Roteador'         : 'ti-router',
    'Scanner'          : 'ti-scan',
  };

  function getIcon(itemtype) {
    const base = itemtype.split('\\').pop().split('/').pop();

    // Se tem ícone Tabler mapeado, usa <i> tag
    const tablerClass = TABLER_ICONS[base] || TABLER_ICONS[itemtype];
    if (tablerClass) {
      return `<i class="ti ${tablerClass}" style="font-size:1rem"></i>`;
    }

    // Fallback: SVG genérico
    return TYPE_ICONS[base] || TYPE_ICONS[itemtype] || TYPE_ICONS._default;
  }

  function getCsrfToken() {
    const input = document.querySelector('input[name="_glpi_csrf_token"]');
    return input ? input.value : '';
  }

  document.addEventListener("DOMContentLoaded", function () {
    if (!window.FP_CONFIG) return;
    window.fpMap = new FloorPlanMap(window.FP_CONFIG);
  });

  // ─── Classe principal ────────────────────────────────────────────
  function FloorPlanMap(cfg) {
    this.cfg        = cfg;
    this.scale      = 1;
    this.pinsLayer  = document.getElementById("fp-pins-layer");
    this.canvas     = document.getElementById("fp-map-canvas");
    this.container  = document.getElementById("fp-map-container");
    this.footer     = document.getElementById("fp-map-footer");
    this.saveStatus = document.getElementById("fp-save-status");
    this.dirty      = false;
    this._pins      = {};
    this._pendingPos = null;
    // Cria tooltip se não existir
    let tt = document.getElementById("fp-tooltip");
    if (!tt) {
      tt = document.createElement("div");
      tt.id = "fp-tooltip";
      tt.className = "fp-tooltip";
      tt.style.display = "none";
      document.body.appendChild(tt);
    }
    this._tooltip = tt;

    if (!this.pinsLayer || !this.canvas) return;

    this._renderSavedPins();

    if (!cfg.readonly) {
      this._buildPopup();
      this._initClickToPlace();
      this._addClickHint();
      this._showFooter();
      const saveBtn = document.getElementById("fp-save-btn");
      if (saveBtn) saveBtn.addEventListener("click", (e) => { e.preventDefault(); this.savePositions(); });
    } else {
      // Modo visualização — carrega detalhes dos pins
      this._loadPinDetails();
      this._buildLegend();
    }

    this._initPdfIfNeeded();
    window.onbeforeunload = null;
  }

  // ─── Renderiza pins salvos ───────────────────────────────────────
  FloorPlanMap.prototype._renderSavedPins = function () {
    (this.cfg.saved || []).forEach((s) => {
      const asset = (this.cfg.available || []).find(
        a => a.itemtype === s.itemtype && a.items_id === parseInt(s.items_id)
      );
      const fakeAsset = asset || {
        itemtype : s.itemtype,
        items_id : parseInt(s.items_id),
        name     : s.itemtype.split('\\').pop() + ' #' + s.items_id,
        icon     : 'ti ti-box',
        label    : s.itemtype.split('\\').pop(),
      };
      this._placePin(fakeAsset, parseFloat(s.pos_x), parseFloat(s.pos_y));
    });
  };

  // ─── Tooltip ────────────────────────────────────────────────────
  FloorPlanMap.prototype._loadPinDetails = function () {
    Object.values(this._pins).forEach(p => {
      fetch(this.cfg.ajax_url + '?action=get_asset_details&itemtype=' + encodeURIComponent(p.asset.itemtype) + '&items_id=' + p.asset.items_id, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          p.details = data.details;
          this._attachTooltip(p.el, data.details);
        }
      })
      .catch(() => {});
    });
  };

  FloorPlanMap.prototype._attachTooltip = function (pinEl, details) {
    const tooltip = this._tooltip;
    if (!tooltip) return;

    const show = (e) => {
      tooltip.innerHTML = `
        <div class="fp-tt-title">${_esc(details.name || '')}</div>
        <div class="fp-tt-row"><i class="ti ti-building"></i> ${_esc(details.entity || '—')}</div>
        <div class="fp-tt-row"><i class="ti ti-activity"></i> ${_esc(details.status || '—')}</div>
        <div class="fp-tt-row"><i class="ti ti-tag"></i> ${_esc(details.inventory && details.inventory !== '—' ? details.inventory : '—')}</div>
        ${details.link ? `<div class="fp-tt-link"><a href="${details.link}" target="_blank"><i class="ti ti-external-link"></i> Abrir no GLPI</a></div>` : ''}
      `;
      tooltip.style.display = 'block';
      this._positionTooltip(e);
    };

    const move  = (e) => this._positionTooltip(e);
    const hide  = ()  => { tooltip.style.display = 'none'; };

    pinEl.addEventListener('mouseenter', show);
    pinEl.addEventListener('mousemove',  move);
    pinEl.addEventListener('mouseleave', hide);
  };

  FloorPlanMap.prototype._positionTooltip = function (e) {
    const tt = this._tooltip;
    if (!tt) return;
    const margin = 14;
    let left = e.clientX + margin;
    let top  = e.clientY + margin;
    setTimeout(() => {
      const tw = tt.offsetWidth  || 220;
      const th = tt.offsetHeight || 120;
      if (left + tw > window.innerWidth)  left = e.clientX - tw - margin;
      if (top  + th > window.innerHeight) top  = e.clientY - th - margin;
      tt.style.left = left + 'px';
      tt.style.top  = top  + 'px';
    }, 0);
  };

  // ─── Legenda ─────────────────────────────────────────────────────
  FloorPlanMap.prototype._buildLegend = function () {
    const legend = document.getElementById('fp-legend');
    const body   = document.getElementById('fp-legend-body');
    if (!legend || !body) return;

    const pins = Object.values(this._pins);
    if (!pins.length) return;

    legend.style.display = 'block';
    pins.forEach(p => {
      const item = document.createElement('div');
      item.className = 'fp-legend-item';
      item.innerHTML = `<span class="fp-legend-icon">${getIcon(p.asset.itemtype)}</span><span>${_esc(p.asset.name)}</span>`;
      item.style.cssText = 'display:flex;align-items:center;gap:6px;padding:4px 10px;background:#f8f9fa;border-radius:6px;font-size:.82rem;cursor:pointer';
      item.addEventListener('click', () => {
        p.el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        p.el.classList.add('fp-pin--highlight');
        setTimeout(() => p.el.classList.remove('fp-pin--highlight'), 2000);
      });
      body.appendChild(item);
    });
  };

  // ─── Exportar como imagem ────────────────────────────────────────
  FloorPlanMap.prototype.exportImage = function () {
    const bgImg = document.getElementById('fp-bg-image');
    if (!bgImg) { alert('Nenhuma imagem de planta encontrada.'); return; }

    const btn = document.querySelector('[onclick*="exportImage"], [title="Exportar imagem"], [title="Exportar"]');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="ti ti-loader"></i> Gerando...'; }

    const exportCanvas = document.createElement('canvas');
    const ctx          = exportCanvas.getContext('2d');

    // Carrega a imagem de fundo
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
      exportCanvas.width  = img.naturalWidth;
      exportCanvas.height = img.naturalHeight;

      // Fundo branco
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);

      // Desenha planta
      ctx.drawImage(img, 0, 0, exportCanvas.width, exportCanvas.height);

      // Desenha pins
      const canvasEl  = document.getElementById('fp-map-canvas');
      const imgEl     = document.getElementById('fp-bg-image');
      const canvasRect = canvasEl.getBoundingClientRect();
      const imgRect    = imgEl.getBoundingClientRect();
      const scaleX     = img.naturalWidth  / imgRect.width;
      const scaleY     = img.naturalHeight / imgRect.height;

      Object.values(this._pins).forEach(p => {
        const px = (p.pos_x / 100) * img.naturalWidth;
        const py = (p.pos_y / 100) * img.naturalHeight;
        const r  = 18;

        // Círculo do pin (sem sombra)
        ctx.shadowColor   = 'transparent';
        ctx.shadowBlur    = 0;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 0;

        ctx.beginPath();
        ctx.arc(px, py, r, 0, Math.PI * 2);
        ctx.fillStyle = '#3b82f6';
        ctx.fill();

        // Borda
        ctx.strokeStyle = 'rgba(255,255,255,0.8)';
        ctx.lineWidth   = 2;
        ctx.stroke();

        // Nome do ativo + número de inventário
        ctx.font         = 'bold 11px Arial';
        ctx.fillStyle    = '#1e293b';
        ctx.textAlign    = 'center';
        ctx.textBaseline = 'top';

        // Monta label: "Nome (inventário)" ou só "Nome"
        const inv     = p.details && p.details.inventory && p.details.inventory !== '—' ? p.details.inventory : null;
        const label   = inv ? (p.asset.name + ' (' + inv + ')') : (p.asset.name || '');
        const metrics = ctx.measureText(label);
        const lw      = metrics.width + 10;
        const lh      = 16;
        const lx      = px - lw / 2;
        const ly      = py + r + 4;

        ctx.fillStyle    = 'rgba(255,255,255,0.9)';
        ctx.beginPath();
        ctx.roundRect ? ctx.roundRect(lx, ly, lw, lh, 3) : ctx.rect(lx, ly, lw, lh);
        ctx.fill();

        ctx.fillStyle = '#1e293b';
        ctx.fillText(label, px, ly + 2);
        ctx.textAlign    = 'left';
        ctx.textBaseline = 'alphabetic';
      });

      // Download
      exportCanvas.toBlob(blob => {
        const url  = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.download = (window.FP_EXPORT_NAME || 'planta') + '.png';
        link.href     = url;
        link.click();
        URL.revokeObjectURL(url);
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="ti ti-download"></i>'; }
      }, 'image/png');
    };

    img.onerror = () => {
      alert('Não foi possível carregar a imagem da planta.');
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="ti ti-download"></i>'; }
    };

    img.src = bgImg.src;
  };

  // ─── Popup (modo edição) ─────────────────────────────────────────
  FloorPlanMap.prototype._buildPopup = function () {
    const old = document.getElementById("fp-popup");
    if (old) old.remove();
    const popup = document.createElement("div");
    popup.id        = "fp-popup";
    popup.className = "fp-popup";
    popup.innerHTML = `
      <div class="fp-popup-header">
        <span id="fp-popup-title"><i class="ti ti-map-pin"></i> Adicionar ativo</span>
        <button class="fp-popup-close" id="fp-popup-close">×</button>
      </div>
      <div class="fp-popup-body" id="fp-popup-body"></div>
    `;
    document.body.appendChild(popup);
    this._popup = popup;
    document.getElementById("fp-popup-close").addEventListener("click", () => this._closePopup());
    document.addEventListener("keydown", (e) => { if (e.key === "Escape") this._closePopup(); });
  };

  FloorPlanMap.prototype._initClickToPlace = function () {
    this.canvas.addEventListener("click", (e) => {
      if (this.cfg.readonly) return;
      if (e.target.closest(".fp-pin")) return;
      const rect  = this.canvas.getBoundingClientRect();
      const pos_x = ((e.clientX - rect.left)  / rect.width)  * 100;
      const pos_y = ((e.clientY - rect.top)   / rect.height) * 100;
      this._pendingPos = { pos_x, pos_y };
      this._showCategoryList(e.clientX, e.clientY);
    });
  };

  FloorPlanMap.prototype._addClickHint = function () {
    if (this.cfg.readonly) return;
    const hint = document.createElement("div");
    hint.className = "fp-click-hint";
    hint.innerHTML = '<svg viewBox="0 0 24 24" width="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/></svg> Clique na planta para adicionar um ativo';
    this.container.appendChild(hint);
    this.canvas.addEventListener("click", () => hint.remove(), { once: true });
  };

  FloorPlanMap.prototype._showCategoryList = function (mx, my) {
    const body  = document.getElementById("fp-popup-body");
    const title = document.getElementById("fp-popup-title");
    title.innerHTML = '<i class="ti ti-category"></i> Escolha a categoria';
    body.innerHTML  = "";
    const groups = {};
    (this.cfg.available || []).forEach(a => {
      if (!groups[a.itemtype]) groups[a.itemtype] = { label: a.label, count: 0 };
      groups[a.itemtype].count++;
    });
    if (!Object.keys(groups).length) {
      body.innerHTML = "<p class='fp-popup-empty'>Nenhum ativo disponível.</p>";
      this._openPopup(mx, my); return;
    }
    Object.entries(groups).forEach(([type, g]) => {
      const btn = document.createElement("button");
      btn.className = "fp-popup-cat-btn";
      btn.innerHTML = `<span class="fp-cat-icon">${getIcon(type)}</span><span>${g.label}</span><span class="fp-popup-count">${g.count}</span><i class="ti ti-chevron-right fp-popup-arrow"></i>`;
      btn.addEventListener("click", () => this._showAssetList(type, g.label, mx, my));
      body.appendChild(btn);
    });
    this._openPopup(mx, my);
  };

  FloorPlanMap.prototype._showAssetList = function (itemtype, label, mx, my) {
    const body  = document.getElementById("fp-popup-body");
    const title = document.getElementById("fp-popup-title");
    title.innerHTML = `<span class="fp-cat-icon-sm">${getIcon(itemtype)}</span> ${label}`;
    const assets = (this.cfg.available || []).filter(a => a.itemtype === itemtype);
    body.innerHTML = `<button class="fp-popup-back" id="fp-popup-back"><i class="ti ti-arrow-left"></i> Voltar</button><input type="text" class="fp-popup-search" id="fp-popup-search" placeholder="Buscar ativo..."><div id="fp-popup-asset-list"></div>`;
    document.getElementById("fp-popup-back").addEventListener("click", () => this._showCategoryList(mx, my));
    const searchInput = document.getElementById("fp-popup-search");
    const listEl      = document.getElementById("fp-popup-asset-list");
    const render = (filter) => {
      listEl.innerHTML = "";
      const filtered = assets.filter(a => !filter || a.name.toLowerCase().includes(filter.toLowerCase()));
      if (!filtered.length) { listEl.innerHTML = "<p class='fp-popup-empty'>Nenhum ativo encontrado.</p>"; return; }
      filtered.forEach(a => {
        const key = `${a.itemtype}|${a.items_id}`;
        const placed = !!this._pins[key];
        const btn = document.createElement("button");
        btn.className = "fp-popup-asset-btn" + (placed ? " fp-popup-asset-placed" : "");
        btn.innerHTML = `<span class="fp-asset-icon-sm">${getIcon(a.itemtype)}</span><span>${_esc(a.name)}</span>${placed ? '<span class="fp-badge-placed">no mapa</span>' : ""}`;
        btn.addEventListener("click", () => {
          this._placePin(a, this._pendingPos.pos_x, this._pendingPos.pos_y);
          this._markDirty();
          this._closePopup();
        });
        listEl.appendChild(btn);
      });
    };
    render("");
    searchInput.addEventListener("input", () => render(searchInput.value));
    setTimeout(() => searchInput.focus(), 50);
  };

  FloorPlanMap.prototype._openPopup = function (mx, my) {
    const popup = this._popup;
    popup.classList.add("fp-popup--open");
    popup.style.left = "-9999px"; popup.style.top = "-9999px";
    setTimeout(() => {
      const margin = 12;
      let left = mx + margin, top = my + margin;
      const pw = popup.offsetWidth || 300, ph = popup.offsetHeight || 350;
      if (left + pw > window.innerWidth)  left = mx - pw - margin;
      if (top  + ph > window.innerHeight) top  = my - ph - margin;
      if (left < 4) left = 4; if (top < 4) top = 4;
      popup.style.left = left + "px"; popup.style.top = top + "px";
    }, 0);
  };

  FloorPlanMap.prototype._closePopup = function () {
    if (this._popup) this._popup.classList.remove("fp-popup--open");
    this._pendingPos = null;
  };

  // ─── Pin ─────────────────────────────────────────────────────────
  FloorPlanMap.prototype._placePin = function (asset, pos_x, pos_y) {
    const key = `${asset.itemtype}|${asset.items_id}`;
    if (this._pins[key]) {
      this._pins[key].el.style.left = pos_x + "%";
      this._pins[key].el.style.top  = pos_y + "%";
      this._pins[key].pos_x = pos_x; this._pins[key].pos_y = pos_y;
      return;
    }
    const pin = document.createElement("div");
    pin.className   = "fp-pin";
    pin.dataset.key = key;
    pin.style.left  = pos_x + "%";
    pin.style.top   = pos_y + "%";
    // title removido — tooltip customizado via JS
    pin.innerHTML   = `
      <div class="fp-pin-icon">${getIcon(asset.itemtype)}</div>
      <div class="fp-pin-label">${_esc(asset.name)}</div>
      ${!this.cfg.readonly ? '<button class="fp-pin-remove" title="Remover">×</button>' : ""}
    `;
    if (!this.cfg.readonly) {
      this._makePinDraggable(pin);
      pin.querySelector(".fp-pin-remove").addEventListener("click", (e) => { e.stopPropagation(); this._removePin(key, asset); });
    }
    // Tooltip sempre ativo (edição e visualização)
    fetch(this.cfg.ajax_url + '?action=get_asset_details&itemtype=' + encodeURIComponent(asset.itemtype) + '&items_id=' + asset.items_id, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r => r.json()).then(data => {
      if (data.success) {
        this._attachTooltip(pin, data.details);
        // Guarda detalhes no pin para uso no exportImage
        this._pins[key].details = data.details;
      }
    }).catch(() => {});
    this.pinsLayer.appendChild(pin);
    this._pins[key] = { el: pin, pos_x, pos_y, asset };
  };

  FloorPlanMap.prototype._makePinDraggable = function (pin) {
    let dragging = false, startX, startY, origLeft, origTop;
    pin.addEventListener("mousedown", (e) => {
      if (e.target.classList.contains("fp-pin-remove")) return;
      e.preventDefault(); e.stopPropagation();
      dragging = true; startX = e.clientX; startY = e.clientY;
      origLeft = parseFloat(pin.style.left); origTop = parseFloat(pin.style.top);
      pin.classList.add("fp-pin--dragging");
      const onMove = (ev) => {
        if (!dragging) return;
        const rect = this.canvas.getBoundingClientRect();
        const newX = Math.max(0, Math.min(100, origLeft + ((ev.clientX - startX) / rect.width)  * 100));
        const newY = Math.max(0, Math.min(100, origTop  + ((ev.clientY - startY) / rect.height) * 100));
        pin.style.left = newX + "%"; pin.style.top = newY + "%";
        this._pins[pin.dataset.key].pos_x = newX; this._pins[pin.dataset.key].pos_y = newY;
      };
      const onUp = () => {
        dragging = false; pin.classList.remove("fp-pin--dragging");
        document.removeEventListener("mousemove", onMove);
        document.removeEventListener("mouseup", onUp);
        this._markDirty();
      };
      document.addEventListener("mousemove", onMove);
      document.addEventListener("mouseup", onUp);
    });
  };

  FloorPlanMap.prototype._removePin = function (key, asset) {
    const pin = this._pins[key];
    if (!pin) return;
    // Esconde tooltip antes de remover
    if (this._tooltip) this._tooltip.style.display = 'none';
    pin.el.remove(); delete this._pins[key]; this._markDirty();
    fetch(this.cfg.ajax_url + "?action=remove_item", {
      method  : "POST",
      headers : { "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest", "X-Glpi-Csrf-Token": getCsrfToken() },
      body    : JSON.stringify({ floorplan_id: this.cfg.floorplan_id, itemtype: asset.itemtype, items_id: asset.items_id }),
    }).catch(console.error);
  };

  FloorPlanMap.prototype.savePositions = function () {
    const positions = Object.values(this._pins).map(p => ({ itemtype: p.asset.itemtype, items_id: p.asset.items_id, pos_x: p.pos_x, pos_y: p.pos_y }));
    const status = this.saveStatus;
    const saveBtn = document.getElementById("fp-save-btn");
    if (saveBtn) saveBtn.disabled = true;
    status.textContent = "Salvando…"; status.className = "fp-save-status fp-save-status--loading";
    fetch(this.cfg.ajax_url + "?action=save_positions", {
      method  : "POST",
      headers : { "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest", "X-Glpi-Csrf-Token": getCsrfToken() },
      body    : JSON.stringify({ floorplan_id: this.cfg.floorplan_id, positions }),
    })
    .then(r => { if (!r.ok) throw new Error("HTTP " + r.status); return r.json(); })
    .then(data => {
      if (data.success) { status.textContent = "✓ Salvo! (" + (data.saved || positions.length) + " ativos)"; status.className = "fp-save-status fp-save-status--ok"; this.dirty = false; window.onbeforeunload = null; }
      else throw new Error(data.error || "Erro");
    })
    .catch(err => { status.textContent = "✗ Erro: " + err.message; status.className = "fp-save-status fp-save-status--error"; })
    .finally(() => { if (saveBtn) saveBtn.disabled = false; });
  };

  FloorPlanMap.prototype.zoom = function (delta) {
    this.scale = Math.max(0.3, Math.min(4, this.scale + delta));
    this.canvas.style.transform = `scale(${this.scale})`;
    this.canvas.style.transformOrigin = "top left";
  };
  FloorPlanMap.prototype.resetZoom = function () { this.scale = 1; this.canvas.style.transform = "scale(1)"; };
  FloorPlanMap.prototype._markDirty = function () { this.dirty = true; if (this.footer) this.footer.style.display = "flex"; };
  FloorPlanMap.prototype._showFooter = function () { if (this.footer) this.footer.style.display = "flex"; };
  FloorPlanMap.prototype._initPdfIfNeeded = function () {
    if (!window.FP_PDF_URL) return;
    const script = document.createElement("script");
    script.src = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js";
    script.onload = () => {
      const lib = window["pdfjs-dist/build/pdf"];
      lib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
      lib.getDocument(window.FP_PDF_URL).promise.then(pdf => {
        pdf.getPage(1).then(page => {
          const vp = page.getViewport({ scale: 1.5 });
          const c = document.getElementById("fp-pdf-canvas");
          c.width = vp.width; c.height = vp.height;
          page.render({ canvasContext: c.getContext("2d"), viewport: vp });
        });
      });
    };
    document.head.appendChild(script);
  };

  window.onbeforeunload = null;

  function _esc(str) {
    const d = document.createElement("div");
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
  }
})();
