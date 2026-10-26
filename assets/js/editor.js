/* Sepo WordPress Bridge — block editor: Post field block + Visibility panel. No build step. */
(function (wp, cfg) {
  if (!wp || !wp.blocks || !wp.element) return;
  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var __ = wp.i18n.__;
  var be = wp.blockEditor || wp.editor;
  var InspectorControls = be.InspectorControls;
  var useBlockProps = be.useBlockProps;
  var C = wp.components;
  var SSR = wp.serverSideRender;
  cfg = cfg || { fields: [], tiers: [], roles: [] };

  // ------------------------------------------------------------ Post field block
  var core = [
    { value: 'post_title', label: __('Title', 'sepo-wp-bridge') },
    { value: 'post_date', label: __('Published date', 'sepo-wp-bridge') },
    { value: 'post_modified', label: __('Updated date', 'sepo-wp-bridge') },
    { value: 'post_author', label: __('Author', 'sepo-wp-bridge') },
    { value: 'post_excerpt', label: __('Excerpt', 'sepo-wp-bridge') },
    { value: 'permalink', label: __('Link', 'sepo-wp-bridge') }
  ];
  var options = core.concat(cfg.fields.map(function (f) {
    return { value: f.value, label: __('Custom field:', 'sepo-wp-bridge') + ' ' + f.label };
  }));

  wp.blocks.registerBlockType('sepo/dynamic-field', {
    apiVersion: 2,
    title: __('Post field', 'sepo-wp-bridge'),
    description: __('Shows one value of the current post: title, date, author, link or a custom field.', 'sepo-wp-bridge'),
    icon: 'database',
    category: 'theme',
    usesContext: ['postId', 'postType'],
    attributes: {
      field: { type: 'string', default: 'post_title' },
      label: { type: 'string', default: '' }
    },
    edit: function (props) {
      var a = props.attributes;
      var blockProps = useBlockProps ? useBlockProps() : {};
      var postId = props.context && props.context.postId;
      return el('div', blockProps,
        el(InspectorControls, null,
          el(C.PanelBody, { title: __('Field', 'sepo-wp-bridge'), initialOpen: true },
            el(C.SelectControl, {
              label: __('Show', 'sepo-wp-bridge'), value: a.field, options: options,
              onChange: function (v) { props.setAttributes({ field: v }); }
            }),
            el(C.TextControl, {
              label: __('Label before the value', 'sepo-wp-bridge'), value: a.label,
              onChange: function (v) { props.setAttributes({ label: v }); }
            })
          )
        ),
        SSR ? el(SSR, {
          block: 'sepo/dynamic-field',
          attributes: a,
          urlQueryArgs: postId ? { post_id: postId } : {},
          EmptyResponsePlaceholder: function () {
            return el('p', { className: 'sepo-field-empty', style: { opacity: .6 } },
              (a.label ? a.label + ' ' : '') + '[' + a.field + '] ' + __('— empty for this post', 'sepo-wp-bridge'));
          }
        }) : el('p', null, '[' + a.field + ']')
      );
    },
    save: function () { return null; }
  });

  // ------------------------------------------------------------ Field group block
  var groupOptions = [{ value: '', label: __('Every group for this post type', 'sepo-wp-bridge') }].concat(cfg.groups || []);

  wp.blocks.registerBlockType('sepo/field-group', {
    apiVersion: 2,
    title: __('Field group', 'sepo-wp-bridge'),
    description: __('Shows all the fields of a group for the current post, as a table, a grid or inline.', 'sepo-wp-bridge'),
    icon: 'list-view',
    category: 'theme',
    keywords: [__('custom fields', 'sepo-wp-bridge'), __('meta', 'sepo-wp-bridge'), __('details', 'sepo-wp-bridge')],
    usesContext: ['postId', 'postType'],
    attributes: {
      group: { type: 'string', default: '' },
      style: { type: 'string', default: '' },
      showTitle: { type: 'string', default: 'group' },
      showEmpty: { type: 'boolean', default: false }
    },
    edit: function (props) {
      var a = props.attributes;
      var blockProps = useBlockProps ? useBlockProps() : {};
      var postId = props.context && props.context.postId;
      if (!cfg.groups || !cfg.groups.length) {
        return el('div', blockProps, el(C.Placeholder, { icon: 'list-view', label: __('Field group', 'sepo-wp-bridge') },
          __('Create a field group under WP Bridge → Meta fields first.', 'sepo-wp-bridge')));
      }
      return el('div', blockProps,
        el(InspectorControls, null,
          el(C.PanelBody, { title: __('Fields', 'sepo-wp-bridge'), initialOpen: true },
            el(C.SelectControl, {
              label: __('Group', 'sepo-wp-bridge'), value: a.group, options: groupOptions,
              onChange: function (v) { props.setAttributes({ group: v }); }
            }),
            el(C.SelectControl, {
              label: __('Style', 'sepo-wp-bridge'), value: a.style,
              options: [
                { value: '', label: __('As set for the group', 'sepo-wp-bridge') },
                { value: 'table', label: __('Table', 'sepo-wp-bridge') },
                { value: 'grid', label: __('Grid', 'sepo-wp-bridge') },
                { value: 'inline', label: __('Inline', 'sepo-wp-bridge') }
              ],
              onChange: function (v) { props.setAttributes({ style: v }); }
            }),
            el(C.SelectControl, {
              label: __('Group title', 'sepo-wp-bridge'), value: a.showTitle,
              options: [
                { value: 'group', label: __('As set for the group', 'sepo-wp-bridge') },
                { value: 'yes', label: __('Show', 'sepo-wp-bridge') },
                { value: 'no', label: __('Hide', 'sepo-wp-bridge') }
              ],
              onChange: function (v) { props.setAttributes({ showTitle: v }); }
            }),
            el(C.ToggleControl, {
              label: __('List empty fields too', 'sepo-wp-bridge'), checked: !!a.showEmpty,
              onChange: function (v) { props.setAttributes({ showEmpty: v }); }
            })
          )
        ),
        SSR ? el(SSR, {
          block: 'sepo/field-group',
          attributes: a,
          urlQueryArgs: postId ? { post_id: postId } : {},
          EmptyResponsePlaceholder: function () {
            return el('p', { className: 'sepo-field-empty', style: { opacity: .6, padding: '12px', border: '1px dashed currentColor', borderRadius: '8px' } },
              __('Field group — no values for this post yet. Fill them in the field box below the editor.', 'sepo-wp-bridge'));
          }
        }) : el('p', null, __('Field group', 'sepo-wp-bridge'))
      );
    },
    save: function () { return null; }
  });

  // ------------------------------------------------------------ Visibility panel
  var hooks = wp.hooks;
  var createHOC = wp.compose.createHigherOrderComponent;

  hooks.addFilter('blocks.registerBlockType', 'sepo-wb/visibility-attr', function (settings) {
    settings.attributes = Object.assign({}, settings.attributes, { spVisibility: { type: 'object' } });
    return settings;
  });

  var withPanel = createHOC(function (BlockEdit) {
    return function (props) {
      if (!props.isSelected) return el(BlockEdit, props);
      var v = props.attributes.spVisibility || {};
      function set(patch) {
        var next = Object.assign({}, v, patch);
        var empty = (!next.who || next.who === 'everyone') && !(next.roles && next.roles.length) && !next.tier && !next.locked;
        props.setAttributes({ spVisibility: empty ? undefined : next });
      }
      var roles = v.roles || [];
      var active = v.who && v.who !== 'everyone' || roles.length || v.tier;
      return el(Fragment, null,
        el(BlockEdit, props),
        el(InspectorControls, null,
          el(C.PanelBody, { title: __('Visibility', 'sepo-wp-bridge') + (active ? ' •' : ''), initialOpen: !!active },
            el(C.SelectControl, {
              label: __('Show to', 'sepo-wp-bridge'), value: v.who || 'everyone',
              options: [
                { value: 'everyone', label: __('Everyone', 'sepo-wp-bridge') },
                { value: 'logged_in', label: __('Signed-in users only', 'sepo-wp-bridge') },
                { value: 'guests', label: __('Signed-out visitors only', 'sepo-wp-bridge') }
              ],
              onChange: function (x) { set({ who: x }); }
            }),
            v.who === 'guests' ? null : el(Fragment, null,
              el('p', { style: { margin: '8px 0 4px', fontWeight: 600 } }, __('Roles (none = any)', 'sepo-wp-bridge')),
              cfg.roles.map(function (r) {
                return el(C.CheckboxControl, {
                  key: r.value, label: r.label, checked: roles.indexOf(r.value) !== -1,
                  onChange: function (on) {
                    var next = roles.filter(function (x) { return x !== r.value; });
                    if (on) next.push(r.value);
                    set({ roles: next });
                  }
                });
              }),
              cfg.tiers.length ? el(C.SelectControl, {
                label: __('Members of tier', 'sepo-wp-bridge'), value: v.tier || '',
                options: [{ value: '', label: __('Any (no tier needed)', 'sepo-wp-bridge') }].concat(cfg.tiers.map(function (t) {
                  return { value: t.value, label: t.label + ' ' + __('and above', 'sepo-wp-bridge') };
                })),
                onChange: function (x) { set({ tier: x }); }
              }) : null,
              v.tier ? el(C.ToggleControl, {
                label: __('Show a lock instead of hiding it', 'sepo-wp-bridge'), checked: !!v.locked,
                onChange: function (x) { set({ locked: x }); }
              }) : null
            ),
            el('p', { style: { opacity: .7, fontSize: '12px', margin: '8px 0 0' } },
              __('The editor always shows the block; the rule applies on the site.', 'sepo-wp-bridge'))
          )
        )
      );
    };
  }, 'withSepoVisibility');

  hooks.addFilter('editor.BlockEdit', 'sepo-wb/visibility-panel', withPanel);
})(window.wp, window.SepoWBEditor);
