<?php
namespace SepoWB\Integration;

use SepoWB\Schema\SchemaManager;
use SepoWB\TierStore;

defined('ABSPATH') || exit;

/**
 * The "Post field" block and [sepo_field] shortcode, which print one value
 * of the current post: title, date, author, link, or a field from a
 * meta-field group. Also loads the editor script that adds the Visibility
 * panel to every block.
 */
final class Blocks
{
    private const CORE = ['post_title', 'post_date', 'post_modified', 'post_author', 'permalink', 'post_excerpt'];

    public static function hook(): void
    {
        add_action('init', [__CLASS__, 'register']);
        add_action('enqueue_block_editor_assets', [__CLASS__, 'editorAssets']);
    }

    public static function register(): void
    {
        register_block_type('sepo/dynamic-field', [
            'api_version'     => 2,
            'title'           => __('Post field', 'sepo-wp-bridge'),
            'category'        => 'theme',
            'render_callback' => [__CLASS__, 'render'],
            'attributes'      => [
                'field'     => ['type' => 'string', 'default' => 'post_title'],
                'label'     => ['type' => 'string', 'default' => ''],
                'className' => ['type' => 'string', 'default' => ''],
            ],
            'uses_context'    => ['postId', 'postType'],
        ]);
        add_shortcode('sepo_field', static fn($atts): string => self::render(shortcode_atts(['field' => '', 'label' => ''], (array) $atts, 'sepo_field')));
    }

    public static function editorAssets(): void
    {
        wp_enqueue_script(
            'sepo-wb-editor',
            SEPO_WB_URL . 'assets/js/editor.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-hooks', 'wp-compose', 'wp-server-side-render', 'wp-i18n'],
            SEPO_WB_VERSION,
            true
        );
        $fields = [];
        foreach ((new SchemaManager())->allFields() as $id => $f) {
            $fields[] = ['value' => $id, 'label' => $id];
        }
        wp_localize_script('sepo-wb-editor', 'SepoWBEditor', [
            'fields' => $fields,
            'tiers'  => array_map(static fn($t) => ['value' => $t['id'], 'label' => $t['label']], (new TierStore())->tiers()),
            'roles'  => array_map(static fn($slug, $name) => ['value' => $slug, 'label' => translate_user_role($name)], array_keys(wp_roles()->get_names()), wp_roles()->get_names()),
        ]);
        wp_set_script_translations('sepo-wb-editor', 'sepo-wp-bridge');
    }

    /**
     * @param array<string,mixed> $attrs
     */
    public static function render($attrs, $content = '', $block = null): string
    {
        $attrs = is_array($attrs) ? $attrs : [];
        $field = sanitize_key((string) ($attrs['field'] ?? ''));
        $id = $block instanceof \WP_Block && !empty($block->context['postId']) ? (int) $block->context['postId'] : (int) get_the_ID();
        if ($field === '' || $id <= 0 || !MembershipGate::passes($id)) {
            return '';
        }

        $value = self::value($id, $field);
        if ($value === '') {
            return '';
        }
        $label = sanitize_text_field((string) ($attrs['label'] ?? ''));
        $class = 'sepo-field sepo-field--' . $field . (!empty($attrs['className']) ? ' ' . sanitize_html_class((string) $attrs['className']) : '');
        return '<div class="' . esc_attr($class) . '">'
            . ($label !== '' ? '<span class="sepo-field-label">' . esc_html($label) . '</span> ' : '')
            . '<span class="sepo-field-value">' . $value . '</span></div>';
    }

    /** The field's value as escaped HTML, or ''. */
    private static function value(int $id, string $field): string
    {
        switch ($field) {
            case 'post_title':
                return esc_html(get_the_title($id));
            case 'post_date':
                return esc_html((string) get_the_date('', $id));
            case 'post_modified':
                return esc_html((string) get_the_modified_date('', $id));
            case 'post_author':
                return esc_html((string) get_the_author_meta('display_name', (int) get_post_field('post_author', $id)));
            case 'permalink':
                $url = (string) get_permalink($id);
                return '<a href="' . esc_url($url) . '">' . esc_html($url) . '</a>';
            case 'post_excerpt':
                return esc_html(get_the_excerpt($id));
        }

        // Only fields declared in a meta-field group, so the block can't print private data.
        $fields = (new SchemaManager())->allFields();
        $allowed = isset($fields[$field]) && !is_protected_meta($field, 'post');
        if (!apply_filters('sepo_wb_field_allowed', $allowed, $field, $id)) {
            return '';
        }
        $raw = (string) get_post_meta($id, $field, true);
        if ($raw === '') {
            return '';
        }
        $type = $fields[$field]['type'] ?? 'text';
        switch ($type) {
            case 'checkbox':
                return $raw === '1' ? esc_html__('Yes', 'sepo-wp-bridge') : esc_html__('No', 'sepo-wp-bridge');
            case 'url':
                return '<a href="' . esc_url($raw) . '">' . esc_html($raw) . '</a>';
            case 'email':
                return '<a href="mailto:' . esc_attr(antispambot($raw)) . '">' . esc_html(antispambot($raw)) . '</a>';
            case 'date':
                $ts = strtotime($raw);
                return esc_html($ts ? wp_date((string) get_option('date_format'), $ts) : $raw);
            case 'textarea':
                return nl2br(esc_html($raw));
            case 'select':
                foreach ((new SchemaManager())->allMetaGroups() as $g) {
                    foreach ((array) ($g['fields'] ?? []) as $f) {
                        if (($f['id'] ?? '') === $field && isset($f['options'][$raw])) {
                            return esc_html((string) $f['options'][$raw]);
                        }
                    }
                }
                return esc_html($raw);
            default:
                return esc_html($raw);
        }
    }
}
