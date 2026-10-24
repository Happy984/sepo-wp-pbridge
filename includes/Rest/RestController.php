<?php
namespace SepoWB\Rest;

use InvalidArgumentException;
use SepoWB\FeatureRegistry;
use SepoWB\FeatureState;
use SepoWB\Features\Handlers;
use SepoWB\Integration\MembershipGate;
use SepoWB\Schema\SchemaManager;
use SepoWB\Settings;
use SepoWB\TierStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * The admin panel's API, under /wp-json/sepo-wb/v1. Every route needs
 * manage_options and the wp_rest nonce.
 *
 * Success: { "status": "success", "data": { ... } }
 * Failure: a WP_Error with a readable message.
 */
final class RestController
{
    public const NS = 'sepo-wb/v1';

    private FeatureRegistry $registry;
    private FeatureState $state;
    private SchemaManager $schema;

    public function __construct(FeatureRegistry $registry, FeatureState $state, SchemaManager $schema)
    {
        $this->registry = $registry;
        $this->state    = $state;
        $this->schema   = $schema;
    }

    public function registerRoutes(): void
    {
        $routes = [
            ['GET',  '/features',              'features'],
            ['POST', '/features/toggle',       'featureToggle'],
            ['POST', '/features/save',         'featureSave'],
            ['POST', '/features/delete',       'featureDelete'],
            ['POST', '/features/restore',      'featureRestore'],
            ['GET',  '/cpts',                  'cpts'],
            ['POST', '/cpts/save',             'cptSave'],
            ['POST', '/cpts/delete',           'cptDelete'],
            ['GET',  '/taxonomies',            'taxonomies'],
            ['POST', '/taxonomies/save',       'taxonomySave'],
            ['POST', '/taxonomies/delete',     'taxonomyDelete'],
            ['GET',  '/metagroups',            'metaGroups'],
            ['POST', '/metagroups/save',       'metaGroupSave'],
            ['POST', '/metagroups/delete',     'metaGroupDelete'],
            ['POST', '/metagroups/field/delete', 'metaFieldDelete'],
            ['GET',  '/membership',            'membership'],
            ['POST', '/membership/save',       'membershipSave'],
        ];
        foreach ($routes as [$method, $path, $cb]) {
            register_rest_route(self::NS, $path, [
                'methods'             => $method,
                'callback'            => $this->wrap($cb),
                'permission_callback' => static fn() => current_user_can('manage_options'),
            ]);
        }
    }

    /** Turns thrown InvalidArgumentException into a 422 with its message. */
    private function wrap(string $method): callable
    {
        return function (WP_REST_Request $req) use ($method) {
            try {
                return $this->{$method}($req);
            } catch (InvalidArgumentException $e) {
                return new WP_Error('sepo_wb_invalid', $e->getMessage(), ['status' => 422]);
            } catch (\Throwable $e) {
                error_log('[sepo-wb] ' . $method . ': ' . $e->getMessage());
                return new WP_Error('sepo_wb_error', __('Something went wrong. Nothing was changed.', 'sepo-wp-bridge'), ['status' => 500]);
            }
        };
    }

    /** @param array<string,mixed> $data */
    private static function ok(array $data = []): WP_REST_Response
    {
        return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
    }

    private static function notFound(string $what): WP_Error
    {
        /* translators: %s: item */
        return new WP_Error('sepo_wb_not_found', sprintf(__('%s not found.', 'sepo-wp-bridge'), $what), ['status' => 404]);
    }

    /** Post types a taxonomy or field group can be attached to. @return list<array{value:string,label:string}> */
    private static function postTypes(): array
    {
        $out = [];
        foreach (get_post_types(['show_ui' => true], 'objects') as $pt) {
            if (in_array($pt->name, ['wp_block', 'wp_navigation', 'wp_template', 'wp_template_part'], true)) {
                continue;
            }
            $out[] = ['value' => $pt->name, 'label' => (string) $pt->labels->singular_name];
        }
        return $out;
    }

    // ============================================================ features

    public function features(): WP_REST_Response
    {
        $toggles = $this->state->all();
        $rows = [];
        foreach ($this->registry->all() as $key => $r) {
            $rows[] = [
                'key'         => $key,
                'label'       => $r['label'],
                'category'    => $r['category'],
                'handler'     => $r['handler'],
                'description' => $r['description'],
                'default'     => $r['default'],
                'enabled'     => array_key_exists($key, $toggles) ? $toggles[$key] : $r['default'],
                'params'      => (object) $r['params'],
                'builtin'     => isset(FeatureRegistry::defaults()[$key]),
                'missing'     => !isset(Handlers::catalog()[$r['handler']]),
            ];
        }
        return self::ok([
            'features'   => $rows,
            'handlers'   => Handlers::catalog(),
            'categories' => FeatureRegistry::CATEGORIES,
        ]);
    }

    public function featureToggle(WP_REST_Request $req)
    {
        $key = sanitize_key((string) $req->get_param('key'));
        if ($this->registry->get($key) === null) {
            return self::notFound(__('Feature', 'sepo-wp-bridge'));
        }
        $this->state->set($key, filter_var($req->get_param('enabled'), FILTER_VALIDATE_BOOLEAN));
        return self::ok(['key' => $key, 'enabled' => $this->state->isEnabled($key)]);
    }

    public function featureSave(WP_REST_Request $req): WP_REST_Response
    {
        $p = (array) $req->get_json_params();
        $this->registry->save((string) ($p['key'] ?? ''), $p);
        return self::ok();
    }

