<?php
namespace SepoWB\Admin;

use SepoWB\FeatureRegistry;
use SepoWB\FeatureState;
use SepoWB\Schema\SchemaManager;

defined('ABSPATH') || exit;

final class AdminController
{
    public const SLUG = 'sepo-wp-bridge';

    private FeatureRegistry $registry;
    private FeatureState    $state;
    private SchemaManager   $schema;
    private string          $hookSuffix = '';

    public function __construct(FeatureRegistry $r, FeatureState $s, SchemaManager $schema)
    {
        $this->registry = $r;
        $this->state    = $s;
        $this->schema   = $schema;
    }

    public function hook(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('plugin_action_links_' . SEPO_WB_BASENAME, static function (array $links): array {
            array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">' . esc_html__('Settings', 'sepo-wp-bridge') . '</a>');
            return $links;
        });
        if (defined('SEPO_WB_SAFE_MODE') && SEPO_WB_SAFE_MODE) {
            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-warning"><p>' . esc_html__('Sepo WordPress Bridge is in safe mode (SEPO_WB_SAFE_MODE): feature toggles are not being applied.', 'sepo-wp-bridge') . '</p></div>';
            });
        }
    }

    public function registerMenu(): void
    {
        $this->hookSuffix = (string) add_menu_page(
            __('WordPress Configuration', 'sepo-wp-bridge'),
            __('WP Bridge', 'sepo-wp-bridge'),
            'manage_options',
            self::SLUG,
            [$this, 'render'],
            'dashicons-admin-generic',
            80
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== $this->hookSuffix) {
            return;
        }
        wp_enqueue_style('sepo-wb-admin', SEPO_WB_URL . 'assets/css/admin.css', [], self::assetVersion('assets/css/admin.css'));
        wp_enqueue_script('sepo-wb-admin', SEPO_WB_URL . 'assets/js/admin.js', ['wp-i18n'], self::assetVersion('assets/js/admin.js'), true);
        wp_set_script_translations('sepo-wb-admin', 'sepo-wp-bridge');
        wp_enqueue_style('sepo-wb-cookies-admin', SEPO_WB_URL . 'assets/css/cookies-admin.css', ['sepo-wb-admin'], self::assetVersion('assets/css/cookies-admin.css'));
        wp_enqueue_script('sepo-wb-cookies-admin', SEPO_WB_URL . 'assets/js/cookies-admin.js', ['sepo-wb-admin'], self::assetVersion('assets/js/cookies-admin.js'), true);
        wp_set_script_translations('sepo-wb-cookies-admin', 'sepo-wp-bridge');
        wp_enqueue_style('sepo-wb-signin-admin', SEPO_WB_URL . 'assets/css/signin-admin.css', ['sepo-wb-admin'], self::assetVersion('assets/css/signin-admin.css'));
        wp_enqueue_script('sepo-wb-signin-admin', SEPO_WB_URL . 'assets/js/signin-admin.js', ['sepo-wb-admin'], self::assetVersion('assets/js/signin-admin.js'), true);
        wp_set_script_translations('sepo-wb-signin-admin', 'sepo-wp-bridge');
        wp_localize_script('sepo-wb-admin', 'SepoWB', [
            'api'       => esc_url_raw(rest_url('sepo-wb/v1')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'adminUrl'  => esc_url_raw(admin_url()),
            'canManage' => current_user_can('manage_options'),
        ]);
    }

    /** The plugin version plus the file's change time, so browsers fetch an edited file at once. */
    private static function assetVersion(string $rel): string
    {
        $file = SEPO_WB_DIR . $rel;
        return SEPO_WB_VERSION . (is_file($file) ? '.' . filemtime($file) : '');
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'sepo-wp-bridge'));
        }
        View::page();
    }
}
