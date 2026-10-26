<?php
namespace SepoWB\Schema;

use InvalidArgumentException;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Custom post types, taxonomies and meta-field groups declared in the panel,
 * stored as options and registered on every request.
 */
final class SchemaManager
{
    public const OPT_CPTS  = 'sepo_wb_cpts';
    public const OPT_TAX   = 'sepo_wb_taxonomies';
    public const OPT_META  = 'sepo_wb_metagroups';
    public const OPT_FLUSH = 'sepo_wb_flush';

    public const SUPPORTS = ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'revisions', 'page-attributes', 'custom-fields', 'post-formats', 'trackbacks'];
    public const FIELD_TYPES = ['text', 'textarea', 'number', 'checkbox', 'select', 'email', 'url', 'date'];

    /** Names WordPress uses itself (post types, query vars) — never valid slugs. */
    private const RESERVED = [
        'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
        'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles',
        'wp_navigation', 'wp_font_family', 'wp_font_face', 'action', 'author', 'order', 'theme', 'type', 'name',
        'category', 'post_tag', 'nav_menu', 'link_category', 'post_format', 'wp_theme', 'wp_template_part_area',
        'attachment_id', 'calendar', 'cat', 'category_name', 'comments_popup', 'cpage', 'day', 'debug', 'error',
        'exact', 'feed', 'hour', 'm', 'minute', 'monthnum', 'more', 'nopaging', 'offset', 'orderby', 'p', 'paged',
        'pagename', 'pb', 'perm', 'posts', 'posts_per_page', 'preview', 'robots', 's', 'search', 'second',
        'sentence', 'static', 'subpost', 'subpost_id', 'tag', 'tag_id', 'taxonomy', 'tb', 'term', 'terms', 'w',
        'withcomments', 'withoutcomments', 'year', 'fields', 'embed', 'page_id', 'post_type', 'status', 'title',
    ];

    // ============================================================ boot

    public function hook(): void
    {
        add_action('init', [$this, 'registerPostTypes'], 5);
        add_action('init', [$this, 'registerTaxonomies'], 6);
        add_action('init', [$this, 'registerMeta'], 7);
        add_action('init', [$this, 'maybeFlush'], 99);
        if (is_admin()) {
            add_action('add_meta_boxes', [$this, 'registerMetaBoxes']);
            add_action('save_post', [$this, 'saveMetaFields'], 10, 2);
        }
    }

    /** Rewrite rules are rebuilt on the request after a change, once the new types are registered. */
    public function maybeFlush(): void
    {
        if (get_option(self::OPT_FLUSH)) {
            delete_option(self::OPT_FLUSH);
            flush_rewrite_rules(false);
        }
    }

    private function scheduleFlush(): void
    {
        update_option(self::OPT_FLUSH, 1);
    }

    private static function slug(string $raw): string
    {
        return (string) preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($raw)));
    }

    /** @return list<string> */
    private static function list($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : explode(',', $raw);
        }
        $out = [];
        foreach ((array) $raw as $v) {
            $v = self::slug((string) $v);
            if ($v !== '' && !in_array($v, $out, true)) {
                $out[] = $v;
            }
        }
        return $out;
    }

    // ============================================================ CPTs

    /** @return array<string,array<string,mixed>> */
    public function allCpts(): array
    {
        $o = get_option(self::OPT_CPTS, []);
        return is_array($o) ? $o : [];
    }

    public function registerPostTypes(): void
    {
        foreach ($this->allCpts() as $slug => $cpt) {
            $slug = (string) $slug;
            if ($slug === '' || (post_type_exists($slug) && !$this->owns('cpt', $slug))) {
                continue;
            }
            $this->registerOneCpt($slug, (array) $cpt);
        }
    }

    /** @param array<string,mixed> $cpt */
    private function registerOneCpt(string $slug, array $cpt): void
    {
        $one  = (string) ($cpt['singular_name'] ?? $slug);
        $many = (string) ($cpt['plural_name'] ?? $slug);
        /* translators: %s: singular name */
        $s = static fn(string $f) => sprintf($f, $one);
        /* translators: %s: plural name */
        $p = static fn(string $f) => sprintf($f, $many);

        $args = [
            'labels' => [
                'name'               => $many,
                'singular_name'      => $one,
                'menu_name'          => $many,
                'add_new'            => __('Add New', 'sepo-wp-bridge'),
                'add_new_item'       => $s(__('Add New %s', 'sepo-wp-bridge')),
                'edit_item'          => $s(__('Edit %s', 'sepo-wp-bridge')),
                'new_item'           => $s(__('New %s', 'sepo-wp-bridge')),
                'view_item'          => $s(__('View %s', 'sepo-wp-bridge')),
                'all_items'          => $p(__('All %s', 'sepo-wp-bridge')),
                'search_items'       => $p(__('Search %s', 'sepo-wp-bridge')),
                'not_found'          => $p(__('No %s found.', 'sepo-wp-bridge')),
                'not_found_in_trash' => $p(__('No %s found in Trash.', 'sepo-wp-bridge')),
                'archives'           => $s(__('%s Archives', 'sepo-wp-bridge')),
            ],
            'public'       => !empty($cpt['public']),
            'show_ui'      => true,
            'has_archive'  => !empty($cpt['has_archive']),
            'hierarchical' => !empty($cpt['hierarchical']),
            'show_in_rest' => true,
            'supports'     => (array) ($cpt['supports'] ?? ['title', 'editor', 'thumbnail']),
            'menu_icon'    => (string) ($cpt['menu_icon'] ?? 'dashicons-admin-post'),
        ];
        if (!empty($cpt['rewrite_slug'])) {
            $args['rewrite'] = ['slug' => (string) $cpt['rewrite_slug'], 'with_front' => false];
        }
        register_post_type($slug, (array) apply_filters('sepo_wb_post_type_args', $args, $slug, $cpt));
    }

    /** True when this plugin registered (or will register) that slug. */
    private function owns(string $kind, string $slug): bool
    {
        static $registered = ['cpt' => [], 'tax' => []];
        if (isset($registered[$kind][$slug])) {
            return true;
        }
        // Not yet registered by anyone: we may take it.
        $exists = $kind === 'cpt' ? post_type_exists($slug) : taxonomy_exists($slug);
        if (!$exists) {
            $registered[$kind][$slug] = true;
            return true;
        }
        return false;
    }

    /** @param array<string,mixed> $data */
    public function saveCpt(string $slug, array $data): void
    {
        $slug = self::slug($slug);
        if ($slug === '' || strlen($slug) > 20) {
            throw new InvalidArgumentException(__('Slug: 1–20 lower-case letters, numbers, - or _.', 'sepo-wp-bridge'));
        }
        $existing = $this->allCpts();
        if (in_array($slug, self::RESERVED, true) || (!isset($existing[$slug]) && post_type_exists($slug))) {
            throw new InvalidArgumentException(sprintf(/* translators: %s: slug */ __('"%s" is already used by WordPress or another plugin.', 'sepo-wp-bridge'), $slug));
        }
        $supports = array_values(array_intersect(self::list($data['supports'] ?? []), self::SUPPORTS));
        if ($supports === []) {
            $supports = ['title', 'editor', 'thumbnail'];
        }
        $icon = (string) ($data['menu_icon'] ?? '');
        if (!preg_match('/^dashicons-[a-z0-9-]+$/', $icon) && !preg_match('#^(https?://|data:image/svg\+xml;base64,)#', $icon)) {
            $icon = 'dashicons-admin-post';
        }
        $singular = sanitize_text_field((string) ($data['singular_name'] ?? '')) ?: ucfirst($slug);

        $existing[$slug] = [
            'singular_name' => $singular,
            'plural_name'   => sanitize_text_field((string) ($data['plural_name'] ?? '')) ?: $singular . 's',
            'public'        => !empty($data['public']),
            'has_archive'   => !empty($data['has_archive']),
            'hierarchical'  => !empty($data['hierarchical']),
            'supports'      => $supports,
            'menu_icon'     => $icon,
            'rewrite_slug'  => sanitize_title((string) ($data['rewrite_slug'] ?? '')),
        ];
        update_option(self::OPT_CPTS, $existing);
        $this->scheduleFlush();
    }

    public function deleteCpt(string $slug): bool
    {
        $cpts = $this->allCpts();
        if (!isset($cpts[$slug])) {
            return false;
        }
        unset($cpts[$slug]);
        update_option(self::OPT_CPTS, $cpts);
        $this->scheduleFlush();
        return true;
    }

    /** Number of posts saved under a post type (for the delete warning). */
    public function countPosts(string $slug): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'auto-draft'", $slug));
    }

    // ============================================================ Taxonomies

    /** @return array<string,array<string,mixed>> */
    public function allTaxonomies(): array
    {
        $o = get_option(self::OPT_TAX, []);
        return is_array($o) ? $o : [];
    }

    public function registerTaxonomies(): void
    {
        foreach ($this->allTaxonomies() as $slug => $tax) {
            $slug = (string) $slug;
            if ($slug === '' || (taxonomy_exists($slug) && !$this->owns('tax', $slug))) {
                continue;
            }
            $this->registerOneTaxonomy($slug, (array) $tax);
        }
    }

    /** @param array<string,mixed> $tax */
    private function registerOneTaxonomy(string $slug, array $tax): void
    {
        $one  = (string) ($tax['singular_name'] ?? $slug);
        $many = (string) ($tax['plural_name'] ?? $slug);
        $args = [
            'labels' => [
                'name'          => $many,
                'singular_name' => $one,
                'menu_name'     => $many,
                /* translators: %s: plural name */
                'all_items'     => sprintf(__('All %s', 'sepo-wp-bridge'), $many),
                /* translators: %s: singular name */
                'edit_item'     => sprintf(__('Edit %s', 'sepo-wp-bridge'), $one),
                /* translators: %s: singular name */
                'add_new_item'  => sprintf(__('Add New %s', 'sepo-wp-bridge'), $one),
                /* translators: %s: plural name */
                'search_items'  => sprintf(__('Search %s', 'sepo-wp-bridge'), $many),
                /* translators: %s: plural name */
                'not_found'     => sprintf(__('No %s found.', 'sepo-wp-bridge'), strtolower($many)),
            ],
            'hierarchical'      => !empty($tax['hierarchical']),
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'public'            => true,
        ];
        register_taxonomy($slug, (array) ($tax['assign_to'] ?? ['post']), (array) apply_filters('sepo_wb_taxonomy_args', $args, $slug, $tax));
    }

    /** @param array<string,mixed> $data */
    public function saveTaxonomy(string $slug, array $data): void
    {
        $slug = self::slug($slug);
        if ($slug === '' || strlen($slug) > 32) {
            throw new InvalidArgumentException(__('Slug: 1–32 lower-case letters, numbers, - or _.', 'sepo-wp-bridge'));
        }
        $all = $this->allTaxonomies();
        if (in_array($slug, self::RESERVED, true) || (!isset($all[$slug]) && taxonomy_exists($slug))) {
            throw new InvalidArgumentException(sprintf(/* translators: %s: slug */ __('"%s" is already used by WordPress or another plugin.', 'sepo-wp-bridge'), $slug));
        }
        $assign = self::list($data['assign_to'] ?? ['post']);
        if ($assign === []) {
            throw new InvalidArgumentException(__('Assign the taxonomy to at least one post type.', 'sepo-wp-bridge'));
        }
        $singular = sanitize_text_field((string) ($data['singular_name'] ?? '')) ?: ucfirst($slug);
        $all[$slug] = [
            'singular_name' => $singular,
            'plural_name'   => sanitize_text_field((string) ($data['plural_name'] ?? '')) ?: $singular . 's',
            'hierarchical'  => !empty($data['hierarchical']),
            'assign_to'     => $assign,
        ];
        update_option(self::OPT_TAX, $all);
        $this->scheduleFlush();
    }

    public function deleteTaxonomy(string $slug): bool
    {
        $all = $this->allTaxonomies();
        if (!isset($all[$slug])) {
            return false;
        }
        unset($all[$slug]);
        update_option(self::OPT_TAX, $all);
        $this->scheduleFlush();
        return true;
    }

    // ============================================================ Meta groups

    /** @return array<string,array<string,mixed>> */
    public function allMetaGroups(): array
    {
        $o = get_option(self::OPT_META, []);
        return is_array($o) ? $o : [];
    }

    /** Every field id in use, with the post types it applies to. @return array<string,array{type:string,assign:list<string>}> */
    public function allFields(): array
    {
        $out = [];
        foreach ($this->allMetaGroups() as $group) {
            foreach ((array) ($group['fields'] ?? []) as $f) {
                $id = (string) ($f['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $out[$id]['type'] = (string) ($f['type'] ?? 'text');
                $out[$id]['assign'] = array_values(array_unique(array_merge($out[$id]['assign'] ?? [], (array) ($group['assign_to'] ?? []))));
            }
        }
        return $out;
    }

    /** Fields are registered as post meta so the REST API and block editor know them. */
    public function registerMeta(): void
    {
        foreach ($this->allMetaGroups() as $group) {
            foreach ((array) ($group['fields'] ?? []) as $field) {
                $id = (string) ($field['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $type = (string) ($field['type'] ?? 'text');
                foreach ((array) ($group['assign_to'] ?? []) as $pt) {
                    // The REST API only exposes meta on types that support custom fields.
                    if (post_type_exists((string) $pt) && !post_type_supports((string) $pt, 'custom-fields')) {
                        add_post_type_support((string) $pt, 'custom-fields');
                    }
                    register_post_meta((string) $pt, $id, [
                        'type'              => $type === 'number' ? 'number' : ($type === 'checkbox' ? 'boolean' : 'string'),
                        'single'            => true,
                        'show_in_rest'      => true,
                        'sanitize_callback' => fn($v) => $this->cleanValue($field, $v),
                        'auth_callback'     => static fn($allowed, $key, $postId) => current_user_can('edit_post', (int) $postId),
                    ]);
                }
            }
        }
    }

    public function registerMetaBoxes(): void
    {
        foreach ($this->allMetaGroups() as $gid => $group) {
            if (empty($group['fields'])) {
                continue;
            }
            foreach ((array) ($group['assign_to'] ?? []) as $cpt) {
                add_meta_box(
                    'sepo_wb_mb_' . $gid,
                    (string) ($group['title'] ?? __('Custom fields', 'sepo-wp-bridge')),
                    function (WP_Post $post) use ($group): void {
                        $this->renderMetaBox($post, $group);
                    },
                    (string) $cpt,
                    (string) ($group['context'] ?? 'normal'),
                    (string) ($group['priority'] ?? 'default')
                );
            }
        }
    }

    /** @param array<string,mixed> $group */
    private function renderMetaBox(WP_Post $post, array $group): void
    {
        static $nonce = false;
        if (!$nonce) {
            wp_nonce_field('sepo_wb_save_meta', 'sepo_wb_meta_nonce');
            $nonce = true;
        }
        $side = ($group['context'] ?? 'normal') === 'side';

        echo '<div class="sepo-wb-fields' . ($side ? ' is-side' : '') . '">';
        foreach ((array) ($group['fields'] ?? []) as $field) {
            $id = (string) ($field['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $label = (string) ($field['label'] ?? $id);
            $type  = (string) ($field['type'] ?? 'text');
            $val   = (string) get_post_meta($post->ID, $id, true);
            $name  = 'sepo_wb_meta[' . $id . ']';
            $dom   = 'sepo-wb-' . $id;

            echo '<p class="sepo-wb-field">';
            if ($type === 'checkbox') {
                echo '<input type="hidden" name="' . esc_attr($name) . '" value="0">';
                echo '<label><input type="checkbox" id="' . esc_attr($dom) . '" name="' . esc_attr($name) . '" value="1" ' . checked($val, '1', false) . '> ' . esc_html($label) . '</label>';
            } else {
                echo '<label for="' . esc_attr($dom) . '" style="display:block;font-weight:600;margin-bottom:3px">' . esc_html($label) . '</label>';
                switch ($type) {
                    case 'textarea':
                        echo '<textarea class="widefat" id="' . esc_attr($dom) . '" name="' . esc_attr($name) . '" rows="4">' . esc_textarea($val) . '</textarea>';
                        break;
                    case 'select':
                        echo '<select class="widefat" id="' . esc_attr($dom) . '" name="' . esc_attr($name) . '"><option value="">—</option>';
                        foreach ((array) ($field['options'] ?? []) as $k => $v) {
                            echo '<option value="' . esc_attr((string) $k) . '" ' . selected($val, (string) $k, false) . '>' . esc_html((string) $v) . '</option>';
                        }
                        echo '</select>';
                        break;
                    default:
                        $html = in_array($type, ['number', 'email', 'url', 'date'], true) ? $type : 'text';
                        echo '<input class="widefat" type="' . esc_attr($html) . '"' . ($html === 'number' ? ' step="any"' : '') . ' id="' . esc_attr($dom) . '" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '">';
                }
            }
            if (!empty($field['description'])) {
                echo '<span class="description" style="display:block;margin-top:3px">' . esc_html((string) $field['description']) . '</span>';
            }
            echo '</p>';
        }
        echo '</div>';
    }

    /**
     * @param array<string,mixed> $field
     * @param mixed $v
     * @return mixed
     */
    private function cleanValue(array $field, $v)
    {
        $type = (string) ($field['type'] ?? 'text');
        if (is_array($v)) {
            $v = '';
        }
        $v = (string) $v;
        switch ($type) {
            case 'checkbox':
                return $v === '1' || $v === 'true' ? '1' : '0';
            case 'textarea':
                return sanitize_textarea_field($v);
            case 'number':
                $v = trim($v);
                return is_numeric($v) ? $v : '';
            case 'email':
                return sanitize_email($v);
            case 'url':
                return esc_url_raw(trim($v));
            case 'date':
                return preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($v)) ? trim($v) : '';
            case 'select':
                return array_key_exists($v, (array) ($field['options'] ?? [])) ? $v : '';
            default:
                return sanitize_text_field($v);
        }
    }

    public function saveMetaFields(int $postId, WP_Post $post): void
    {
        if (!isset($_POST['sepo_wb_meta_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['sepo_wb_meta_nonce'])), 'sepo_wb_save_meta')) {
            return;
        }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId)) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }
        $posted = isset($_POST['sepo_wb_meta']) && is_array($_POST['sepo_wb_meta']) ? wp_unslash($_POST['sepo_wb_meta']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned per field below

        foreach ($this->allMetaGroups() as $group) {
            if (!in_array($post->post_type, (array) ($group['assign_to'] ?? []), true)) {
                continue;
            }
            foreach ((array) ($group['fields'] ?? []) as $field) {
                $id = (string) ($field['id'] ?? '');
                if ($id === '' || !array_key_exists($id, $posted)) {
                    continue;   // field was not on the form: leave its value alone
                }
                $value = $this->cleanValue($field, $posted[$id]);
                if ($value === '' && ($field['type'] ?? '') !== 'checkbox') {
                    delete_post_meta($postId, $id);
                } else {
                    update_post_meta($postId, $id, $value);
                }
            }
        }
    }

    /** @param array<string,mixed> $data */
    public function saveMetaGroup(string $gid, array $data): void
    {
        $gid = self::slug($gid);
        if ($gid === '' || strlen($gid) > 40) {
            throw new InvalidArgumentException(__('Group id: 1–40 lower-case letters, numbers, - or _.', 'sepo-wp-bridge'));
        }
        $assign = self::list($data['assign_to'] ?? ['post']);
        if ($assign === []) {
            throw new InvalidArgumentException(__('Assign the group to at least one post type.', 'sepo-wp-bridge'));
        }

        $all = $this->allMetaGroups();
        // Field ids used by other groups, to stop two groups fighting over one key with different types.
        $taken = [];
        foreach ($all as $otherId => $g) {
            if ($otherId === $gid) {
                continue;
            }
            foreach ((array) ($g['fields'] ?? []) as $f) {
                $taken[(string) ($f['id'] ?? '')] = (string) ($f['type'] ?? 'text');
            }
        }

        $fields = [];
        $ids = [];
        foreach ((array) ($data['fields'] ?? []) as $f) {
            if (!is_array($f)) {
                continue;
            }
            $given = trim((string) ($f['id'] ?? ''));
            if ($given !== '' && $given[0] === '_') {
                throw new InvalidArgumentException(sprintf(/* translators: %s: field id */ __('Field id "%s" starts with _ — those keys belong to WordPress and other plugins.', 'sepo-wp-bridge'), $given));
            }
            $raw = $given !== '' ? $given : (string) ($f['label'] ?? '');
            $fid = trim((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower(remove_accents($raw))), '_');
            if ($fid === '') {
                continue;
            }
            if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $fid)) {
                throw new InvalidArgumentException(sprintf(/* translators: %s: field id */ __('Field id "%s" must start with a letter (ids starting with _ are WordPress’s own).', 'sepo-wp-bridge'), $fid));
            }
            if (isset($ids[$fid])) {
                throw new InvalidArgumentException(sprintf(/* translators: %s: field id */ __('Field id "%s" is used twice.', 'sepo-wp-bridge'), $fid));
            }
            $type = in_array($f['type'] ?? '', self::FIELD_TYPES, true) ? (string) $f['type'] : 'text';
            if (isset($taken[$fid]) && $taken[$fid] !== $type) {
                throw new InvalidArgumentException(sprintf(/* translators: %s: field id */ __('Field id "%s" is already a different type in another group.', 'sepo-wp-bridge'), $fid));
            }
            $ids[$fid] = true;

            $opts = $f['options'] ?? [];
            if (is_string($opts)) {
                $decoded = json_decode($opts, true);
                $opts = is_array($decoded) ? $decoded : [];
            }
            $cleanOpts = [];
            foreach ((array) $opts as $k => $v) {
                $k = sanitize_text_field((string) $k);
                if ($k !== '') {
                    $cleanOpts[$k] = sanitize_text_field((string) $v) ?: $k;
                }
            }
            if ($type === 'select' && $cleanOpts === []) {
                throw new InvalidArgumentException(sprintf(/* translators: %s: field id */ __('Give the select field "%s" at least one option.', 'sepo-wp-bridge'), $fid));
            }
            $fields[] = [
                'id'          => $fid,
                'label'       => sanitize_text_field((string) ($f['label'] ?? '')) ?: ucfirst(str_replace('_', ' ', $fid)),
                'type'        => $type,
                'description' => sanitize_text_field((string) ($f['description'] ?? '')),
                'options'     => $type === 'select' ? $cleanOpts : [],
                'show'        => !array_key_exists('show', $f) || !empty($f['show']),
                'column'      => !empty($f['column']),
            ];
        }

        $all[$gid] = [
            'title'     => sanitize_text_field((string) ($data['title'] ?? '')) ?: __('Custom fields', 'sepo-wp-bridge'),
            'assign_to' => $assign,
            'context'   => in_array($data['context'] ?? '', ['normal', 'side', 'advanced'], true) ? (string) $data['context'] : 'normal',
            'priority'  => in_array($data['priority'] ?? '', ['default', 'high', 'low'], true) ? (string) $data['priority'] : 'default',
            'fields'    => $fields,
            // How the group appears on the site: added to the content automatically or not, and its look.
            'display'   => in_array($data['display'] ?? '', ['none', 'before', 'after'], true) ? (string) $data['display'] : 'none',
            'style'     => in_array($data['style'] ?? '', ['table', 'grid', 'inline'], true) ? (string) $data['style'] : 'table',
            'show_title'=> !empty($data['show_title']),
        ];
        update_option(self::OPT_META, $all);
    }

    /**
     * Remove a group. Saved values stay in the database unless $purge is
     * true, and then only on the group's post types and only for field ids
     * no other group still uses.
     */
    public function deleteMetaGroup(string $gid, bool $purge = false): bool
    {
        $all = $this->allMetaGroups();
        if (!isset($all[$gid])) {
            return false;
        }
        $group = $all[$gid];
        unset($all[$gid]);
        update_option(self::OPT_META, $all);
        if ($purge) {
            foreach ((array) ($group['fields'] ?? []) as $f) {
                $this->purgeField((string) ($f['id'] ?? ''), (array) ($group['assign_to'] ?? []));
            }
        }
        return true;
    }

    public function deleteFieldFromGroup(string $gid, string $fid, bool $purge = false): bool
    {
        $all = $this->allMetaGroups();
        if (!isset($all[$gid]['fields'])) {
            return false;
        }
        $before = count((array) $all[$gid]['fields']);
        $all[$gid]['fields'] = array_values(array_filter(
            (array) $all[$gid]['fields'],
            static fn($f) => ($f['id'] ?? '') !== $fid
        ));
        if (count($all[$gid]['fields']) === $before) {
            return false;
        }
        update_option(self::OPT_META, $all);
        if ($purge) {
            $this->purgeField($fid, (array) ($all[$gid]['assign_to'] ?? []));
        }
        return true;
    }

    /** @param list<string> $postTypes */
    private function purgeField(string $fid, array $postTypes): void
    {
        if ($fid === '' || $postTypes === [] || isset($this->allFields()[$fid])) {
            return;   // still used by another group
        }
        global $wpdb;
        $in = implode(',', array_fill(0, count($postTypes), '%s'));
        $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s AND p.post_type IN ({$in})",
            array_merge([$fid], $postTypes)
        ));
        if (function_exists('wp_cache_flush_group') && wp_cache_supports('flush_group')) {
            wp_cache_flush_group('post_meta');
        } else {
            wp_cache_flush();
        }
    }
}
