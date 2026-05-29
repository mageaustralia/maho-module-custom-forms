/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Two-pane schema-first form builder (admin). Vanilla ES, no jQuery/Prototype.
 *
 *   Left  (canvas)    - a live preview of the form as it will actually render:
 *                       each field shows its label + the real control. Click a
 *                       field to select it; reorder/delete via the hover rail.
 *   Right (inspector) - the properties of the currently selected field. Editing
 *                       a property updates the preview live.
 *
 * A plain JS `fields` array is the single source of truth. Every change
 * re-renders the canvas and serialises the schema into the hidden #schema
 * field (also on form submit, belt + braces).
 */
window.CustomFormsBuilder = (function () {
    'use strict';

    const CHOICE_TYPES = ['select', 'radio', 'checkbox', 'multiselect'];
    const TEXTY_TYPES  = ['text', 'textarea', 'email', 'phone', 'number'];
    const WIDTHS = ['full', 'half', 'third'];

    let root, canvas, inspector, schemaField, baseSchema;
    let fields = [];
    let selected = -1;

    function init(rootEl) {
        if (!rootEl || rootEl.dataset.cfInit) { return; }
        rootEl.dataset.cfInit = '1';
        root = rootEl;
        canvas = document.getElementById('cf-field-list');
        inspector = document.getElementById('cf-inspector');
        schemaField = document.getElementById(rootEl.dataset.schemaField || 'schema');

        let parsed = {};
        try { parsed = JSON.parse(document.getElementById('cf-builder-schema').textContent) || {}; } catch (e) { parsed = {}; }
        baseSchema = parsed;
        fields = Array.isArray(parsed.fields) ? parsed.fields.map(normalise) : [];

        // "Add" type dropdown.
        const fieldTypes = JSON.parse(rootEl.dataset.fieldTypes || '[]');
        const sel = document.getElementById('cf-add-type');
        fieldTypes.forEach(function (ft) {
            const o = document.createElement('option');
            o.value = ft.type; o.textContent = ft.label;
            sel.appendChild(o);
        });

        document.getElementById('cf-add-btn').addEventListener('click', function () {
            const type = sel.value;
            fields.push(normalise({ type: type, key: suggestKey(type), label: '', width: 'full' }));
            selected = fields.length - 1;
            renderAll();
        });
        document.getElementById('cf-toggle-json').addEventListener('click', function (e) {
            e.preventDefault();
            const pre = document.getElementById('cf-json-preview');
            const show = pre.style.display === 'none';
            pre.style.display = show ? 'block' : 'none';
            this.textContent = show ? 'Hide JSON' : 'Show JSON';
        });

        const form = schemaField ? schemaField.form : null;
        if (form) { form.addEventListener('submit', serialize); }

        renderAll();
    }

    /* ---------- model helpers ---------- */

    function normalise(f) {
        f = f || {};
        return {
            type: f.type || 'text',
            key: f.key || '',
            label: f.label != null ? f.label : '',
            width: f.width || 'full',
            required: !!f.required,
            placeholder: f.placeholder != null ? f.placeholder : '',
            options: Array.isArray(f.options) ? f.options.slice() : [],
            validate: f.validate ? Object.assign({}, f.validate) : {},
            showIf: f.showIf ? Object.assign({}, f.showIf) : {},
        };
    }

    function isChoice(type) { return CHOICE_TYPES.indexOf(type) !== -1; }
    function isTexty(type) { return TEXTY_TYPES.indexOf(type) !== -1; }

    function suggestKey(type) {
        let n = fields.length + 1;
        let key;
        do { key = type + '_' + n; n++; } while (fields.some(function (f) { return f.key === key; }));
        return key;
    }

    function el(tag, cls, text) {
        const e = document.createElement(tag);
        if (cls) { e.className = cls; }
        if (text != null) { e.textContent = text; }
        return e;
    }

    // Inline-SVG icon. `stroke=true` for line icons (the X); default filled.
    function icon(d, stroke) {
        const ns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 10 10');
        svg.setAttribute('width', '12'); svg.setAttribute('height', '12');
        svg.setAttribute('aria-hidden', 'true');
        const p = document.createElementNS(ns, 'path');
        p.setAttribute('d', d);
        if (stroke) {
            p.setAttribute('fill', 'none'); p.setAttribute('stroke', 'currentColor');
            p.setAttribute('stroke-width', '1.6'); p.setAttribute('stroke-linecap', 'round');
        } else {
            p.setAttribute('fill', 'currentColor');
        }
        svg.appendChild(p);
        return svg;
    }

    function iconBtn(cls, title, d, stroke, onClick) {
        const b = el('button', 'cf-icon' + (cls ? ' ' + cls : ''));
        b.type = 'button'; b.title = title;
        b.appendChild(icon(d, stroke));
        b.addEventListener('click', function (e) { e.stopPropagation(); onClick(); });
        return b;
    }

    /* ---------- canvas (live preview) ---------- */

    function renderAll() {
        renderCanvas();
        renderInspector();
        serialize();
    }

    function renderCanvas() {
        canvas.textContent = '';
        document.getElementById('cf-empty').style.display = fields.length ? 'none' : 'block';

        fields.forEach(function (f, i) {
            const li = el('li', 'cf-prev' + (i === selected ? ' is-selected' : ''));
            li.dataset.width = f.width || 'full';
            li.addEventListener('click', function () { selected = i; renderCanvas(); renderInspector(); });

            // hover/selected rail: up / down / delete
            const rail = el('div', 'cf-prev__rail');
            rail.appendChild(iconBtn('', 'Move up', 'M5 2.5 L8.5 7.5 L1.5 7.5 Z', false, function () { move(i, -1); }));
            rail.appendChild(iconBtn('', 'Move down', 'M1.5 2.5 L8.5 2.5 L5 7.5 Z', false, function () { move(i, 1); }));
            rail.appendChild(iconBtn('cf-icon--del', 'Remove', 'M2.5 2.5 L7.5 7.5 M7.5 2.5 L2.5 7.5', true, function () { remove(i); }));
            li.appendChild(rail);

            li.appendChild(previewControl(f));
            canvas.appendChild(li);
        });
    }

    // Render a field the way it will appear on the real form.
    function previewControl(f) {
        const wrap = el('div', 'cf-prev__body');

        if (f.type === 'heading') {
            wrap.appendChild(el('div', 'cf-prev__heading', f.label || 'Heading'));
            return wrap;
        }

        const lbl = el('div', 'cf-prev__label', f.label || placeholderName(f));
        if (f.required) { lbl.appendChild(el('span', 'cf-prev__req', ' *')); }
        wrap.appendChild(lbl);

        let control;
        if (f.type === 'textarea') {
            control = el('div', 'cf-prev__control cf-prev__control--area', f.placeholder || '');
        } else if (f.type === 'select' || f.type === 'multiselect') {
            control = el('div', 'cf-prev__control cf-prev__control--select');
            const opts = f.options.length ? f.options : [{ label: 'Option 1' }];
            control.textContent = (opts[0].label || opts[0].value || 'Option') + (f.type === 'multiselect' ? ' (multiple)' : '');
            control.appendChild(el('span', 'cf-prev__caret', ' v'));
        } else if (f.type === 'radio' || f.type === 'checkbox') {
            control = el('div', 'cf-prev__choices');
            const opts = f.options.length ? f.options : [{ label: 'Option 1' }, { label: 'Option 2' }];
            opts.forEach(function (o) {
                const row = el('label', 'cf-prev__choice');
                row.appendChild(el('span', 'cf-prev__box cf-prev__box--' + (f.type === 'radio' ? 'radio' : 'check')));
                row.appendChild(el('span', null, o.label || o.value || 'Option'));
                control.appendChild(row);
            });
        } else if (f.type === 'file') {
            control = el('div', 'cf-prev__control cf-prev__control--file', 'Choose file...');
        } else {
            control = el('div', 'cf-prev__control', f.placeholder || '');
        }
        wrap.appendChild(control);
        return wrap;
    }

    function placeholderName(f) {
        return f.key ? f.key : (f.type.charAt(0).toUpperCase() + f.type.slice(1));
    }

    function move(i, dir) {
        const j = i + dir;
        if (j < 0 || j >= fields.length) { return; }
        const tmp = fields[i]; fields[i] = fields[j]; fields[j] = tmp;
        if (selected === i) { selected = j; } else if (selected === j) { selected = i; }
        renderAll();
    }

    function remove(i) {
        fields.splice(i, 1);
        if (selected === i) { selected = -1; }
        else if (selected > i) { selected--; }
        renderAll();
    }

    /* ---------- inspector (properties of selected field) ---------- */

    function renderInspector() {
        inspector.textContent = '';
        if (selected < 0 || !fields[selected]) {
            inspector.appendChild(el('p', 'cf-inspector__empty',
                'Select a field on the left to edit its properties, or add a new one.'));
            return;
        }
        const f = fields[selected];

        const head = el('div', 'cf-inspector__head');
        head.appendChild(el('span', 'cf-inspector__type', f.type));
        inspector.appendChild(head);

        inspector.appendChild(keyProp(f.key));
        inspector.appendChild(textProp('Label', 'label', f.label, 'Field label'));
        inspector.appendChild(selectProp('Width', 'width', WIDTHS, f.width));
        inspector.appendChild(checkProp('Required', 'required', f.required));

        if (f.type !== 'heading' && f.type !== 'file') {
            inspector.appendChild(textProp('Placeholder', 'placeholder', f.placeholder, ''));
        }
        if (isChoice(f.type)) {
            inspector.appendChild(optionsProp(f));
        }
        if (isTexty(f.type)) {
            inspector.appendChild(validateProp('Min length', 'minLength', f));
            inspector.appendChild(validateProp('Max length', 'maxLength', f));
            inspector.appendChild(patternProp(f));
        }
        const cond = el('div', 'cf-insp-group');
        cond.appendChild(el('div', 'cf-insp-group__title', 'Conditional display'));
        cond.appendChild(showIfProp('Show if field', 'field', f, 'other_field_key'));
        cond.appendChild(showIfProp('equals', 'eq', f, ''));
        inspector.appendChild(cond);
    }

    function field(labelText) {
        const w = el('div', 'cf-insp-field');
        w.appendChild(el('label', null, labelText));
        return w;
    }

    function commit() { renderCanvas(); serialize(); }

    // Field keys become the stored payload key + HTML input name, so keep
    // them clean: lowercase, spaces -> underscore, drop anything else.
    function sanitiseKey(v) {
        return String(v).toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
    }

    function keyProp(value) {
        const w = field('Key');
        const i = document.createElement('input');
        i.type = 'text'; i.className = 'input-text'; i.value = value || ''; i.placeholder = 'field_key';
        i.addEventListener('input', function () {
            const raw = i.value;
            const caret = i.selectionStart || 0;
            const clean = sanitiseKey(raw);
            if (clean !== raw) {
                const before = sanitiseKey(raw.slice(0, caret));
                i.value = clean;
                try { i.setSelectionRange(before.length, before.length); } catch (e) {}
            }
            fields[selected].key = i.value;
            commit();
        });
        w.appendChild(i);
        return w;
    }

    // Common format presets so people rarely need to hand-write regex.
    // Entries are either {label, value} options or {group, items:[...]} groups.
    const PATTERN_PRESETS = [
        { label: 'No pattern',              value: '' },
        { label: 'Letters and spaces only', value: '^[A-Za-z ]+$' },
        { label: 'Numbers only',            value: '^[0-9]+$' },
        { group: 'Postcode / ZIP', items: [
            { label: 'Australia (4 digits)',          value: '^\\d{4}$' },
            { label: 'New Zealand (4 digits)',        value: '^\\d{4}$' },
            { label: 'United States (ZIP / ZIP+4)',   value: '^\\d{5}(-\\d{4})?$' },
            { label: 'United Kingdom',                value: '^[A-Za-z]{1,2}\\d[A-Za-z\\d]? ?\\d[A-Za-z]{2}$' },
            { label: 'Canada',                        value: '^[A-Za-z]\\d[A-Za-z] ?\\d[A-Za-z]\\d$' },
            { label: 'Germany / France / Spain / Italy (5 digits)', value: '^\\d{5}$' },
            { label: 'Netherlands (1234 AB)',         value: '^\\d{4} ?[A-Za-z]{2}$' },
            { label: 'India (6 digits)',              value: '^\\d{6}$' },
            { label: 'Japan (123-4567)',              value: '^\\d{3}-?\\d{4}$' },
            { label: 'Generic (international)',        value: '^[A-Za-z0-9 -]{2,12}$' },
        ] },
        { label: 'Custom...',               value: '__custom__' },
    ];

    // Flatten groups to a single list of options for lookups.
    function flatPresets() {
        const out = [];
        PATTERN_PRESETS.forEach(function (p) {
            if (p.items) { p.items.forEach(function (it) { out.push(it); }); }
            else { out.push(p); }
        });
        return out;
    }

    function patternProp(f) {
        const w = field('Pattern');
        const cur = (f.validate && f.validate.pattern != null) ? String(f.validate.pattern) : '';
        const known = flatPresets().some(function (p) { return p.value === cur && p.value !== '__custom__'; });
        const isCustom = cur !== '' && !known;

        const sel = document.createElement('select');
        let picked = false; // first matching value wins (some share a regex, e.g. AU/NZ)
        PATTERN_PRESETS.forEach(function (p) {
            if (p.items) {
                const og = document.createElement('optgroup'); og.label = p.group;
                p.items.forEach(function (it) {
                    const o = document.createElement('option'); o.value = it.value; o.textContent = it.label;
                    if (!isCustom && !picked && it.value === cur) { o.selected = true; picked = true; }
                    og.appendChild(o);
                });
                sel.appendChild(og);
            } else {
                const o = document.createElement('option'); o.value = p.value; o.textContent = p.label;
                if (isCustom && p.value === '__custom__') { o.selected = true; }
                else if (!isCustom && !picked && p.value === cur) { o.selected = true; picked = true; }
                sel.appendChild(o);
            }
        });

        const custom = document.createElement('input');
        custom.type = 'text'; custom.className = 'input-text'; custom.placeholder = '^...$';
        custom.style.marginTop = '6px';
        custom.value = isCustom ? cur : '';
        custom.style.display = isCustom ? '' : 'none';

        function apply(val) {
            fields[selected].validate = fields[selected].validate || {};
            if (val === '' || val === '__custom__') { delete fields[selected].validate.pattern; }
            else { fields[selected].validate.pattern = val; }
            commit();
        }
        sel.addEventListener('change', function () {
            if (sel.value === '__custom__') {
                custom.style.display = ''; apply(custom.value); custom.focus();
            } else {
                custom.style.display = 'none'; apply(sel.value);
            }
        });
        custom.addEventListener('input', function () { apply(custom.value); });

        w.appendChild(sel);
        w.appendChild(custom);
        return w;
    }

    function textProp(labelText, prop, value, ph) {
        const w = field(labelText);
        const i = document.createElement('input');
        i.type = 'text'; i.className = 'input-text'; i.value = value || '';
        if (ph) { i.placeholder = ph; }
        i.addEventListener('input', function () { fields[selected][prop] = i.value; commit(); });
        w.appendChild(i);
        return w;
    }

    function selectProp(labelText, prop, opts, value) {
        const w = field(labelText);
        const s = document.createElement('select');
        opts.forEach(function (o) {
            const op = document.createElement('option'); op.value = o; op.textContent = o;
            if ((value || opts[0]) === o) { op.selected = true; }
            s.appendChild(op);
        });
        s.addEventListener('change', function () { fields[selected][prop] = s.value; commit(); });
        w.appendChild(s);
        return w;
    }

    function checkProp(labelText, prop, value) {
        const w = el('div', 'cf-insp-field cf-insp-field--check');
        const i = document.createElement('input'); i.type = 'checkbox'; i.checked = !!value;
        i.addEventListener('change', function () { fields[selected][prop] = i.checked; commit(); });
        const lab = el('label', null); lab.appendChild(i); lab.appendChild(el('span', null, ' ' + labelText));
        w.appendChild(lab);
        return w;
    }

    function optionsProp(f) {
        const w = field('Options (value|Label, one per line)');
        const ta = document.createElement('textarea'); ta.rows = 4; ta.className = 'cf-insp-textarea';
        ta.value = (f.options || []).map(function (o) {
            return o.value + '|' + (o.label != null ? o.label : o.value);
        }).join('\n');
        ta.addEventListener('input', function () {
            fields[selected].options = ta.value.split('\n').map(function (line) {
                const p = line.split('|');
                const v = (p[0] || '').trim();
                if (v === '') { return null; }
                return { value: v, label: (p[1] != null ? p[1] : p[0]).trim() };
            }).filter(Boolean);
            commit();
        });
        w.appendChild(ta);
        return w;
    }

    function validateProp(labelText, key, f) {
        const w = field(labelText);
        const i = document.createElement('input'); i.type = 'text'; i.className = 'input-text';
        i.value = (f.validate && f.validate[key] != null) ? f.validate[key] : '';
        i.addEventListener('input', function () {
            fields[selected].validate = fields[selected].validate || {};
            if (i.value === '') { delete fields[selected].validate[key]; }
            else { fields[selected].validate[key] = (key === 'pattern') ? i.value : parseInt(i.value, 10); }
            commit();
        });
        w.appendChild(i);
        return w;
    }

    function showIfProp(labelText, key, f, ph) {
        const w = field(labelText);
        const i = document.createElement('input'); i.type = 'text'; i.className = 'input-text';
        i.value = (f.showIf && f.showIf[key] != null) ? f.showIf[key] : '';
        if (ph) { i.placeholder = ph; }
        i.addEventListener('input', function () {
            fields[selected].showIf = fields[selected].showIf || {};
            if (i.value === '') { delete fields[selected].showIf[key]; }
            else { fields[selected].showIf[key] = i.value; }
            commit();
        });
        w.appendChild(i);
        return w;
    }

    /* ---------- serialise ---------- */

    function clean(f) {
        const out = { type: f.type };
        if (f.key) { out.key = f.key; }
        if (f.label !== '') { out.label = f.label; }
        if (f.width && f.width !== 'full') { out.width = f.width; }
        if (f.required) { out.required = true; }
        if (f.placeholder !== '') { out.placeholder = f.placeholder; }
        if (isChoice(f.type) && f.options.length) { out.options = f.options; }
        if (f.validate) {
            const v = {};
            ['minLength', 'maxLength', 'pattern'].forEach(function (k) {
                if (f.validate[k] != null && f.validate[k] !== '') { v[k] = f.validate[k]; }
            });
            if (Object.keys(v).length) { out.validate = v; }
        }
        if (f.showIf && (f.showIf.field || f.showIf.eq)) {
            out.showIf = {};
            if (f.showIf.field) { out.showIf.field = f.showIf.field; }
            if (f.showIf.eq != null && f.showIf.eq !== '') { out.showIf.eq = f.showIf.eq; }
        }
        return out;
    }

    function serialize() {
        const schema = Object.assign({}, baseSchema, {
            version: baseSchema.version || 1,
            fields: fields.map(clean),
        });
        delete schema.steps; // builder MVP edits the flat field list
        if (schemaField) { schemaField.value = JSON.stringify(schema); }
        const pre = document.getElementById('cf-json-preview');
        if (pre) { pre.textContent = JSON.stringify(schema, null, 2); }
    }

    return { init: init };
})();
