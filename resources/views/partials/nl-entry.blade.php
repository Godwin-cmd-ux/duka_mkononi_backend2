{{--
    Capability #25 - Schema-Free Natural-Language Data Entry (website runtime).

    Script-only runtime, included from a page that renders a panel marked with
    `data-dm-entry` (see resources/views/muuzaji/huduma-nyingine.blade.php).
    Include it AFTER partials.dm-lang-widget, like the voice runtime.

    WHAT IT DOES
    ------------
    One box: the seller writes what happened ("Add 20 bottles of blue perfume to
    stock"), presses Prepare preview, and gets an EDITABLE preview of exactly one
    record - product, sale, expense or customer. Nothing is written until the
    seller confirms, and the confirmed record goes through the EXISTING endpoint
    for that type, so all the normal validation, stock rules and audit trail
    apply.

    WHAT IT DELIBERATELY DOES NOT DO
    --------------------------------
    * It never invents a product. A product reference that matches nothing, or
      matches more than one product, stays unresolved and is shown with its
      candidates - the seller chooses.
    * It never hides a problem. Missing required fields, ambiguity flags and a
      degraded catalogue read are all rendered from the API response.
    * It never says "saved" on its own: the confirmation comes from the existing
      workflow's own response.

    API exposed to the page (all safe to call):
      DM.entry.mount(root)             -> wire one panel element
      DM.entry.state()                 -> { phase, type, preview, missing, flags }
      DM.entry.preview(text)           -> Promise<response|null>
      DM.entry.commit()                -> Promise<response|null>
      DM.entry.reset()
--}}
@verbatim
<script>
(function (global) {
    'use strict';

    var PREVIEW_URL = '/api/ai/entry/preview';
    var COMMIT_URL = '/api/ai/entry/commit';
    var EXAMPLES_URL = '/api/ai/entry/examples';

    var MAX_TEXT = 500;

    /** Localized chrome: page catalogue first, then a plain fallback. */
    var FALLBACK = {
        title: 'Write it, we record it',
        subtitle: 'Describe one thing that happened and check the preview before saving.',
        placeholder: 'e.g. Add 20 bottles of blue perfume to stock',
        sign_in: 'Please sign in again.',
        check_missing: 'Fill in the fields marked as missing.',
        analyze: 'Prepare preview',
        preview_title: 'Check before saving',
        confirm: 'Confirm and save',
        cancel: 'Cancel',
        retry: 'Try again',
        saved: 'Saved through the normal workflow.',
        unsupported: 'I could not tell which record this is. Write it as a product, a sale, an expense or a customer.',
        type_product: 'Product',
        type_sale: 'Sale',
        type_expense: 'Expense',
        type_customer: 'Customer',
        matched: 'Found in your catalogue',
        ambiguous: 'More than one product fits - choose one',
        not_found: 'Not in your catalogue',
        new_record: 'Will be created as a new record',
        characters_left: '{count} characters left'
    };

    var UNITS = ['bottle', 'piece', 'kg', 'litre', 'box', 'packet', 'bag', 'pair', 'dozen', 'carton', 'roll', 'sheet', 'metre'];

    /* Field definitions per record type: [key, label, kind, options] */
    var FIELDS = {
        product: [
            ['name', 'name', 'text'],
            ['category', 'category', 'text'],
            ['unit', 'unit', 'select', UNITS],
            ['stock', 'stock', 'number'],
            ['price', 'price', 'number'],
            ['expected_selling_price', 'selling_price', 'number'],
            ['description', 'description', 'text']
        ],
        expense: [
            ['description', 'description', 'text'],
            ['amount', 'amount', 'number'],
            ['category', 'category', 'select', null],
            ['expense_date', 'date', 'date']
        ],
        customer: [
            ['name', 'name', 'text'],
            ['phone', 'phone', 'text'],
            ['email', 'email', 'text']
        ],
        sale: [
            ['quantity', 'quantity', 'number'],
            ['unit_price', 'unit_price', 'number'],
            ['customer_name', 'customer', 'text'],
            ['sale_date', 'date', 'date'],
            ['payment_method', 'payment', 'select', ['cash', 'mobile_money', 'bank', 'credit']]
        ]
    };

    var FLAG_LABEL = {
        type_uncertain: 'unsupported', name_missing: 'name', category_missing: 'category',
        stock_missing: 'stock', price_missing: 'price', selling_price_missing: 'selling',
        quantity_missing: 'quantity', product_ambiguous: 'ambiguous', product_not_found: 'not_found',
        product_new: 'new_record', customer_new: 'new_record', unit_uncertain: 'unit',
        amount_ambiguous: 'amount', price_assumed_unit: 'unit_price', amount_missing: 'amount'
    };

    var state = {
        root: null,
        phase: 'idle',
        response: null,
        type: null,
        preview: {},
        missing: [],
        flags: [],
        resolution: null,
        key: null,
        dirty: false,
        messages: {}
    };

    var EXAMPLES = null;

    function dm() { return global.DM || null; }

    function locale() {
        var api = dm();
        var value = (api && api.locale && api.locale()) || document.documentElement.getAttribute('data-dm-locale') || 'sw';
        return value;
    }

    function t(key, params) {
        var api = dm();
        var full = 'nl_entry.' + key;
        var text = api && api.t ? api.t(full, params) : null;
        if (!text || text === full) {
            text = state.messages[key] || FALLBACK[key] || key;
        }
        if (params) {
            Object.keys(params).forEach(function (name) {
                text = String(text).split('{' + name + '}').join(String(params[name]));
            });
        }
        return text;
    }

    function token() {
        try { return global.localStorage.getItem('userToken') || ''; } catch (e) { return ''; }
    }

    function escapeHtml(value) {
        if (value === null || value === undefined) { return ''; }
        return String(value).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token()
            },
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                return { ok: response.ok, status: response.status, data: data || {} };
            });
        });
    }

    function newKey() {
        if (global.crypto && global.crypto.randomUUID) { return global.crypto.randomUUID(); }
        return 'entry-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
    }

    /* ------------------------------------------------------------------ */
    /* Rendering                                                          */
    /* ------------------------------------------------------------------ */

    function q(selector) {
        return state.root ? state.root.querySelector(selector) : null;
    }

    function setBanner(message, kind) {
        var banner = q('[data-dm-entry-banner]');
        if (!banner) { return; }
        if (!message) {
            banner.textContent = '';
            banner.className = 'dm-entry-banner';
            banner.removeAttribute('data-kind');
            return;
        }
        banner.textContent = message;
        banner.className = 'dm-entry-banner is-visible';
        banner.setAttribute('data-kind', kind || 'info');
    }

    function renderExamples(container) {
        if (!container) { return; }
        var list = (EXAMPLES && EXAMPLES.examples) || null;
        if (!list) { return; }
        var order = ['product', 'sale', 'expense', 'customer'];
        var html = '';
        order.forEach(function (type) {
            (list[type] || []).slice(0, 1).forEach(function (sentence) {
                html += '<button type="button" class="dm-entry-chip" data-dm-entry-example="' + escapeHtml(sentence) + '">'
                    + '<span class="dm-entry-chip-type">' + escapeHtml(t('type_' + type)) + '</span>'
                    + escapeHtml(sentence) + '</button>';
            });
        });
        container.innerHTML = html;
    }

    function fieldInput(key, label, kind, options) {
        var value = state.preview ? state.preview[key] : null;
        var id = 'dmEntry-' + key.replace(/[^a-z0-9_]/gi, '');
        var html = '<label class="dm-entry-field" for="' + id + '">'
            + '<span class="dm-entry-field-label">' + escapeHtml(label) + '</span>';

        label = t(label);

        if (kind === 'select') {
            var opts = options || (state.response && state.response.categories) || [];
            html += '<select id="' + id + '" data-dm-entry-field="' + key + '">';
            html += '<option value="">—</option>';
            opts.forEach(function (option) {
                var selected = String(value || '') === String(option) ? ' selected' : '';
                html += '<option value="' + escapeHtml(option) + '"' + selected + '>' + escapeHtml(option) + '</option>';
            });
            html += '</select>';
        } else {
            var type = kind === 'number' ? 'number' : (kind === 'date' ? 'date' : 'text');
            var step = kind === 'number' ? ' step="any" min="0"' : '';
            var current = value === null || value === undefined ? '' : value;
            html += '<input id="' + id + '" data-dm-entry-field="' + key + '" type="' + type + '"' + step
                + ' value="' + escapeHtml(current) + '">';
        }

        return html + '</label>';
    }

    function renderPreview() {
        var host = q('[data-dm-entry-preview]');
        if (!host) { return; }

        var response = state.response;
        if (!response || !response.preview) {
            host.innerHTML = '';
            host.classList.remove('is-visible');
            return;
        }

        state.preview = response.preview;
        state.missing = response.missing_required || [];
        state.flags = response.flags || [];
        state.resolution = response.resolution || null;

        var html = '';
        html += '<div class="dm-entry-preview-head">'
            + '<span class="dm-entry-type">' + escapeHtml(response.type_label || t('type_' + response.type)) + '</span>'
            + '<span class="dm-entry-status" data-kind="' + escapeHtml(response.status || '') + '">'
            + (state.missing.length === 0 ? escapeHtml(t('preview_title')) : escapeHtml(state.missing.join(', ')))
            + '</span></div>';

        // Product resolution for a sale: an ambiguous reference must be chosen.
        var items = (state.resolution && state.resolution.items) || [];
        if (items.length) {
            var item = items[0] || {};
            var match = item.match || {};
            html += '<div class="dm-entry-match" data-status="' + escapeHtml(match.status || '') + '">';
            html += '<div class="dm-entry-match-line">'
                + '<strong>' + escapeHtml(item.reference || '') + '</strong> — '
                + escapeHtml(match.status === 'matched'
                    ? t('matched') + ': ' + (item.product_name || '')
                    : (match.status === 'ambiguous' ? t('ambiguous') : t('not_found')))
                + '</div>';
            (match.candidates || []).forEach(function (candidate) {
                html += '<button type="button" class="dm-entry-candidate" data-dm-entry-product="' + escapeHtml(candidate.product_id) + '" '
                    + 'data-dm-entry-product-name="' + escapeHtml(candidate.name || '') + '">'
                    + escapeHtml(candidate.name || candidate.product_id)
                    + (candidate.stock === null || candidate.stock === undefined ? '' : ' · ' + escapeHtml(candidate.stock))
                    + '</button>';
            });
            html += '</div>';
        }

        if (state.resolution && state.resolution.existing_product) {
            html += '<div class="dm-entry-banner is-visible" data-kind="info">'
                + escapeHtml(t('type_product')) + ': ' + escapeHtml(state.resolution.existing_product.name || '')
                + '</div>';
        }

        // A sale keeps its count and price on the item, so surface them as the
        // same flat fields the other types use.
        if (state.type === 'sale') {
            var saleItem = (state.preview.items || [])[0] || {};
            state.preview.quantity = saleItem.quantity;
            state.preview.unit_price = saleItem.unit_price;
        }

        // Editable fields for the record type.
        html += '<div class="dm-entry-fields">';
        var spec = FIELDS[state.type] || [];
        spec.forEach(function (definition) {
            html += fieldInput(definition[0], definition[1], definition[2], definition[3]);
        });
        html += '</div>';

        // Flags (each already carries a localized sentence from the API).
        if (state.flags.length) {
            html += '<ul class="dm-entry-flags">';
            state.flags.forEach(function (flag) {
                html += '<li data-kind="' + escapeHtml(flag.code) + '">' + escapeHtml(flag.message) + '</li>';
            });
            html += '</ul>';
        }            if (state.missing.length) {
                html += '<div class="dm-entry-missing"><strong>'
                    + escapeHtml(t('check_missing')) + ' — ' + escapeHtml(state.missing.join(', '))
                    + '</strong></div>';
            }

        if (state.resolution && state.resolution.catalogue && state.resolution.catalogue.degraded) {
            html += '<div class="dm-entry-banner is-visible" data-kind="warn">'
                + escapeHtml(state.resolution.catalogue.degraded_reason || '') + '</div>';
        }

        html += '<div class="dm-entry-actions">'
            + '<button type="button" class="dm-entry-btn dm-entry-btn-primary" data-dm-entry-confirm'
            + (state.missing.length ? ' disabled' : '') + '>' + escapeHtml(t('confirm')) + '</button>'
            + '<button type="button" class="dm-entry-btn" data-dm-entry-cancel>' + escapeHtml(t('cancel')) + '</button>'
            + '</div>';

        host.innerHTML = html;
        host.classList.add('is-visible');
    }

    function readFields() {
        var values = {};
        if (!state.root) { return values; }

        state.root.querySelectorAll('[data-dm-entry-field]').forEach(function (input) {
            var key = input.getAttribute('data-dm-entry-field');
            var raw = input.value;
            if (input.type === 'number') {
                values[key] = raw === '' ? null : Number(raw);
            } else {
                values[key] = raw;
            }
        });

        var chosen = state.root.querySelector('[data-dm-entry-product].is-selected');
        if (chosen) {
            values.product_id = chosen.getAttribute('data-dm-entry-product');
            values.product_name = chosen.getAttribute('data-dm-entry-product-name') || '';
        }

        return values;
    }

    function currentPreviewPayload() {
        var values = readFields();
        var preview = JSON.parse(JSON.stringify(state.preview || {}));

        if (state.type === 'sale') {
            var item = (preview.items || [])[0] || {};
            if ('quantity' in values) { item.quantity = values.quantity; }
            if ('unit_price' in values) { item.unit_price = values.unit_price; }
            if (values.product_id) { item.product_id = values.product_id; }
            preview.items = [item];
            ['customer_name', 'sale_date', 'payment_method'].forEach(function (key) {
                if (key in values) { preview[key] = values[key]; }
            });
        } else {
            Object.keys(values).forEach(function (key) { preview[key] = values[key]; });
        }

        return preview;
    }

    /* ------------------------------------------------------------------ */
    /* Actions                                                            */
    /* ------------------------------------------------------------------ */

    function preview(text) {
        var box = q('[data-dm-entry-text]');
        var value = typeof text === 'string' ? text : (box ? box.value : '');
        value = (value || '').trim();

        if (value === '') {
            setBanner(t('placeholder'), 'warn');
            return Promise.resolve(null);
        }

        setBanner('', '');
        state.phase = 'loading';

        return post(PREVIEW_URL, { text: value, locale: locale() }).then(function (result) {
            var data = result.data;
            state.phase = 'ready';

            if (!result.ok) {
                setBanner(data.error || t('unsupported'), 'error');
                return data;
            }

            if (data.status === 'unsupported' || !data.preview) {
                setBanner(data.explanation || t('unsupported'), 'warn');
                state.response = null;
                state.type = null;
                renderPreview();
                return data;
            }

            state.response = data;
            state.type = data.type;
            state.key = newKey();
            setBanner('', '');
            renderPreview();
            return data;
        }).catch(function () {
            state.phase = 'error';
            setBanner(t('retry'), 'error');
            return null;
        });
    }

    function commit() {
        if (!state.response || !state.type) { return Promise.resolve(null); }
        if (state.missing.length) {
            setBanner(state.missing.join(', '), 'warn');
            return Promise.resolve(null);
        }

        state.phase = 'committing';
        var button = q('[data-dm-entry-confirm]');
        if (button) { button.disabled = true; }

        return post(COMMIT_URL, {
            confirm: true,
            type: state.type,
            idempotency_key: state.key || newKey(),
            locale: locale(),
            preview: currentPreviewPayload()
        }).then(function (result) {
            var data = result.data;
            if (result.ok && data.success) {
                state.phase = 'saved';
                setBanner(data.message || t('saved'), 'success');
                var host = q('[data-dm-entry-preview]');
                if (host) { host.innerHTML = ''; host.classList.remove('is-visible'); }
                state.response = null;
                state.type = null;
            } else {
                state.phase = 'ready';
                setBanner(data.message || data.error || t('retry'), 'error');
                if (button) { button.disabled = false; }
            }
            return data;
        }).catch(function () {
            state.phase = 'ready';
            setBanner(t('retry'), 'error');
            if (button) { button.disabled = false; }
            return null;
        });
    }

    function reset() {
        state.phase = 'idle';
        state.response = null;
        state.type = null;
        state.preview = {};
        state.missing = [];
        state.flags = [];
        state.key = null;
        setBanner('', '');
        var host = q('[data-dm-entry-preview]');
        if (host) { host.innerHTML = ''; host.classList.remove('is-visible'); }
    }

    /* ------------------------------------------------------------------ */
    /* Wiring                                                             */
    /* ------------------------------------------------------------------ */

    function mount(root) {
        if (!root || root.getAttribute('data-dm-entry-bound') === '1') { return root; }
        root.setAttribute('data-dm-entry-bound', '1');
        state.root = root;

        var box = q('[data-dm-entry-text]');
        var counter = q('[data-dm-entry-count]');

        function updateCounter() {
            if (!counter || !box) { return; }
            var left = Math.max(0, MAX_TEXT - box.value.length);
            counter.textContent = t('characters_left', { count: left });
        }

        if (box) {
            box.setAttribute('maxlength', String(MAX_TEXT));
            box.setAttribute('placeholder', t('placeholder'));
            box.addEventListener('input', updateCounter);
            updateCounter();
        }

        root.addEventListener('click', function (event) {
            var target = event.target;
            if (!target || !target.getAttribute) { return; }

            if (target.getAttribute('data-dm-entry-analyze') !== null) {
                preview();
                return;
            }
            if (target.getAttribute('data-dm-entry-confirm') !== null) {
                commit();
                return;
            }
            if (target.getAttribute('data-dm-entry-cancel') !== null) {
                reset();
                return;
            }
            var example = target.getAttribute('data-dm-entry-example');
            if (example) {
                if (box) { box.value = example; }
                updateCounter();
                preview(example);
                return;
            }
            var product = target.getAttribute('data-dm-entry-product');
            if (product) {
                root.querySelectorAll('[data-dm-entry-product]').forEach(function (button) {
                    button.classList.remove('is-selected');
                });
                target.classList.add('is-selected');
                if (memberOfMissing('items[0].product_id')) {
                    state.missing = state.missing.filter(function (field) { return field !== 'items[0].product_id'; });
                    var confirmButton = root.querySelector('[data-dm-entry-confirm]');
                    if (confirmButton) { confirmButton.disabled = state.missing.length > 0; }
                }
            }
        });

        function memberOfMissing(field) {
            return state.missing.indexOf(field) !== -1;
        }

        root.addEventListener('change', function (event) {
            var target = event.target;
            if (target && target.getAttribute && target.getAttribute('data-dm-entry-field')) {
                state.dirty = true;
            }
        });

        renderExamples(q('[data-dm-entry-examples]'));

        return root;
    }

    function loadExamples() {
        fetch(EXAMPLES_URL + '?locale=' + encodeURIComponent(locale()), { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data) { return; }
                EXAMPLES = data;
                state.messages = data.messages || {};
                state.root = state.root || document.querySelector('[data-dm-entry]');
                renderExamples(q('[data-dm-entry-examples]'));
            })
            .catch(function () { /* the panel still works without the examples */ });
    }

    var api = {
        mount: mount,
        preview: preview,
        commit: commit,
        reset: reset,
        state: function () {
            return {
                phase: state.phase,
                type: state.type,
                preview: state.preview,
                missing: state.missing,
                flags: state.flags
            };
        }
    };

    global.DM = global.DM || {};
    global.DM.entry = api;

    function boot() {
        var root = document.querySelector('[data-dm-entry]');
        if (!root) { return; }
        mount(root);
        loadExamples();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window);
</script>
@endverbatim
