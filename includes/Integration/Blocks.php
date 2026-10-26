<?php
namespace SepoWB\Integration;

use SepoWB\TierStore;

defined('ABSPATH') || exit;

/**
 * The "Post field" block and [sepo_field] shortcode, which print one value
 * of the current post (title, date, author, link, or a field from a
 * meta-field group), and the "Field group" block, which prints a whole
 * group. Also loads the editor script that adds the Visibility panel to
 * every block.
 */
final class Blocks
{
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
            'style_handles'   => ['sepo-wb-fields'],
        ]);
        register_block_type('sepo/field-group', [
            'api_version'     => 2,
            'title'           => __('Field group', 'sepo-wp-bridge'),
            'category'        => 'theme',
            'render_callback' => [__CLASS__, 'renderGroup'],
            'attributes'      => [
                'group'     => ['type' => 'string', 'default' => ''],
                'style'     => ['type' => 'string', 'default' => ''],
                'showTitle' => ['type' => 'string', 'default' => 'group'],
                'showEmpty' => ['type' => 'boolean', 'default' => false],
                'className' => ['type' => 'string', 'default' => ''],
            ],
            'uses_context'    => ['postId', 'postType'],
            'style_handles'   => ['sepo-wb-fields'],
        ]);
        add_shortcode('sepo_field', static fn($atts): string => self::render(shortcode_atts(['field' => '', 'label' => ''], (array) $atts, 'sepo_field')));
    }

    public static function editorAssets(): void
    {
        wp_enqueue_script(
            'sepo-wb-editor',
            SEPO_WB_URL . 'assets/js/editor.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-hooks', 'wp-compose', 'wp-server-side-render', 'wp-i18n'],
            SEPO_WB_VERSION . '.' . (int) @filemtime(SEPO_WB_DIR . 'assets/js/editor.js'),
            true
        );
        $fields = [];
        foreach (FieldDisplay::fieldChoices() as $id => $label) {
            if (!in_array($id, FieldDisplay::coreFields(), true)) {
                $fields[] = ['value' => $id, 'label' => $label];
            }
        }
        $groups = [];
        foreach (FieldDisplay::groupChoices() as $id => $label) {
            $groups[] = ['value' => $id, 'label' => $label];
        }
        wp_localize_script('sepo-wb-editor', 'SepoWBEditor', [
            'fields' => $fields,
            'groups' => $groups,
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
        $id = self::postId($block);
        $class = !empty($attrs['className']) ? sanitize_html_class((string) $attrs['className']) : '';
        return FieldDisplay::renderField($id, sanitize_key((string) ($attrs['field'] ?? '')), sanitize_text_field((string) ($attrs['label'] ?? '')), $class);
    }

    /**
     * @param array<string,mixed> $attrs
     */
    public static function renderGroup($attrs, $content = '', $block = null): string
    {
        $attrs = is_array($attrs) ? $attrs : [];
        $args = ['style' => (string) ($attrs['style'] ?? ''), 'empty' => !empty($attrs['showEmpty'])];
        if (($attrs['showTitle'] ?? 'group') !== 'group') {
            $args['title'] = $attrs['showTitle'] === 'yes';
        }
        if (!empty($attrs['className'])) {
            $args['class'] = sanitize_html_class((string) $attrs['className']);
        }
        $postId = self::postId($block);
        $gid = sanitize_key((string) ($attrs['group'] ?? ''));
        if ($gid !== '') {
            return FieldDisplay::renderGroup($gid, $postId, $args);
        }
        $out = '';
        foreach (array_keys(FieldDisplay::groupsFor((string) get_post_type($postId))) as $g) {
            $out .= FieldDisplay::renderGroup($g, $postId, $args);
        }
        return $out;
    }

    /** The post a block shows: its Query Loop post, the post being previewed in the editor, or the current post. */
    private static function postId($block): int
    {
        if ($block instanceof \WP_Block && !empty($block->context['postId'])) {
            return (int) $block->context['postId'];
        }
        // phpcs:ignore WordPress.Security.NonceVerification -- the editor preview passes the post being edited.
        if (defined('REST_REQUEST') && REST_REQUEST && isset($_GET['post_id']) && current_user_can('edit_post', absint($_GET['post_id']))) {
            return absint($_GET['post_id']);
        }
        return (int) get_the_ID();
    }
}