    public function featureDelete(WP_REST_Request $req)
    {
        $key = sanitize_key((string) $req->get_param('key'));
        if (!$this->registry->delete($key)) {
            return self::notFound(__('Feature', 'sepo-wp-bridge'));
        }
        $this->state->forget($key);
        return self::ok();
    }

    public function featureRestore(): WP_REST_Response
    {
        $this->registry->restoreDefaults();
        return self::ok();
    }

    // ============================================================ post types

    public function cpts(): WP_REST_Response
    {
        $rows = [];
        foreach ($this->schema->allCpts() as $slug => $c) {
            $rows[] = array_merge(['ID' => (string) $slug, 'count' => $this->schema->countPosts((string) $slug)], (array) $c);
        }
        return self::ok(['cpts' => $rows, 'supports' => SchemaManager::SUPPORTS]);
    }

    public function cptSave(WP_REST_Request $req): WP_REST_Response
    {
        $p = (array) $req->get_json_params();
        $this->schema->saveCpt((string) ($p['post_type'] ?? ''), $p);
        return self::ok();
    }

    public function cptDelete(WP_REST_Request $req)
    {
        if (!$this->schema->deleteCpt(sanitize_key((string) $req->get_param('post_type')))) {
            return self::notFound(__('Post type', 'sepo-wp-bridge'));
        }
        return self::ok();
    }

    // ============================================================ taxonomies

    public function taxonomies(): WP_REST_Response
    {
        $rows = [];
        foreach ($this->schema->allTaxonomies() as $slug => $t) {
            $rows[] = array_merge(['ID' => (string) $slug, 'count' => taxonomy_exists((string) $slug) ? (int) wp_count_terms(['taxonomy' => (string) $slug, 'hide_empty' => false]) : 0], (array) $t);
        }
        return self::ok(['taxonomies' => $rows, 'post_types' => self::postTypes()]);
    }

    public function taxonomySave(WP_REST_Request $req): WP_REST_Response
    {
        $p = (array) $req->get_json_params();
        $this->schema->saveTaxonomy((string) ($p['taxonomy'] ?? ''), $p);
        return self::ok();
    }

    public function taxonomyDelete(WP_REST_Request $req)
    {
        if (!$this->schema->deleteTaxonomy(sanitize_key((string) $req->get_param('taxonomy')))) {
            return self::notFound(__('Taxonomy', 'sepo-wp-bridge'));
        }
        return self::ok();
    }

    // ============================================================ meta groups

    public function metaGroups(): WP_REST_Response
    {
        $rows = [];
        foreach ($this->schema->allMetaGroups() as $id => $g) {
            $rows[] = array_merge(['ID' => (string) $id], (array) $g);
        }
        return self::ok(['groups' => $rows, 'post_types' => self::postTypes(), 'types' => SchemaManager::FIELD_TYPES]);
    }

    public function metaGroupSave(WP_REST_Request $req): WP_REST_Response
    {
        $p = (array) $req->get_json_params();
        $this->schema->saveMetaGroup((string) ($p['id'] ?? ''), $p);
        return self::ok();
    }

    public function metaGroupDelete(WP_REST_Request $req)
    {
        $ok = $this->schema->deleteMetaGroup(sanitize_key((string) $req->get_param('id')), filter_var($req->get_param('purge'), FILTER_VALIDATE_BOOLEAN));
        return $ok ? self::ok() : self::notFound(__('Group', 'sepo-wp-bridge'));
    }

    public function metaFieldDelete(WP_REST_Request $req)
    {
        $ok = $this->schema->deleteFieldFromGroup(
            sanitize_key((string) $req->get_param('group_id')),
            sanitize_key((string) $req->get_param('field_id')),
            filter_var($req->get_param('purge'), FILTER_VALIDATE_BOOLEAN)
        );
        return $ok ? self::ok() : self::notFound(__('Field', 'sepo-wp-bridge'));
    }

    // ============================================================ membership

    public function membership(): WP_REST_Response
    {
        $store = new TierStore();
        $counts = [];
        global $wpdb;
        foreach ($wpdb->get_results($wpdb->prepare("SELECT meta_value v, COUNT(*) n FROM {$wpdb->usermeta} WHERE meta_key = %s GROUP BY meta_value", TierStore::USER_META), ARRAY_A) ?: [] as $r) {
            $counts[(string) $r['v']] = (int) $r['n'];
        }
        $tiers = array_map(static fn($t) => $t + ['members' => $counts[$t['id']] ?? 0], $store->tiers());
        $public = [];
        foreach (get_post_types(['public' => true], 'objects') as $pt) {
            if ($pt->name !== 'attachment') {
                $public[] = ['value' => $pt->name, 'label' => (string) $pt->labels->name];
            }
        }
        $s = Settings::all();
        return self::ok([
            'tiers'        => $tiers,
            'default_tier' => $store->defaultTier(),
            'gate_types'   => MembershipGate::defaultTypes(),
            'post_types'   => $public,
            'join_url'     => $s['join_url'],
            'account_url'  => $s['account_url'],
            'delete_on_uninstall' => (bool) $s['delete_on_uninstall'],
        ]);
    }

    public function membershipSave(WP_REST_Request $req): WP_REST_Response
    {
        $p = (array) $req->get_json_params();
        $store = new TierStore();
        if (isset($p['tiers']) && is_array($p['tiers'])) {
            $store->save($p['tiers']);
        }
        $store->setDefaultTier((string) ($p['default_tier'] ?? ''));
        $types = array_values(array_filter(array_map('sanitize_key', (array) ($p['gate_types'] ?? [])), 'post_type_exists'));
        update_option(MembershipGate::TYPES_OPTION, $types);
        update_option(Settings::OPTION, Settings::sanitize($p));
        return $this->membership();
    }
}
