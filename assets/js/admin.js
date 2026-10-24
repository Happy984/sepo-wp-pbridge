/* Sepo WordPress Bridge — admin panel. */
(function () {
  'use strict';
  var root = document.querySelector('.wb-app');
  if (!root || !window.SepoWB) return;

  var cfg = window.SepoWB;
  var i18n = (window.wp && window.wp.i18n) || { __: function (s) { return s; }, sprintf: function (f) { var a = [].slice.call(arguments, 1), i = 0; return f.replace(/%(\d\$)?[sd]/g, function () { return a[i++]; }); } };
  var __ = function (s) { return i18n.__(s, 'sepo-wp-bridge'); };
  var sprintf = i18n.sprintf;

  // ------------------------------------------------------------------ utils
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function req(method, path, payload) {
    var opts = { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-WP-Nonce': cfg.nonce } };
    if (payload !== undefined) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(payload); }
    return fetch(cfg.api + path, opts).then(function (r) {
      return r.text().then(function (t) {
        var b = null;
        try { b = JSON.parse(t); } catch (e) { throw new Error(sprintf(__('Unexpected response from the server (%s).'), r.status)); }
        if (!r.ok) throw new Error((b && b.message) || sprintf(__('Request failed (%s).'), r.status));
        return (b && b.data) || {};
      });
    });
  }
  function get(path) { return req('GET', path); }
  function post(path, p) { return req('POST', path, p || {}); }

  var toastTimer = null;
  function toast(msg, isError) {
    var el = root.querySelector('[data-wb-toast]');
    el.textContent = msg;
    el.classList.toggle('is-error', !!isError);
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.hidden = true; }, isError ? 6000 : 2600);
  }

  function panel(slug) { return root.querySelector('[data-wb-panel="' + slug + '"]'); }

  function setState(slug, state) {
    var p = panel(slug);
    p.querySelector('[data-wb-loading]').hidden = state !== 'loading';
    var empty = p.querySelector('[data-wb-empty]');
    if (empty) empty.hidden = state !== 'empty';
  }

  function checks(name, options, selected, showCode) {
    selected = selected || [];
    return '<div class="wb-checks">' + options.map(function (o) {
      return '<label><input type="checkbox" data-f="' + esc(name) + '" value="' + esc(o.value) + '"'
        + (selected.indexOf(o.value) !== -1 ? ' checked' : '') + '> ' + esc(o.label)
        + (showCode && o.label !== o.value ? ' <code>' + esc(o.value) + '</code>' : '') + '</label>';
    }).join('') + '</div>';
  }

  function checked(card, name) {
    return [].slice.call(card.querySelectorAll('[data-f="' + name + '"]:checked')).map(function (i) { return i.value; });
  }

  // ------------------------------------------------------------------ modal
  function modal(opts) {
    var last = document.activeElement;
    var overlay = document.createElement('div');
    overlay.className = 'wb-modal';
    overlay.innerHTML = '<div class="wb-modal-card' + (opts.small ? ' wb-modal-card--sm' : '') + '" role="dialog" aria-modal="true" aria-labelledby="wb-modal-title">'
      + '<h3 id="wb-modal-title">' + esc(opts.title) + '</h3>'
      + '<div data-body>' + opts.body + '</div>'
      + '<div class="wb-modal-err" data-err role="alert"></div>'
      + '<div class="wb-modal-actions"><button type="button" class="wb-btn" data-cancel>' + esc(__('Cancel')) + '</button>'
      + '<button type="button" class="wb-btn ' + (opts.danger ? 'wb-btn--danger' : 'wb-btn--primary') + '" data-ok>' + esc(opts.okLabel || __('Save')) + '</button></div>'
      + '</div>';
    document.body.appendChild(overlay);
    var card = overlay.firstChild;
    var ok = card.querySelector('[data-ok]');
    function close() { overlay.remove(); document.removeEventListener('keydown', onKey); if (last && last.focus) last.focus(); }
    function onKey(e) { if (e.key === 'Escape') close(); }
    document.addEventListener('keydown', onKey);
    card.querySelector('[data-cancel]').addEventListener('click', close);
    overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) close(); });
    ok.addEventListener('click', function () {
      var err = card.querySelector('[data-err]');
      err.textContent = '';
      ok.disabled = true;
      Promise.resolve().then(function () { return opts.onOk(card); })
        .then(function (keepOpen) { if (!keepOpen) close(); })
        .catch(function (e) { err.textContent = e.message; ok.disabled = false; });
    });
    if (opts.onOpen) opts.onOpen(card);
    var first = card.querySelector('input:not([readonly]):not([type=checkbox]), select, textarea') || ok;
    first.focus();
    return card;
  }

  function confirmModal(title, message, okLabel, extraLabel) {
    return new Promise(function (resolve) {
      var answered = false;
      var card = modal({
        title: title, small: true, danger: true, okLabel: okLabel || __('Delete'),
        body: '<p>' + esc(message) + '</p>' + (extraLabel ? '<label class="wb-field wb-field--check"><input type="checkbox" data-extra> <span>' + esc(extraLabel) + '</span></label>' : ''),
        onOk: function (c) { answered = true; resolve({ ok: true, extra: !!(c.querySelector('[data-extra]') || {}).checked }); }
      });
      var obs = new MutationObserver(function () { if (!document.body.contains(card)) { obs.disconnect(); if (!answered) resolve({ ok: false }); } });
      obs.observe(document.body, { childList: true });
    });
  }

  // ------------------------------------------------------------------ tabs
  var loaders = {};
  var loaded = {};

  function showTab(slug, focus) {
    root.querySelectorAll('[data-wb-tab]').forEach(function (b) {
      var on = b.getAttribute('data-wb-tab') === slug;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
      b.tabIndex = on ? 0 : -1;
      if (on && focus) b.focus();
    });
    root.querySelectorAll('[data-wb-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-wb-panel') !== slug; });
    try {
      var u = new URL(location.href);
      u.searchParams.set('tab', slug);
      history.replaceState(null, '', u.toString());
    } catch (e) { /* ignore */ }
    if (!loaded[slug] && loaders[slug]) loaders[slug]();
  }

  root.addEventListener('click', function (ev) {
    var tab = ev.target.closest('[data-wb-tab]');
    if (tab) showTab(tab.getAttribute('data-wb-tab'));
  });
  root.querySelector('.wb-tabs').addEventListener('keydown', function (ev) {
    var tabs = [].slice.call(root.querySelectorAll('[data-wb-tab]'));
    var i = tabs.indexOf(document.activeElement);
    if (i === -1) return;
    var n = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[ev.key];
    if (n === undefined) return;
    ev.preventDefault();
    showTab(tabs[(n + tabs.length) % tabs.length].getAttribute('data-wb-tab'), true);
  });

  // ------------------------------------------------------------------ filter
  root.addEventListener('input', function (ev) {
    var input = ev.target.closest('[data-wb-filter]');
    if (!input) return;
    var p = input.closest('[data-wb-panel]');
    applyFilter(p, input.value);
  });
  function applyFilter(p, value) {
    var needle = String(value || '').trim().toLowerCase();
    p.querySelectorAll('[data-search]').forEach(function (row) {
      row.hidden = needle !== '' && (row.getAttribute('data-search') || '').indexOf(needle) === -1;
    });
    p.querySelectorAll('[data-wb-group]').forEach(function (g) {
      g.hidden = !g.querySelector('[data-search]:not([hidden])');
    });
  }
  function refilter(slug) {
    var p = panel(slug);
    var f = p.querySelector('[data-wb-filter]');
    if (f && f.value) applyFilter(p, f.value);
  }

  // ================================================================ FEATURES
  var F = { rows: [], handlers: {}, categories: [] };
  var catLabels = {
    security: __('Security'), performance: __('Performance'), admin: __('Admin'), branding: __('Branding'), media: __('Media'), other: __('Other')
  };

  loaders.features = function () {
    setState('features', 'loading');
    get('/features').then(function (d) {
      loaded.features = true;
      F = { rows: d.features || [], handlers: d.handlers || {}, categories: d.categories || [] };
      renderFeatures();
    }).catch(function (e) { toast(e.message, true); });
  };

  function renderFeatures() {
    var p = panel('features');
    var list = p.querySelector('[data-wb-list]');
    setState('features', F.rows.length ? 'ready' : 'empty');
    var html = '';
    F.categories.forEach(function (cat) {
      var rows = F.rows.filter(function (r) { return r.category === cat; });
      if (!rows.length) return;
      var on = rows.filter(function (r) { return r.enabled; }).length;
      html += '<section class="wb-group" data-wb-group><div class="wb-group-h"><h2>' + esc(catLabels[cat] || cat) + '</h2>'
        + '<span>' + esc(sprintf(__('%1$d of %2$d on'), on, rows.length)) + '</span></div>';
      rows.forEach(function (f) {
        var params = Object.keys(f.params || {}).map(function (k) {
          var v = f.params[k];
          return esc(k) + ': ' + esc(Array.isArray(v) ? v.join(', ') : String(v));
        }).join(' · ');
        html += '<div class="wb-feature" data-search="' + esc((f.key + ' ' + f.label + ' ' + f.description + ' ' + f.handler + ' ' + f.category).toLowerCase()) + '" data-key="' + esc(f.key) + '">'
          + '<label class="wb-switch" title="' + esc(f.enabled ? __('On') : __('Off')) + '"><input type="checkbox" data-wb-feature-toggle aria-label="' + esc(f.label) + '"' + (f.enabled ? ' checked' : '') + (f.missing ? ' disabled' : '') + '><span class="wb-slider"></span></label>'
          + '<div class="wb-feature-main"><div class="wb-feature-name">' + esc(f.label) + '</div>'
          + (f.description ? '<p class="wb-feature-desc">' + esc(f.description) + '</p>' : '')
          + '<div class="wb-feature-meta"><code>' + esc(f.handler) + '</code>'
          + (f.default ? '<span class="wb-tag">' + esc(__('on by default')) + '</span>' : '')
          + (f.builtin ? '' : '<span class="wb-tag">' + esc(__('custom')) + '</span>')
          + (f.missing ? '<span class="wb-tag wb-tag--warn">' + esc(__('handler missing')) + '</span>' : '')
          + (params ? '<span>' + params + '</span>' : '')
          + '</div></div>'
          + '<div class="wb-feature-actions"><button type="button" class="wb-btn wb-btn--sm" data-wb-feature-edit>' + esc(Object.keys(f.params || {}).length ? __('Settings') : __('Edit')) + '</button>'
          + '<button type="button" class="wb-btn wb-btn--sm wb-btn--danger" data-wb-feature-delete aria-label="' + esc(sprintf(__('Delete %s'), f.label)) + '">' + esc(__('Delete')) + '</button></div>'
          + '</div>';
      });
      html += '</section>';
    });
    list.innerHTML = html;
    refilter('features');
  }

  function featureByKey(k) { return F.rows.filter(function (r) { return r.key === k; })[0]; }

  panel('features').addEventListener('change', function (ev) {
    var t = ev.target.closest('[data-wb-feature-toggle]');
    if (!t) return;
    var key = t.closest('[data-key]').getAttribute('data-key');
    var f = featureByKey(key);
    t.disabled = true;
    post('/features/toggle', { key: key, enabled: t.checked }).then(function (d) {
      f.enabled = !!d.enabled;
      toast(sprintf(f.enabled ? __('%s is on.') : __('%s is off.'), f.label));
      renderFeatures();
    }).catch(function (e) { toast(e.message, true); t.checked = !t.checked; t.disabled = false; });
  });

  panel('features').addEventListener('click', function (ev) {
    if (ev.target.closest('[data-wb-feature-add]')) return featureEditor(null);
    if (ev.target.closest('[data-wb-feature-restore]')) {
      return confirmModal(__('Restore default features'), __('Shipped features you deleted or edited are put back as they came. Your custom features and on/off choices are kept.'), __('Restore')).then(function (r) {
        if (!r.ok) return;
        post('/features/restore').then(function () { toast(__('Defaults restored.')); loaders.features(); }).catch(function (e) { toast(e.message, true); });
      });
    }
    var row = ev.target.closest('[data-key]');
    if (!row) return;
    var f = featureByKey(row.getAttribute('data-key'));
    if (ev.target.closest('[data-wb-feature-edit]')) return featureEditor(f);
    if (ev.target.closest('[data-wb-feature-delete]')) {
      confirmModal(__('Delete feature'), sprintf(__('Delete "%s"? It stops running straight away.'), f.label), __('Delete')).then(function (r) {
        if (!r.ok) return;
        post('/features/delete', { key: f.key }).then(function () { toast(__('Feature deleted.')); loaders.features(); }).catch(function (e) { toast(e.message, true); });
      });
    }
  });

  var paramLabels = {
    heading: __('Title above the login form'), footer: __('Footer line'), logo: __('Logo image address'),
    color: __('Main colour'), colorDark: __('Hover colour'), max: __('Attempts allowed'), lock: __('Lock-out minutes'),
    minLength: __('Minimum length'), window: __('Per how many seconds'), roles: __('Roles'), allow: __('Namespaces left open'), quality: __('Quality (1–100)')
  };
  var paramHints = {
    heading: __('Empty: "Welcome back".'), footer: __('{year} and {site} are filled in. Empty: © {year} {site}.'),
    logo: __('Empty: your Site Icon, else the theme logo, else the WordPress logo.')
  };

  function paramsHtml(handler, values) {
    var params = F.handlers[handler] || [];
    if (!params.length) return '';
    return '<div class="wb-params"><p class="wb-params-h">' + esc(__('Settings')) + '</p>' + params.map(function (p) {
      var v = values && Object.prototype.hasOwnProperty.call(values, p.name) ? values[p.name] : p.default;
      var label = p.name.replace(/([A-Z])/g, ' $1').replace(/^./, function (c) { return c.toUpperCase(); });
      if (p.type === 'bool') {
        return '<label class="wb-field wb-field--check"><input type="checkbox" data-p="' + esc(p.name) + '" data-type="bool"' + (v ? ' checked' : '') + '> <span>' + esc(label) + '</span></label>';
      }
      if (p.type === 'array') {
        return '<label class="wb-field">' + esc(label) + '<input type="text" data-p="' + esc(p.name) + '" data-type="array" value="' + esc((v || []).join(', ')) + '"><small>' + esc(__('Separate with commas.')) + '</small></label>';
      }
      var isColor = /^colou?r/i.test(p.name);
      var inputType = p.type === 'int' ? 'number' : isColor ? 'color' : /logo|url/i.test(p.name) ? 'url' : 'text';
      var hint = paramHints[p.name] ? '<small>' + esc(paramHints[p.name]) + '</small>' : '';
      return '<label class="wb-field">' + esc(paramLabels[p.name] || label) + '<input type="' + inputType + '"' + (p.type === 'int' ? ' min="0"' : '') + ' data-p="' + esc(p.name) + '" data-type="' + esc(p.type) + '" value="' + esc(v == null ? '' : v) + '"' + (isColor ? ' style="height:38px;padding:3px"' : '') + '>' + hint + '</label>';
    }).join('') + '</div>';
  }

  function featureEditor(initial) {
    var f = initial || { key: '', label: '', category: 'other', handler: '', description: '', default: false, params: {} };
    var handlers = Object.keys(F.handlers);
    modal({
      title: initial ? sprintf(__('Edit "%s"'), f.label) : __('New feature'),
      body: '<div class="wb-grid2">'
        + '<label class="wb-field">' + esc(__('Label')) + '<input data-f="label" type="text" value="' + esc(f.label) + '"></label>'
        + '<label class="wb-field">' + esc(__('Key')) + '<input data-f="key" type="text" value="' + esc(f.key) + '"' + (initial ? ' readonly' : '') + ' placeholder="my_feature"></label>'
        + '<label class="wb-field">' + esc(__('Category')) + '<select data-f="category">' + F.categories.map(function (c) {
          return '<option value="' + esc(c) + '"' + (c === f.category ? ' selected' : '') + '>' + esc(catLabels[c] || c) + '</option>';
        }).join('') + '</select></label>'
        + '<label class="wb-field">' + esc(__('Handler')) + '<select data-f="handler"><option value="">—</option>' + handlers.map(function (h) {
          return '<option value="' + esc(h) + '"' + (h === f.handler ? ' selected' : '') + '>' + esc(h) + '</option>';
        }).join('') + '</select></label></div>'
        + '<label class="wb-field">' + esc(__('Description')) + '<textarea data-f="description" rows="2">' + esc(f.description) + '</textarea></label>'
        + '<div data-params>' + paramsHtml(f.handler, f.params) + '</div>'
        + '<label class="wb-field wb-field--check"><input data-f="default" type="checkbox"' + (f.default ? ' checked' : '') + '> <span>' + esc(__('On by default (when you have not switched it yourself)')) + '</span></label>',
      onOpen: function (card) {
        var label = card.querySelector('[data-f="label"]'), key = card.querySelector('[data-f="key"]');
        if (!initial) label.addEventListener('input', function () {
          if (!key.dataset.touched) key.value = label.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        });
        key.addEventListener('input', function () { key.dataset.touched = '1'; });
        card.querySelector('[data-f="handler"]').addEventListener('change', function (e) {
          card.querySelector('[data-params]').innerHTML = paramsHtml(e.target.value, {});
        });
      },
      onOk: function (card) {
        function v(n) { var el = card.querySelector('[data-f="' + n + '"]'); return el.type === 'checkbox' ? el.checked : el.value.trim(); }
        var params = {};
        card.querySelectorAll('[data-p]').forEach(function (el) {
          var t = el.getAttribute('data-type');
          params[el.getAttribute('data-p')] = t === 'bool' ? el.checked : t === 'int' ? parseInt(el.value || '0', 10) : t === 'array' ? el.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean) : el.value;
        });
        return post('/features/save', {
          key: v('key'), label: v('label'), category: v('category'), handler: v('handler'),
          description: v('description'), default: v('default'), params: params
        }).then(function () { toast(__('Feature saved.')); loaders.features(); });
      }
    });
  }

  // ================================================================ POST TYPES
  var CPT = { rows: [], supports: [] };
  var supportLabels = {
    'title': __('Title'), 'editor': __('Editor'), 'author': __('Author'), 'thumbnail': __('Featured image'),
    'excerpt': __('Excerpt'), 'comments': __('Comments'), 'revisions': __('Revisions'), 'page-attributes': __('Order / parent'),
    'custom-fields': __('Custom fields'), 'post-formats': __('Post formats'), 'trackbacks': __('Trackbacks')
  };

  loaders.cpts = function () {
    setState('cpts', 'loading');
    get('/cpts').then(function (d) {
      loaded.cpts = true;
      CPT = { rows: d.cpts || [], supports: d.supports || [] };
      var list = panel('cpts').querySelector('[data-wb-list]');
      setState('cpts', CPT.rows.length ? 'ready' : 'empty');
      if (!CPT.rows.length) { list.innerHTML = ''; return; }
      list.innerHTML = '<table class="wb-table"><thead><tr><th>' + esc(__('Post type')) + '</th><th>' + esc(__('Public')) + '</th><th>' + esc(__('Archive')) + '</th><th>' + esc(__('Supports')) + '</th><th>' + esc(__('Posts')) + '</th><th></th></tr></thead><tbody>'
        + CPT.rows.map(function (c) {
          return '<tr data-search="' + esc((c.ID + ' ' + c.singular_name + ' ' + c.plural_name).toLowerCase()) + '" data-key="' + esc(c.ID) + '">'
            + '<td data-label="' + esc(__('Post type')) + '"><span class="dashicons ' + esc(/^dashicons-/.test(c.menu_icon) ? c.menu_icon : 'dashicons-admin-post') + '" aria-hidden="true"></span> <strong>' + esc(c.plural_name) + '</strong><span class="wb-sub-line"><code>' + esc(c.ID) + '</code> · ' + esc(c.singular_name) + '</span></td>'
            + '<td data-label="' + esc(__('Public')) + '">' + (c.public ? esc(__('Yes')) : esc(__('No'))) + '</td>'
            + '<td data-label="' + esc(__('Archive')) + '">' + (c.has_archive ? esc(__('Yes')) : esc(__('No'))) + '</td>'
            + '<td data-label="' + esc(__('Supports')) + '"><small>' + esc((c.supports || []).map(function (s) { return supportLabels[s] || s; }).join(', ')) + '</small></td>'
            + '<td data-label="' + esc(__('Posts')) + '"><a href="' + esc(cfg.adminUrl + 'edit.php?post_type=' + encodeURIComponent(c.ID)) + '">' + esc(c.count) + '</a></td>'
            + '<td class="wb-actions-cell"><button type="button" class="wb-btn wb-btn--sm" data-edit>' + esc(__('Edit')) + '</button> <button type="button" class="wb-btn wb-btn--sm wb-btn--danger" data-delete>' + esc(__('Delete')) + '</button></td></tr>';
        }).join('') + '</tbody></table>';
      refilter('cpts');
    }).catch(function (e) { toast(e.message, true); });
  };

  panel('cpts').addEventListener('click', function (ev) {
    if (ev.target.closest('[data-wb-add]')) return cptEditor(null);
    var row = ev.target.closest('[data-key]');
    if (!row) return;
    var c = CPT.rows.filter(function (r) { return r.ID === row.getAttribute('data-key'); })[0];
    if (ev.target.closest('[data-edit]')) return cptEditor(c);
    if (ev.target.closest('[data-delete]')) {
      var msg = c.count > 0
        ? sprintf(__('"%1$s" has %2$d posts. They stay in the database but disappear from wp-admin until the post type is added again with the same slug.'), c.plural_name, c.count)
        : sprintf(__('Remove the post type "%s"?'), c.plural_name);
      confirmModal(__('Delete post type'), msg, __('Delete')).then(function (r) {
        if (!r.ok) return;
        post('/cpts/delete', { post_type: c.ID }).then(function () { toast(__('Post type removed. Reload wp-admin to update the menu.')); loaders.cpts(); }).catch(function (e) { toast(e.message, true); });
      });
    }
  });

  function cptEditor(initial) {
    var c = initial || { ID: '', singular_name: '', plural_name: '', public: true, has_archive: true, hierarchical: false, supports: ['title', 'editor', 'thumbnail'], menu_icon: 'dashicons-admin-post', rewrite_slug: '' };
    modal({
      title: initial ? sprintf(__('Edit "%s"'), c.plural_name) : __('New post type'),
      body: '<div class="wb-grid2">'
        + '<label class="wb-field">' + esc(__('Singular name')) + '<input data-f="singular_name" type="text" value="' + esc(c.singular_name) + '" placeholder="' + esc(__('Event')) + '"></label>'
        + '<label class="wb-field">' + esc(__('Plural name')) + '<input data-f="plural_name" type="text" value="' + esc(c.plural_name) + '" placeholder="' + esc(__('Events')) + '"></label>'
        + '<label class="wb-field">' + esc(__('Slug')) + '<input data-f="post_type" type="text" maxlength="20" value="' + esc(c.ID) + '"' + (initial ? ' readonly' : '') + ' placeholder="event"><small>' + esc(initial ? __('The slug cannot change: posts are stored under it.') : __('Up to 20 characters: a–z, 0–9, - and _.')) + '</small></label>'
        + '<label class="wb-field">' + esc(__('Address')) + '<input data-f="rewrite_slug" type="text" value="' + esc(c.rewrite_slug || '') + '" placeholder="' + esc(c.ID || 'events') + '"><small>' + esc(__('Optional. The part of the URL before each post’s name.')) + '</small></label>'
        + '<label class="wb-field">' + esc(__('Menu icon')) + '<input data-f="menu_icon" type="text" value="' + esc(c.menu_icon) + '"><small>' + esc(__('A Dashicons class such as dashicons-calendar-alt.')) + '</small></label>'
        + '</div>'
        + '<fieldset class="wb-field"><legend>' + esc(__('Editing screen shows')) + '</legend>' + checks('supports', CPT.supports.map(function (s) { return { value: s, label: supportLabels[s] || s }; }), c.supports) + '</fieldset>'
        + '<div class="wb-checks">'
        + '<label><input data-f="public" type="checkbox"' + (c.public ? ' checked' : '') + '> ' + esc(__('Public (has pages on the site)')) + '</label>'
        + '<label><input data-f="has_archive" type="checkbox"' + (c.has_archive ? ' checked' : '') + '> ' + esc(__('Archive page listing them')) + '</label>'
        + '<label><input data-f="hierarchical" type="checkbox"' + (c.hierarchical ? ' checked' : '') + '> ' + esc(__('Can have parents, like pages')) + '</label>'
        + '</div>',
      onOpen: function (card) {
        var one = card.querySelector('[data-f="singular_name"]'), many = card.querySelector('[data-f="plural_name"]'), slug = card.querySelector('[data-f="post_type"]');
        if (initial) return;
        one.addEventListener('input', function () {
          if (!many.dataset.touched) many.value = one.value ? one.value + 's' : '';
          if (!slug.dataset.touched) slug.value = one.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 20);
        });
        many.addEventListener('input', function () { many.dataset.touched = '1'; });
        slug.addEventListener('input', function () { slug.dataset.touched = '1'; });
      },
      onOk: function (card) {
        function v(n) { var el = card.querySelector('[data-f="' + n + '"]'); return el.type === 'checkbox' ? el.checked : el.value.trim(); }
        return post('/cpts/save', {
          post_type: v('post_type'), singular_name: v('singular_name'), plural_name: v('plural_name'),
          rewrite_slug: v('rewrite_slug'), menu_icon: v('menu_icon'), supports: checked(card, 'supports'),
          public: v('public'), has_archive: v('has_archive'), hierarchical: v('hierarchical')
        }).then(function () { toast(__('Post type saved. Reload wp-admin to see it in the menu.')); loaders.cpts(); loaded.taxonomies = loaded.metagroups = loaded.membership = false; });
      }
    });
  }

  // ================================================================ TAXONOMIES
  var TAX = { rows: [], postTypes: [] };

  function ptLabel(list, slug) {
    var m = list.filter(function (p) { return p.value === slug; })[0];
    return m ? m.label : slug;
  }

  loaders.taxonomies = function () {
    setState('taxonomies', 'loading');
    get('/taxonomies').then(function (d) {
      loaded.taxonomies = true;
      TAX = { rows: d.taxonomies || [], postTypes: d.post_types || [] };
      var list = panel('taxonomies').querySelector('[data-wb-list]');
      setState('taxonomies', TAX.rows.length ? 'ready' : 'empty');
      if (!TAX.rows.length) { list.innerHTML = ''; return; }
      list.innerHTML = '<table class="wb-table"><thead><tr><th>' + esc(__('Taxonomy')) + '</th><th>' + esc(__('Kind')) + '</th><th>' + esc(__('Used on')) + '</th><th>' + esc(__('Terms')) + '</th><th></th></tr></thead><tbody>'
        + TAX.rows.map(function (t) {
          return '<tr data-search="' + esc((t.ID + ' ' + t.singular_name + ' ' + t.plural_name + ' ' + (t.assign_to || []).join(' ')).toLowerCase()) + '" data-key="' + esc(t.ID) + '">'
            + '<td data-label="' + esc(__('Taxonomy')) + '"><strong>' + esc(t.plural_name) + '</strong><span class="wb-sub-line"><code>' + esc(t.ID) + '</code> · ' + esc(t.singular_name) + '</span></td>'
            + '<td data-label="' + esc(__('Kind')) + '">' + esc(t.hierarchical ? __('Categories (nested)') : __('Tags (flat)')) + '</td>'
            + '<td data-label="' + esc(__('Used on')) + '"><small>' + esc((t.assign_to || []).map(function (s) { return ptLabel(TAX.postTypes, s); }).join(', ')) + '</small></td>'
            + '<td data-label="' + esc(__('Terms')) + '"><a href="' + esc(cfg.adminUrl + 'edit-tags.php?taxonomy=' + encodeURIComponent(t.ID)) + '">' + esc(t.count) + '</a></td>'
            + '<td class="wb-actions-cell"><button type="button" class="wb-btn wb-btn--sm" data-edit>' + esc(__('Edit')) + '</button> <button type="button" class="wb-btn wb-btn--sm wb-btn--danger" data-delete>' + esc(__('Delete')) + '</button></td></tr>';
        }).join('') + '</tbody></table>';
      refilter('taxonomies');
    }).catch(function (e) { toast(e.message, true); });
  };

  panel('taxonomies').addEventListener('click', function (ev) {
    if (ev.target.closest('[data-wb-add]')) return taxEditor(null);
    var row = ev.target.closest('[data-key]');
    if (!row) return;
    var t = TAX.rows.filter(function (r) { return r.ID === row.getAttribute('data-key'); })[0];
    if (ev.target.closest('[data-edit]')) return taxEditor(t);
    if (ev.target.closest('[data-delete]')) {
      confirmModal(__('Delete taxonomy'), sprintf(__('Remove "%s"? Its terms stay in the database but are hidden until it is added again with the same slug.'), t.plural_name), __('Delete')).then(function (r) {
        if (!r.ok) return;
        post('/taxonomies/delete', { taxonomy: t.ID }).then(function () { toast(__('Taxonomy removed.')); loaders.taxonomies(); }).catch(function (e) { toast(e.message, true); });
      });
    }
  });

  function taxEditor(initial) {
    var t = initial || { ID: '', singular_name: '', plural_name: '', hierarchical: true, assign_to: ['post'] };
    modal({
      title: initial ? sprintf(__('Edit "%s"'), t.plural_name) : __('New taxonomy'),
      body: '<div class="wb-grid2">'
        + '<label class="wb-field">' + esc(__('Singular name')) + '<input data-f="singular_name" type="text" value="' + esc(t.singular_name) + '" placeholder="' + esc(__('Genre')) + '"></label>'
        + '<label class="wb-field">' + esc(__('Plural name')) + '<input data-f="plural_name" type="text" value="' + esc(t.plural_name) + '" placeholder="' + esc(__('Genres')) + '"></label>'
        + '<label class="wb-field">' + esc(__('Slug')) + '<input data-f="taxonomy" type="text" maxlength="32" value="' + esc(t.ID) + '"' + (initial ? ' readonly' : '') + ' placeholder="genre"></label>'
        + '<label class="wb-field">' + esc(__('Kind')) + '<select data-f="hierarchical"><option value="1"' + (t.hierarchical ? ' selected' : '') + '>' + esc(__('Categories (nested, tick boxes)')) + '</option><option value=""' + (!t.hierarchical ? ' selected' : '') + '>' + esc(__('Tags (flat, typed in)')) + '</option></select></label>'
        + '</div>'
        + '<fieldset class="wb-field"><legend>' + esc(__('Use on')) + '</legend>' + checks('assign_to', TAX.postTypes, t.assign_to, true) + '</fieldset>',
      onOpen: function (card) {
        if (initial) return;
        var one = card.querySelector('[data-f="singular_name"]'), many = card.querySelector('[data-f="plural_name"]'), slug = card.querySelector('[data-f="taxonomy"]');
        one.addEventListener('input', function () {
          if (!many.dataset.touched) many.value = one.value ? one.value + 's' : '';
          if (!slug.dataset.touched) slug.value = one.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 32);
        });
        many.addEventListener('input', function () { many.dataset.touched = '1'; });
        slug.addEventListener('input', function () { slug.dataset.touched = '1'; });
      },
      onOk: function (card) {
        function v(n) { return card.querySelector('[data-f="' + n + '"]').value.trim(); }
        return post('/taxonomies/save', {
          taxonomy: v('taxonomy'), singular_name: v('singular_name'), plural_name: v('plural_name'),
          hierarchical: v('hierarchical') === '1', assign_to: checked(card, 'assign_to')
        }).then(function () { toast(__('Taxonomy saved.')); loaders.taxonomies(); });
      }
    });
  }

  // ================================================================ META GROUPS
  var META = { rows: [], postTypes: [], types: [] };
  var typeLabels = {
    text: __('Text'), textarea: __('Paragraph'), number: __('Number'), checkbox: __('Yes / no'),
    select: __('Choice list'), email: __('Email'), url: __('Link'), date: __('Date')
  };

  loaders.metagroups = function () {
    setState('metagroups', 'loading');
    get('/metagroups').then(function (d) {
      loaded.metagroups = true;
      META = { rows: d.groups || [], postTypes: d.post_types || [], types: d.types || [] };
      var list = panel('metagroups').querySelector('[data-wb-list]');
      setState('metagroups', META.rows.length ? 'ready' : 'empty');
      list.innerHTML = '<div class="wb-cards">' + META.rows.map(function (g) {
        var fields = g.fields || [];
        return '<div class="wb-card" data-key="' + esc(g.ID) + '" data-search="' + esc((g.ID + ' ' + g.title + ' ' + fields.map(function (f) { return f.id + ' ' + f.label; }).join(' ')).toLowerCase()) + '">'
          + '<h3>' + esc(g.title || g.ID) + '</h3>'
          + '<div class="wb-meta">' + esc((g.assign_to || []).map(function (s) { return ptLabel(META.postTypes, s); }).join(', ') || '—') + ' · ' + esc(g.context === 'side' ? __('sidebar') : __('main column')) + '</div>'
          + (fields.length ? '<ul class="wb-fields">' + fields.map(function (f) {
            return '<li><span>' + esc(f.label) + ' <em>' + esc(typeLabels[f.type] || f.type) + ' · <code>' + esc(f.id) + '</code></em></span>'
              + '<button type="button" class="wb-btn wb-btn--sm wb-btn--link" data-field-del="' + esc(f.id) + '" aria-label="' + esc(sprintf(__('Remove field %s'), f.label)) + '">' + esc(__('Remove')) + '</button></li>';
          }).join('') + '</ul>' : '<p class="wb-note">' + esc(__('No fields yet.')) + '</p>')
          + '<div class="wb-card-actions"><button type="button" class="wb-btn wb-btn--sm" data-edit>' + esc(__('Edit')) + '</button><button type="button" class="wb-btn wb-btn--sm wb-btn--danger" data-delete>' + esc(__('Delete')) + '</button></div></div>';
      }).join('') + '</div>';
      refilter('metagroups');
    }).catch(function (e) { toast(e.message, true); });
  };

  panel('metagroups').addEventListener('click', function (ev) {
    if (ev.target.closest('[data-wb-add]')) return metaEditor(null);
    var card = ev.target.closest('[data-key]');
    if (!card) return;
    var g = META.rows.filter(function (r) { return r.ID === card.getAttribute('data-key'); })[0];
    if (ev.target.closest('[data-edit]')) return metaEditor(g);
    if (ev.target.closest('[data-delete]')) {
      return confirmModal(__('Delete field group'), sprintf(__('Delete "%s"? Its box disappears from the editing screen.'), g.title), __('Delete'), __('Also delete the values already saved on posts')).then(function (r) {
        if (!r.ok) return;
        post('/metagroups/delete', { id: g.ID, purge: r.extra }).then(function () { toast(__('Field group deleted.')); loaders.metagroups(); }).catch(function (e) { toast(e.message, true); });
      });
    }
    var fd = ev.target.closest('[data-field-del]');
    if (fd) {
      var fid = fd.getAttribute('data-field-del');
      confirmModal(__('Remove field'), sprintf(__('Remove "%1$s" from "%2$s"?'), fid, g.title), __('Remove'), __('Also delete the values already saved on posts')).then(function (r) {
        if (!r.ok) return;
        post('/metagroups/field/delete', { group_id: g.ID, field_id: fid, purge: r.extra }).then(function () { toast(__('Field removed.')); loaders.metagroups(); }).catch(function (e) { toast(e.message, true); });
      });
    }
  });

  function fieldRow(field) {
    field = field || { id: '', label: '', type: 'text', description: '', options: {} };
    var opts = Object.keys(field.options || {}).map(function (k) { return k === field.options[k] ? k : k + '|' + field.options[k]; }).join('\n');
    return '<div class="wb-metafield" data-mf-row' + (field.id ? ' data-existing="1"' : '') + '>'
      + '<div class="wb-metafield-head"><span>' + esc(__('Field')) + '</span><button type="button" class="wb-btn wb-btn--sm wb-btn--link" data-mf-remove>' + esc(__('Remove')) + '</button></div>'
      + '<div class="wb-grid2">'
      + '<label class="wb-field">' + esc(__('Label')) + '<input data-mf="label" type="text" value="' + esc(field.label) + '"></label>'
      + '<label class="wb-field">' + esc(__('Meta key')) + '<input data-mf="id" type="text" value="' + esc(field.id) + '"' + (field.id ? ' readonly' : '') + ' placeholder="' + esc(__('from the label')) + '"></label>'
      + '<label class="wb-field">' + esc(__('Type')) + '<select data-mf="type">' + META.types.map(function (t) {
        return '<option value="' + esc(t) + '"' + (t === field.type ? ' selected' : '') + '>' + esc(typeLabels[t] || t) + '</option>';
      }).join('') + '</select></label>'
      + '<label class="wb-field">' + esc(__('Help text')) + '<input data-mf="description" type="text" value="' + esc(field.description || '') + '"></label>'
      + '</div>'
      + '<label class="wb-field" data-mf-options' + (field.type === 'select' ? '' : ' hidden') + '>' + esc(__('Choices, one per line (value|label)')) + '<textarea data-mf="options" rows="3">' + esc(opts) + '</textarea></label>'
      + '</div>';
  }

  function metaEditor(initial) {
    var g = initial || { ID: '', title: '', assign_to: ['post'], context: 'normal', priority: 'default', fields: [] };
    modal({
      title: initial ? sprintf(__('Edit "%s"'), g.title) : __('New field group'),
      body: '<div class="wb-grid2">'
        + '<label class="wb-field">' + esc(__('Box title')) + '<input data-f="title" type="text" value="' + esc(g.title) + '" placeholder="' + esc(__('Event details')) + '"></label>'
        + '<label class="wb-field">' + esc(__('Group id')) + '<input data-f="id" type="text" value="' + esc(g.ID) + '"' + (initial ? ' readonly' : '') + ' placeholder="event_details"></label>'
        + '<label class="wb-field">' + esc(__('Position')) + '<select data-f="context">'
        + [['normal', __('Main column')], ['side', __('Sidebar')], ['advanced', __('Below the main column')]].map(function (o) { return '<option value="' + o[0] + '"' + (g.context === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('')
        + '</select></label>'
        + '<label class="wb-field">' + esc(__('Priority')) + '<select data-f="priority">'
        + [['high', __('Top')], ['default', __('Normal')], ['low', __('Bottom')]].map(function (o) { return '<option value="' + o[0] + '"' + (g.priority === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('')
        + '</select></label></div>'
        + '<fieldset class="wb-field"><legend>' + esc(__('Show on')) + '</legend>' + checks('assign_to', META.postTypes, g.assign_to, true) + '</fieldset>'
        + '<div class="wb-metafields" data-mf-list>' + (g.fields || []).map(fieldRow).join('') + '</div>'
        + '<button type="button" class="wb-btn" data-mf-add>+ ' + esc(__('Add field')) + '</button>',
      onOpen: function (card) {
        var list = card.querySelector('[data-mf-list]');
        if (!initial) {
          list.insertAdjacentHTML('beforeend', fieldRow(null));
          var title = card.querySelector('[data-f="title"]'), id = card.querySelector('[data-f="id"]');
          title.addEventListener('input', function () { if (!id.dataset.touched) id.value = title.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, ''); });
          id.addEventListener('input', function () { id.dataset.touched = '1'; });
        }
        card.addEventListener('click', function (e) {
          if (e.target.closest('[data-mf-add]')) { list.insertAdjacentHTML('beforeend', fieldRow(null)); list.lastElementChild.querySelector('[data-mf="label"]').focus(); }
          var rm = e.target.closest('[data-mf-remove]');
          if (rm) rm.closest('[data-mf-row]').remove();
        });
        card.addEventListener('change', function (e) {
          var sel = e.target.closest('[data-mf="type"]');
          if (sel) sel.closest('[data-mf-row]').querySelector('[data-mf-options]').hidden = sel.value !== 'select';
        });
      },
      onOk: function (card) {
        function v(n) { return card.querySelector('[data-f="' + n + '"]').value.trim(); }
        var fields = [];
        card.querySelectorAll('[data-mf-row]').forEach(function (row) {
          function rv(n) { return row.querySelector('[data-mf="' + n + '"]').value.trim(); }
          var label = rv('label'), id = rv('id');
          if (!label && !id) return;
          var options = {};
          if (rv('type') === 'select') {
            rv('options').split(/\r?\n/).forEach(function (line) {
              line = line.trim();
              if (!line) return;
              var bar = line.indexOf('|');
              if (bar === -1) options[line] = line;
              else { var k = line.slice(0, bar).trim(); if (k) options[k] = line.slice(bar + 1).trim() || k; }
            });
          }
          fields.push({ id: id, label: label, type: rv('type'), description: rv('description'), options: options });
        });
        return post('/metagroups/save', {
          id: v('id'), title: v('title'), context: v('context'), priority: v('priority'),
          assign_to: checked(card, 'assign_to'), fields: fields
        }).then(function () { toast(__('Field group saved.')); loaders.metagroups(); });
      }
    });
  }

  // ================================================================ MEMBERSHIP
  var MEM = null;

  loaders.membership = function () {
    setState('membership', 'loading');
    get('/membership').then(function (d) { loaded.membership = true; MEM = d; renderMembership(); })
      .catch(function (e) { toast(e.message, true); });
  };

  function tierRow(t) {
    t = t || { id: '', label: '', rank: 0, members: 0 };
    return '<tr data-tier>'
      + '<td data-label="' + esc(__('Name')) + '"><input type="text" data-t="label" value="' + esc(t.label) + '" aria-label="' + esc(__('Name')) + '"></td>'
      + '<td data-label="' + esc(__('Id')) + '"><input type="text" data-t="id" value="' + esc(t.id) + '"' + (t.id ? ' readonly' : '') + ' aria-label="' + esc(__('Id')) + '"></td>'
      + '<td data-label="' + esc(__('Rank')) + '"><input type="number" data-t="rank" value="' + esc(t.rank) + '" aria-label="' + esc(__('Rank')) + '"></td>'
      + '<td data-label="' + esc(__('Members')) + '">' + esc(t.members || 0) + '</td>'
      + '<td class="wb-actions-cell"><button type="button" class="wb-btn wb-btn--sm wb-btn--link" data-tier-remove>' + esc(__('Remove')) + '</button></td></tr>';
  }

  function renderMembership() {
    var p = panel('membership');
    var form = p.querySelector('[data-wb-membership]');
    setState('membership', 'ready');
    form.hidden = false;
    form.querySelector('[data-wb-tiers]').innerHTML = MEM.tiers.map(tierRow).join('');
    form.querySelector('[data-wb-gate-types]').innerHTML = MEM.post_types.map(function (pt) {
      return '<label><input type="checkbox" data-gate value="' + esc(pt.value) + '"' + (MEM.gate_types.indexOf(pt.value) !== -1 ? ' checked' : '') + '> ' + esc(pt.label) + '</label>';
    }).join('');
    form.querySelector('[data-wb-join]').value = MEM.join_url || '';
    form.querySelector('[data-wb-account]').value = MEM.account_url || '';
    form.querySelector('[data-wb-delete-data]').checked = !!MEM.delete_on_uninstall;
    syncDefaultTier(MEM.default_tier);
  }

  function readTiers(form) {
    return [].slice.call(form.querySelectorAll('[data-tier]')).map(function (r) {
      var label = r.querySelector('[data-t="label"]').value.trim();
      var id = r.querySelector('[data-t="id"]').value.trim() || label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
      return { id: id, label: label, rank: parseInt(r.querySelector('[data-t="rank"]').value || '0', 10) };
    }).filter(function (t) { return t.id; });
  }

  function syncDefaultTier(current) {
    var form = panel('membership').querySelector('[data-wb-membership]');
    var sel = form.querySelector('[data-wb-default-tier]');
    if (current === undefined) current = sel.value;
    sel.innerHTML = '<option value="">' + esc(__('Nothing — everything is public')) + '</option>' + readTiers(form).map(function (t) {
      return '<option value="' + esc(t.id) + '"' + (t.id === current ? ' selected' : '') + '>' + esc(sprintf(__('%s members and above'), t.label || t.id)) + '</option>';
    }).join('');
  }

  var memForm = panel('membership').querySelector('[data-wb-membership]');
  memForm.addEventListener('click', function (ev) {
    if (ev.target.closest('[data-wb-tier-add]')) {
      var rows = readTiers(memForm);
      var top = rows.reduce(function (m, t) { return Math.max(m, t.rank); }, 0);
      memForm.querySelector('[data-wb-tiers]').insertAdjacentHTML('beforeend', tierRow({ id: '', label: '', rank: top + 10, members: 0 }));
      memForm.querySelector('[data-wb-tiers] tr:last-child [data-t="label"]').focus();
    }
    var rm = ev.target.closest('[data-tier-remove]');
    if (rm) { rm.closest('[data-tier]').remove(); syncDefaultTier(); }
  });
  memForm.addEventListener('input', function (ev) { if (ev.target.closest('[data-tier]')) syncDefaultTier(); });
  memForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = memForm.querySelector('[type=submit]');
    btn.disabled = true;
    post('/membership/save', {
      tiers: readTiers(memForm),
      default_tier: memForm.querySelector('[data-wb-default-tier]').value,
      gate_types: [].slice.call(memForm.querySelectorAll('[data-gate]:checked')).map(function (i) { return i.value; }),
      join_url: memForm.querySelector('[data-wb-join]').value.trim(),
      account_url: memForm.querySelector('[data-wb-account]').value.trim(),
      delete_on_uninstall: memForm.querySelector('[data-wb-delete-data]').checked
    }).then(function (d) { MEM = d; renderMembership(); toast(__('Membership settings saved.')); })
      .catch(function (e) { toast(e.message, true); })
      .then(function () { btn.disabled = false; });
  });

  // ------------------------------------------------------------------ start
  var start = root.querySelector('[data-wb-tab].is-on');
  showTab(start ? start.getAttribute('data-wb-tab') : 'features');
})();
