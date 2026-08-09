(function () {
    'use strict';

    const cfg = window.LayoutDesignerConfig || {};
    const shell = document.querySelector('.layout-designer-shell');
    const canvas = document.getElementById('layout-canvas');
    const canvasScroll = document.getElementById('canvas-scroll');
    const palette = document.getElementById('palette-list');
    const layoutSelect = document.getElementById('layout-select');
    const layoutName = document.getElementById('layout-name');
    const layoutActive = document.getElementById('layout-active');
    const editModeButton = document.getElementById('layout-edit-mode');
    const modeBadge = document.getElementById('layout-mode-badge');
    const totalsBox = document.getElementById('layout-totals');
    const form = document.getElementById('element-form');
    const zoneModal = document.getElementById('layout-zone-modal');
    const zoneForm = document.querySelector('[data-zone-form]');
    const zoneAreaFields = zoneForm ? {
        x: zoneForm.querySelector('[data-zone-map-x]'),
        y: zoneForm.querySelector('[data-zone-map-y]'),
        width: zoneForm.querySelector('[data-zone-map-width]'),
        height: zoneForm.querySelector('[data-zone-map-height]')
    } : {};
    const zoneAreaSummary = zoneForm?.querySelector('[data-zone-area-summary]');

    const fields = {
        id: document.getElementById('element-id'),
        label: document.getElementById('prop-label'),
        x: document.getElementById('prop-x'),
        y: document.getElementById('prop-y'),
        width: document.getElementById('prop-width'),
        height: document.getElementById('prop-height'),
        tentGroup: document.getElementById('prop-tent-group'),
        tentType: document.getElementById('prop-tent-type'),
        stallCount: document.getElementById('prop-stall-count'),
        stallHelp: document.querySelector('[data-stall-count-help]'),
        category: document.getElementById('prop-category'),
        zone: document.getElementById('prop-zone')
    };

    const labels = {
        tent_50: '50-Seater Tent',
        tent_100: '100-Seater Tent',
        stage: 'STAGE',
        reg_desk: 'RD',
        waste_point: 'WCP',
        toilet_m: 'MT (M)',
        toilet_f: 'MT (F)',
        walkway: 'FLOW',
        label: 'Label'
    };

    const defaults = {
        tent_50: { width: 120, height: 120, tent_type: '50', stall_count: 4, category: 'sme', u_zone: 'retail_commercial' },
        tent_100: { width: 190, height: 110, tent_type: '100', stall_count: 8, category: 'sme', u_zone: 'retail_commercial' },
        stage: { width: 170, height: 420, u_zone: '' },
        reg_desk: { width: 100, height: 110, u_zone: '' },
        waste_point: { width: 100, height: 100, u_zone: '' },
        toilet_m: { width: 100, height: 110, u_zone: '' },
        toilet_f: { width: 100, height: 110, u_zone: '' },
        walkway: { width: 70, height: 110, u_zone: '' },
        label: { width: 180, height: 60, u_zone: '' }
    };

    let elements = [];
    let selectedId = null;
    let currentLayoutId = Number(cfg.initialLayoutId || 0);
    let zoom = 1;
    let snap = true;
    let editMode = Number(cfg.initialEditMode || 0) === 1;
    let dragState = null;
    let zoneSelectMode = false;
    let zoneSelectState = null;

    function uid() {
        return 'el_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    }

    function applyMode() {
        shell?.classList.toggle('is-viewer-mode', !editMode);
        shell?.classList.toggle('is-edit-mode', editMode);
        canvas?.classList.toggle('is-viewer-mode', !editMode);

        if (modeBadge) {
            modeBadge.textContent = editMode ? 'Live Edit Mode' : 'Viewer Mode';
            modeBadge.className = 'badge ' + (editMode ? 'badge-success' : 'badge-muted');
        }
        if (editModeButton) {
            editModeButton.innerHTML = editMode ? 'Viewer Mode' : '&#9998; Live Edit Mode';
            editModeButton.classList.toggle('button-primary', !editMode);
            editModeButton.classList.toggle('button-secondary', editMode);
        }

        if (!editMode) {
            selectedId = null;
            dragState = null;
            cancelZoneSelection(false);
        }

        render();
    }

    function isTent(type) {
        return type === 'tent_50' || type === 'tent_100';
    }

    function displayTentGroupCode(group) {
        return String(group || '').replace(/^TENT-0?/i, '');
    }

    function snapValue(value) {
        return snap ? Math.round(value / (cfg.defaultCanvas?.grid || 40)) * (cfg.defaultCanvas?.grid || 40) : Math.round(value);
    }

    function normalizeElement(raw) {
        const type = raw.element_type || raw.type || 'label';
        const base = defaults[type] || defaults.label;
        return {
            client_id: raw.client_id || uid(),
            element_type: type,
            tent_group_code: raw.tent_group_code || '',
            tent_type: raw.tent_type || base.tent_type || '',
            stall_count: Number(raw.stall_count || base.stall_count || 0),
            category: raw.category || base.category || '',
            u_zone: raw.u_zone || base.u_zone || '',
            x: Number(raw.x ?? 80),
            y: Number(raw.y ?? 80),
            width: Number(raw.width || base.width),
            height: Number(raw.height || base.height),
            rotation: Number(raw.rotation || 0),
            label: raw.label || labels[type] || 'Element',
            z_index: Number(raw.z_index || elements.length + 1)
        };
    }

    function rulesForTent(tentType) {
        return (cfg.arrangementRules || []).filter((rule) => String(rule.tent_code) === String(tentType));
    }

    function stallCountBounds(tentType) {
        const counts = rulesForTent(tentType).map((rule) => Number(rule.number_of_stalls)).filter((count) => Number.isFinite(count) && count > 0);
        if (!counts.length) return { min: 1, max: tentType === '100' ? 10 : 5 };
        return { min: Math.min(...counts), max: Math.max(...counts) };
    }

    function clampStallCount(value, tentType) {
        const bounds = stallCountBounds(tentType);
        const count = Number.isFinite(Number(value)) ? Math.round(Number(value)) : defaultStallCount(tentType);
        return Math.max(bounds.min, Math.min(bounds.max, count));
    }

    function defaultStallCount(tentType) {
        const target = tentType === '100' ? 8 : 4;
        const rules = rulesForTent(tentType).map((rule) => Number(rule.number_of_stalls));
        return rules.includes(target) ? target : (rules[0] || 1);
    }

    function presetSize(element, vertical = false) {
        if (element.element_type === 'tent_100') {
            return vertical ? { width: 110, height: 190 } : { width: 190, height: 110 };
        }
        if (element.element_type === 'tent_50') {
            return vertical ? { width: 120, height: 190 } : { width: 120, height: 120 };
        }
        return { width: element.width, height: element.height };
    }

    function addElement(type, x = 120, y = 120) {
        if (!editMode) return;
        const base = defaults[type] || defaults.label;
        const count = base.tent_type ? defaultStallCount(base.tent_type) : 0;
        const element = normalizeElement({
            element_type: type,
            x: snapValue(x),
            y: snapValue(y),
            label: labels[type] || 'Element',
            stall_count: count
        });
        if (isTent(type)) {
            const next = elements.filter((item) => isTent(item.element_type)).length + 1;
            element.tent_group_code = 'TENT-' + String(next).padStart(2, '0');
            element.label = String(next);
        }
        elements.push(element);
        selectedId = element.client_id;
        render();
    }

    function selectedElement() {
        return elements.find((element) => element.client_id === selectedId) || null;
    }

    function render() {
        canvas.querySelectorAll('.layout-zone-area').forEach((node) => node.remove());
        canvas.querySelectorAll('.layout-element').forEach((node) => node.remove());
        elements.sort((a, b) => a.z_index - b.z_index);
        renderZones();

        for (const element of elements) {
            const node = document.createElement('div');
            node.className = 'layout-element ' + element.element_type + (editMode && element.client_id === selectedId ? ' selected' : '');
            if (element.element_type === 'stage') node.classList.add('stage');
            node.dataset.id = element.client_id;
            node.style.left = element.x + 'px';
            node.style.top = element.y + 'px';
            node.style.width = element.width + 'px';
            node.style.height = element.height + 'px';
            node.style.zIndex = String(element.z_index);
            node.style.transform = 'rotate(' + element.rotation + 'deg)';
            node.textContent = elementLabel(element);

            if (isTent(element.element_type)) {
                const handle = document.createElement('span');
                handle.className = 'resize-handle';
                handle.dataset.resize = '1';
                node.appendChild(handle);
            }

            canvas.appendChild(node);
        }

        updateProperties();
        updateTotals();
    }

    function zoneArea(zone) {
        const x = Number(zone.map_x ?? '');
        const y = Number(zone.map_y ?? '');
        const width = Number(zone.map_width ?? '');
        const height = Number(zone.map_height ?? '');
        if (!Number.isFinite(x) || !Number.isFinite(y) || !Number.isFinite(width) || !Number.isFinite(height) || width <= 0 || height <= 0) {
            return null;
        }
        return { x, y, width, height };
    }

    function renderZones() {
        for (const zone of (cfg.zones || [])) {
            const area = zoneArea(zone);
            if (!area) continue;

            const node = document.createElement('div');
            node.className = 'layout-zone-area';
            node.style.left = area.x + 'px';
            node.style.top = area.y + 'px';
            node.style.width = area.width + 'px';
            node.style.height = area.height + 'px';
            node.title = zone.notes || zone.zone_name || 'Layout zone';
            node.textContent = zone.zone_name || 'Zone';
            canvas.appendChild(node);
        }
    }

    function elementLabel(element) {
        if (isTent(element.element_type)) {
            const group = element.tent_group_code || element.label || 'Tent';
            const bookings = cfg.tentBookings?.[group] || {};
            const configuredTotal = Number(element.stall_count || 0);
            const total = editMode ? configuredTotal : Number(bookings.total || configuredTotal || 0);
            const booked = Math.min(Number(bookings.booked || 0), total || Number(bookings.booked || 0));
            const count = total ? ' (' + booked + '/' + total + ')' : '';
            const visibleLabel = String(element.label || '').trim() || displayTentGroupCode(group) || 'Tent';
            return visibleLabel + count;
        }
        return element.label || labels[element.element_type] || 'Element';
    }

    function updateProperties() {
        const element = editMode ? selectedElement() : null;
        form.classList.toggle('is-empty', !element);
        if (!element) return;

        fields.id.value = element.client_id;
        fields.label.value = element.label || '';
        fields.x.value = element.x;
        fields.y.value = element.y;
        fields.width.value = element.width;
        fields.height.value = element.height;

        document.querySelectorAll('.tent-only').forEach((node) => node.classList.toggle('hidden', !isTent(element.element_type)));
        if (isTent(element.element_type)) {
            fields.tentGroup.value = element.tent_group_code || '';
            fields.tentType.value = element.tent_type || (element.element_type === 'tent_100' ? '100' : '50');
            populateStallOptions(fields.tentType.value, element.stall_count);
            fields.category.value = element.category || 'sme';
            populateZones(element.u_zone || 'retail_commercial');
        }
    }

    function populateStallOptions(tentType, selected) {
        const bounds = stallCountBounds(tentType);
        fields.stallCount.min = String(bounds.min);
        fields.stallCount.max = String(bounds.max);
        fields.stallCount.value = String(clampStallCount(selected, tentType));
        if (fields.stallHelp) {
            const presets = rulesForTent(tentType).map((rule) => rule.number_of_stalls + ' ' + rule.arrangement_name).join(', ');
            fields.stallHelp.textContent = 'Allowed range: ' + bounds.min + '-' + bounds.max + '. Presets: ' + (presets || 'none') + '. Custom counts inside the range are allowed.';
        }
    }

    function populateZones(selected) {
        fields.zone.innerHTML = '';
        for (const zone of (cfg.zones || [])) {
            const option = document.createElement('option');
            option.value = zone.zone_key;
            option.textContent = zone.zone_name;
            if (zone.zone_key === selected) option.selected = true;
            fields.zone.appendChild(option);
        }
    }

    function applyProperties() {
        if (!editMode) return;
        const element = selectedElement();
        if (!element) return;

        const previousTentLabel = isTent(element.element_type) ? displayTentGroupCode(element.tent_group_code) : '';
        const enteredLabel = fields.label.value.trim();
        element.x = snapValue(Number(fields.x.value || 0));
        element.y = snapValue(Number(fields.y.value || 0));
        element.width = Math.max(20, Number(fields.width.value || element.width));
        element.height = Math.max(20, Number(fields.height.value || element.height));

        if (isTent(element.element_type)) {
            const tentType = fields.tentType.value;
            const nextTentGroup = fields.tentGroup.value.trim().toUpperCase();
            element.element_type = tentType === '100' ? 'tent_100' : 'tent_50';
            element.tent_type = tentType;
            element.tent_group_code = nextTentGroup;
            element.label = enteredLabel === '' || enteredLabel === previousTentLabel ? (displayTentGroupCode(nextTentGroup) || labels[element.element_type] || 'Tent') : enteredLabel;
            element.stall_count = clampStallCount(fields.stallCount.value, tentType);
            element.category = fields.category.value;
            element.u_zone = fields.zone.value;
        } else {
            element.label = enteredLabel || labels[element.element_type] || 'Element';
        }

        render();
    }

    function updateTotals() {
        const totalTents = elements.filter((element) => isTent(element.element_type)).length;
        const totalStalls = elements.reduce((sum, element) => sum + (isTent(element.element_type) ? Number(element.stall_count || 0) : 0), 0);
        const byCategory = {};
        for (const element of elements) {
            if (isTent(element.element_type)) {
                byCategory[element.category || 'uncategorized'] = (byCategory[element.category || 'uncategorized'] || 0) + Number(element.stall_count || 0);
            }
        }
        const lines = [
            ['Total tents', totalTents],
            ['Total stalls', totalStalls],
            ...Object.entries(byCategory).map(([key, value]) => [key.replace('_', ' '), value])
        ];
        totalsBox.innerHTML = lines.map(([label, value]) => '<div class="total-line"><span>' + escapeHtml(label) + '</span><strong>' + value + '</strong></div>').join('');
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[char]));
    }

    function canvasPoint(event) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (event.clientX - rect.left) / zoom,
            y: (event.clientY - rect.top) / zoom
        };
    }

    function canvasBounds() {
        return {
            width: cfg.defaultCanvas?.width || canvas.offsetWidth || 1200,
            height: cfg.defaultCanvas?.height || canvas.offsetHeight || 1600
        };
    }

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function zoneAreaFromPoints(startX, startY, endX, endY) {
        const bounds = canvasBounds();
        const grid = cfg.defaultCanvas?.grid || 40;
        let x = Math.min(startX, endX);
        let y = Math.min(startY, endY);
        let width = Math.abs(endX - startX);
        let height = Math.abs(endY - startY);

        if (snap) {
            x = snapValue(x);
            y = snapValue(y);
            width = snapValue(width);
            height = snapValue(height);
        } else {
            x = Math.round(x);
            y = Math.round(y);
            width = Math.round(width);
            height = Math.round(height);
        }

        width = Math.max(grid, width);
        height = Math.max(grid, height);
        x = clamp(x, 0, Math.max(0, bounds.width - width));
        y = clamp(y, 0, Math.max(0, bounds.height - height));
        width = Math.min(width, bounds.width - x);
        height = Math.min(height, bounds.height - y);

        return { x, y, width, height };
    }

    function updateZoneAreaSummary() {
        if (!zoneAreaSummary || !zoneAreaFields.x) return;
        const area = zoneArea({
            map_x: zoneAreaFields.x.value,
            map_y: zoneAreaFields.y.value,
            map_width: zoneAreaFields.width.value,
            map_height: zoneAreaFields.height.value
        });
        zoneAreaSummary.textContent = area ? 'Selected: x ' + area.x + ', y ' + area.y + ', ' + area.width + ' x ' + area.height + ' px' : 'No map area selected yet.';
    }

    function setZoneFormArea(area) {
        if (!zoneAreaFields.x) return;
        zoneAreaFields.x.value = String(area.x);
        zoneAreaFields.y.value = String(area.y);
        zoneAreaFields.width.value = String(area.width);
        zoneAreaFields.height.value = String(area.height);
        updateZoneAreaSummary();
    }

    function startZoneMapSelection() {
        if (!editMode || !zoneForm) return;
        zoneSelectMode = true;
        zoneSelectState = null;
        if (zoneModal) zoneModal.hidden = true;
        canvas.classList.add('is-zone-selecting');
        canvasScroll?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        window.showToast?.('Drag on the layout map to select the zone area.', 'info');
    }

    function updateZoneSelectionBox(event) {
        if (!zoneSelectState) return null;
        const point = canvasPoint(event);
        const area = zoneAreaFromPoints(zoneSelectState.startX, zoneSelectState.startY, point.x, point.y);
        zoneSelectState.box.style.left = area.x + 'px';
        zoneSelectState.box.style.top = area.y + 'px';
        zoneSelectState.box.style.width = area.width + 'px';
        zoneSelectState.box.style.height = area.height + 'px';
        return area;
    }

    function beginZoneSelection(event) {
        event.preventDefault();
        event.stopPropagation();
        const point = canvasPoint(event);
        const box = document.createElement('div');
        box.className = 'zone-selection-box';
        canvas.appendChild(box);
        zoneSelectState = { startX: point.x, startY: point.y, box };
        updateZoneSelectionBox(event);
        canvas.setPointerCapture?.(event.pointerId);
    }

    function finishZoneSelection(event) {
        const area = updateZoneSelectionBox(event);
        zoneSelectState?.box.remove();
        zoneSelectState = null;
        zoneSelectMode = false;
        canvas.classList.remove('is-zone-selecting');
        if (area) setZoneFormArea(area);
        if (zoneModal) zoneModal.hidden = false;
        zoneForm?.querySelector('[name="zone_name"]')?.focus();
    }

    function cancelZoneSelection(showModal = true) {
        zoneSelectState?.box.remove();
        zoneSelectState = null;
        zoneSelectMode = false;
        canvas.classList.remove('is-zone-selecting');
        if (showModal && zoneModal) zoneModal.hidden = false;
    }

    function saveLayout() {
        if (!editMode) return;
        const payload = {
            layout_id: currentLayoutId,
            name: layoutName.value.trim() || 'Untitled Layout',
            is_active: layoutActive.checked ? 1 : 0,
            elements: elements.map(({ client_id, ...element }) => element)
        };
        const body = new FormData();
        body.append('csrf_token', cfg.csrfToken || '');
        body.append('payload', JSON.stringify(payload));
        document.getElementById('save-layout').disabled = true;
        fetch(cfg.saveUrl, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    window.appAlert?.({
                        title: 'Layout Warning',
                        message: data.message || 'The layout could not be saved.',
                        danger: true
                    });
                    return;
                }
                window.showToast?.(data.message || 'Layout saved.', 'success');
                if (data.ok && data.layout_id) {
                    currentLayoutId = Number(data.layout_id);
                    loadLayout(currentLayoutId, true);
                }
            })
            .catch(() => window.appAlert?.({ title: 'Save Failed', message: 'Unable to save layout. Please try again.', danger: true }))
            .finally(() => { document.getElementById('save-layout').disabled = false; });
    }

    function loadLayout(layoutId, refreshList = false) {
        const url = cfg.loadUrl + (layoutId ? '?layout_id=' + encodeURIComponent(layoutId) : '');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) throw new Error('Load failed');
                cfg.arrangementRules = data.arrangementRules || cfg.arrangementRules || [];
                cfg.zones = data.zones || cfg.zones || [];
                cfg.tentBookings = data.tentBookings || cfg.tentBookings || {};
                if (refreshList || data.layouts) refreshLayoutSelect(data.layouts || []);
                if (data.layout) {
                    currentLayoutId = Number(data.layout.id);
                    layoutName.value = data.layout.name || '';
                    layoutActive.checked = Number(data.layout.is_active) === 1;
                }
                elements = (data.elements || []).map((element) => normalizeElement({ ...element, client_id: 'db_' + element.id }));
                selectedId = null;
                populateZones('retail_commercial');
                render();
            })
            .catch(() => window.showToast?.('Unable to load layout.', 'error'));
    }

    function refreshLayoutSelect(layouts) {
        if (!layoutSelect) return;
        layoutSelect.innerHTML = '';
        if (!layouts.length) {
            const option = document.createElement('option');
            option.value = '0';
            option.textContent = 'New Layout';
            layoutSelect.appendChild(option);
            return;
        }
        for (const layout of layouts) {
            const option = document.createElement('option');
            option.value = String(layout.id);
            option.textContent = layout.name + (Number(layout.is_active) === 1 ? ' (Active)' : '');
            if (Number(layout.id) === currentLayoutId) option.selected = true;
            layoutSelect.appendChild(option);
        }
    }

    function exportPng() {
        const output = document.createElement('canvas');
        output.width = cfg.defaultCanvas?.width || 1200;
        output.height = cfg.defaultCanvas?.height || 1600;
        const ctx = output.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, output.width, output.height);
        ctx.strokeStyle = '#e2e2e2';
        for (let x = 0; x < output.width; x += 40) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, output.height); ctx.stroke(); }
        for (let y = 0; y < output.height; y += 40) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(output.width, y); ctx.stroke(); }
        ctx.font = '700 18px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        for (const zone of (cfg.zones || [])) {
            const area = zoneArea(zone);
            if (!area) continue;
            ctx.fillStyle = 'rgba(255, 107, 0, 0.14)';
            ctx.strokeStyle = 'rgba(255, 107, 0, 0.75)';
            ctx.lineWidth = 3;
            ctx.fillRect(area.x, area.y, area.width, area.height);
            ctx.strokeRect(area.x, area.y, area.width, area.height);
            ctx.fillStyle = '#a04100';
            ctx.fillText(zone.zone_name || 'Zone', area.x + area.width / 2, area.y + area.height / 2, area.width - 16);
        }
        for (const element of elements) {
            ctx.save();
            ctx.translate(element.x + element.width / 2, element.y + element.height / 2);
            ctx.rotate((element.rotation || 0) * Math.PI / 180);
            ctx.fillStyle = element.element_type === 'stage' ? '#4675c4' : (isTent(element.element_type) ? '#ff6b00' : '#2f3131');
            if (element.element_type === 'label') ctx.fillStyle = '#ffffff';
            ctx.strokeStyle = '#a04100';
            ctx.lineWidth = 2;
            ctx.fillRect(-element.width / 2, -element.height / 2, element.width, element.height);
            ctx.strokeRect(-element.width / 2, -element.height / 2, element.width, element.height);
            ctx.fillStyle = element.element_type === 'label' ? '#1a1c1c' : '#ffffff';
            ctx.fillText(elementLabel(element), 0, 0, element.width - 12);
            ctx.restore();
        }
        const link = document.createElement('a');
        link.download = (layoutName.value || 'venue-layout').toLowerCase().replace(/[^a-z0-9]+/g, '-') + '.png';
        link.href = output.toDataURL('image/png');
        link.click();
    }

    palette?.addEventListener('dragstart', (event) => {
        if (!editMode) {
            event.preventDefault();
            return;
        }
        const item = event.target.closest('[data-type]');
        if (!item) return;
        event.dataTransfer.setData('text/plain', item.dataset.type);
    });

    palette?.addEventListener('click', (event) => {
        if (!editMode) return;
        const item = event.target.closest('[data-type]');
        if (!item) return;
        addElement(item.dataset.type, 160 + elements.length * 15, 160 + elements.length * 15);
    });

    canvas.addEventListener('dragover', (event) => {
        if (editMode) event.preventDefault();
    });
    canvas.addEventListener('drop', (event) => {
        event.preventDefault();
        if (!editMode) return;
        const type = event.dataTransfer.getData('text/plain');
        if (!type) return;
        const point = canvasPoint(event);
        addElement(type, point.x, point.y);
    });

    canvas.addEventListener('pointerdown', (event) => {
        if (zoneSelectMode) {
            beginZoneSelection(event);
            return;
        }

        if (!editMode) return;

        const node = event.target.closest('.layout-element');
        if (!node) {
            selectedId = null;
            render();
            return;
        }
        const element = elements.find((item) => item.client_id === node.dataset.id);
        if (!element) return;
        selectedId = element.client_id;
        const point = canvasPoint(event);
        dragState = {
            mode: event.target.dataset.resize ? 'resize' : 'move',
            id: element.client_id,
            startX: point.x,
            startY: point.y,
            elementX: element.x,
            elementY: element.y,
            width: element.width,
            height: element.height
        };
        node.setPointerCapture(event.pointerId);
        canvas.querySelectorAll('.layout-element').forEach((item) => item.classList.remove('selected'));
        node.classList.add('selected');
        updateProperties();
    });

    canvas.addEventListener('pointermove', (event) => {
        if (zoneSelectState) {
            updateZoneSelectionBox(event);
            return;
        }

        if (!dragState) return;
        const element = elements.find((item) => item.client_id === dragState.id);
        if (!element) return;
        const point = canvasPoint(event);
        const dx = point.x - dragState.startX;
        const dy = point.y - dragState.startY;
        if (dragState.mode === 'move') {
            element.x = snapValue(dragState.elementX + dx);
            element.y = snapValue(dragState.elementY + dy);
        } else if (isTent(element.element_type)) {
            const vertical = Math.abs(dy) > Math.abs(dx);
            const size = presetSize(element, vertical);
            element.width = size.width;
            element.height = size.height;
        }
        const node = canvas.querySelector('.layout-element[data-id="' + element.client_id + '"]');
        if (node) {
            node.style.left = element.x + 'px';
            node.style.top = element.y + 'px';
            node.style.width = element.width + 'px';
            node.style.height = element.height + 'px';
        }
        updateProperties();
    });

    window.addEventListener('pointerup', (event) => {
        if (zoneSelectState) {
            finishZoneSelection(event);
            return;
        }

        if (dragState) render();
        dragState = null;
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        applyProperties();
        window.showToast?.('Element changes applied. Save the layout to persist them.', 'success');
    });

    form.addEventListener('change', (event) => {
        if (!editMode) return;
        if (event.target.matches('input, select')) applyProperties();
    });

    fields.tentType.addEventListener('change', () => {
        if (!editMode) return;
        const tentType = fields.tentType.value;
        populateStallOptions(tentType, fields.stallCount.value || defaultStallCount(tentType));
    });

    document.getElementById('rotate-element').addEventListener('click', () => {
        if (!editMode) return;
        const element = selectedElement();
        if (!element) return;
        element.rotation = (Number(element.rotation || 0) + 90) % 360;
        if (isTent(element.element_type)) {
            const width = element.width;
            element.width = element.height;
            element.height = width;
        }
        render();
    });

    document.getElementById('duplicate-element').addEventListener('click', () => {
        if (!editMode) return;
        const element = selectedElement();
        if (!element) return;
        const copy = normalizeElement({ ...element, client_id: uid(), x: element.x + 40, y: element.y + 40, z_index: elements.length + 1 });
        if (isTent(copy.element_type)) copy.tent_group_code = copy.tent_group_code ? copy.tent_group_code + '-COPY' : '';
        elements.push(copy);
        selectedId = copy.client_id;
        render();
    });

    document.getElementById('delete-element').addEventListener('click', async () => {
        if (!editMode) return;
        if (!selectedId) return;
        const confirmed = await window.appConfirm?.({
            title: 'Delete Element?',
            message: 'This removes the element from the layout. If this is a tent with assigned stalls, saving will be blocked until those stalls are released.',
            confirmText: 'Delete Element',
            cancelText: 'Keep Element',
            danger: true
        });
        if (!confirmed) return;
        elements = elements.filter((element) => element.client_id !== selectedId);
        selectedId = null;
        render();
    });

    document.getElementById('clear-selection').addEventListener('click', () => { selectedId = null; render(); });
    document.getElementById('save-layout').addEventListener('click', saveLayout);
    document.getElementById('export-layout').addEventListener('click', exportPng);
    document.getElementById('new-layout').addEventListener('click', () => {
        if (!editMode) return;
        currentLayoutId = 0;
        layoutName.value = 'New Layout ' + new Date().toLocaleString();
        layoutActive.checked = false;
        elements = [];
        selectedId = null;
        render();
    });
    editModeButton?.addEventListener('click', () => {
        editMode = !editMode;
        applyMode();
    });
    document.querySelectorAll('[data-zone-map-select]').forEach((button) => button.addEventListener('click', startZoneMapSelection));
    layoutSelect?.addEventListener('change', () => loadLayout(Number(layoutSelect.value || 0)));

    document.getElementById('snap-toggle').addEventListener('change', (event) => { snap = event.target.checked; });
    document.getElementById('zoom-in').addEventListener('click', () => setZoom(Math.min(1.8, zoom + 0.1)));
    document.getElementById('zoom-out').addEventListener('click', () => setZoom(Math.max(0.4, zoom - 0.1)));
    document.getElementById('zoom-fit').addEventListener('click', () => {
        const available = canvasScroll.clientWidth - 60;
        setZoom(Math.max(0.4, Math.min(1, available / (cfg.defaultCanvas?.width || 1200))));
    });
    document.getElementById('zones-only-toggle')?.addEventListener('change', (event) => {
        canvas.classList.toggle('zones-only', event.target.checked);
        if (event.target.checked) {
            selectedId = null;
            updateProperties();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && zoneSelectMode) {
            cancelZoneSelection();
        }
    });

    function setZoom(value) {
        zoom = Number(value.toFixed(2));
        canvas.style.transform = 'scale(' + zoom + ')';
        document.getElementById('zoom-label').textContent = Math.round(zoom * 100) + '%';
    }

    populateZones('retail_commercial');
    populateStallOptions('50', 4);
    updateZoneAreaSummary();
    applyMode();
    loadLayout(currentLayoutId, true);
})();
