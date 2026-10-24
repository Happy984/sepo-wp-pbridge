<?php
namespace SepoWB;

use SepoWB\Schema\SchemaManager;

defined('ABSPATH') || exit;

final class Plugin
{
    public const DB_VERSION_OPTION = 'sepo_wb_version';

    private static ?Plugin $instance = null;

    public FeatureRegistry $registry;
    public FeatureState    $state;
    public SchemaManager   $schema;

    // ---------------------------------------------------------------- lifecycle

    public static function activate(): void
    {
        add_option(FeatureRegistry::OPTION, []);
        add_option(FeatureState::OPTION, []);
        add_option(SchemaManager::OPT_CPTS, []);
        add_option(SchemaManager::OPT_TAX, []);
        add_option(SchemaManager::OPT_META, []);
        add_option(TierStore::TIERS_OPTION, [
            ['id' => 'free',   'label' => 'Free',   'rank' => 0],
            ['id' => 'silver', 'label' => 'Silver', 'rank' => 10],
            ['id' => 'gold',   'label' => 'Gold',   'rank' => 20],
        ]);
        add_option(TierStore::DEFAULT_OPTION, '');
        add_option(Settings::OPTION, Settings::defaults());
        self::upgrade();
        // The post types and taxonomies saved earlier get their addresses on the next request.
        update_option(SchemaManager::OPT_FLUSH, 1);
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /** Runs once per version change: adds new default features, moves old settings. */
    public static function upgrade(): void
    {
        (new FeatureRegistry())->mergeNewDefaults();

        // 1.0 kept handler settings in a separate option.
        $params = get_option('sepo_wb_handler_params', null);
        if (is_array($params)) {
            $rows = get_option(FeatureRegistry::OPTION, []);
            foreach ($params as $key => $args) {
                if (isset($rows[$key]) && is_array($args)) {
                    $rows[$key]['params'] = $args;
                }
            }
            update_option(FeatureRegistry::OPTION, $rows);
            delete_option('sepo_wb_handler_params');
        }
        update_option(self::DB_VERSION_OPTION, SEPO_WB_VERSION);
    }

    public static function boot(): void
    {
        if (self::$instance !== null) {
            return;
        }
        if (get_option(self::DB_VERSION_OPTION) !== SEPO_WB_VERSION) {
            // On init, so the default feature labels can be translated.
            add_action('init', [self::class, 'upgrade'], 0);
        }
        self::$instance = new self();
        self::$instance->wire();
    }

    public static function instance(): ?Plugin
    {
        return self::$instance;
    }

    // ---------------------------------------------------------------- wiring

    private function wire(): void
    {
        $this->registry = new FeatureRegistry();
        $this->state    = new FeatureState();
        $this->schema   = new SchemaManager();

        // Features run now, on plugins_loaded, so handlers can still reach
        // hooks that fire before init (after_setup_theme, set_current_user…).
        $this->applyFeatures();

        $this->schema->hook();

        Integration\NavVisibility::hook();
        Integration\MembershipGate::hook();
        Integration\Blocks::hook();

        if (is_admin()) {
            Integration\MembershipAdmin::hook();
            (new Admin\AdminController($this->registry, $this->state, $this->schema))->hook();
        }

        add_action('rest_api_init', function (): void {
            (new Rest\RestController($this->registry, $this->state, $this->schema))->registerRoutes();
        });

        add_action('init', static function (): void {
            load_plugin_textdomain('sepo-wp-bridge', false, dirname(SEPO_WB_BASENAME) . '/languages');
        });
    }

    // ---------------------------------------------------------------- feature runner

    /**
     * Run every enabled feature's handler once per request. Each one is
     * isolated: a failure is logged and skipped.
     *
     * Define SEPO_WB_SAFE_MODE as true in wp-config.php to skip them all
     * (for example if a feature locked you out).
     */
    public function applyFeatures(): void
    {
        static $done = false;
        if ($done || (defined('SEPO_WB_SAFE_MODE') && SEPO_WB_SAFE_MODE)) {
            return;
        }
        $done = true;

        $toggles  = $this->state->all();
        $class    = Features\Handlers::className();
        $handlers = new $class();
        $catalog  = Features\Handlers::catalog();

        foreach ($this->registry->all() as $key => $row) {
            $enabled = array_key_exists($key, $toggles) ? $toggles[$key] : $row['default'];
            if (!$enabled || !isset($catalog[$row['handler']])) {
                continue;
            }
            try {
                $args = Features\Handlers::sanitizeParams($row['handler'], $row['params']);
                call_user_func_array([$handlers, $row['handler']], $args);   // string keys = named arguments
            } catch (\Throwable $e) {
                error_log(sprintf('[sepo-wb] feature "%s" failed: %s', $key, $e->getMessage()));
            }
        }
    }
}
