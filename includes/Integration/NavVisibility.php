<?php
namespace SepoWB\Integration;

use SepoWB\Settings;
use SepoWB\TierStore;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Menu + block visibility judged against the WordPress user.
 *
 * Reads `_sp_nav_visibility` meta on nav_menu_item posts, or `sp-*` classes
 * on any menu item or block. A smart link (#sp-login etc.) implies a `who`.
 * Rules cascade to children — a hidden parent takes its submenu with it.
 *
 * Blocks follow the same rules, set from the "Visibility" panel in the
 * block editor (stored as the spVisibility attribute) or with sp-* classes.
 */
final class NavVisibility
{
    private const META_KEY = '_sp_nav_visibility';

    public static function hook(): void
    {
        add_filter('wp_nav_menu_objects', [__CLASS__, 'filterItems'], 10, 1);
        add_filter('render_block', [__CLASS__, 'filterBlock'], 10, 2);
        add_filter('register_block_type_args', [__CLASS__, 'addBlockAttribute'], 10, 2);
        add_action('wp_nav_menu_item_custom_fields', [__CLASS__, 'renderItemFields'], 10, 2);
        add_action('wp_update_nav_menu_item', [__CLASS__, 'saveItemFields'], 10, 2);
        add_action('admin_head-nav-menus.php', static function (): void {
            echo '<style>.sepo-wb-navvis legend{font-size:13px}.sepo-wb-navvis p{margin:6px 0}</style>';
        });
    }

    // --------------------------------------------------------------- front end

    /** Every block accepts spVisibility, so dynamic blocks don't reject it. */
    public static function addBlockAttribute(array $args, string $name): array
    {
        $args['attributes'] = is_array($args['attributes'] ?? null) ? $args['attributes'] : [];
        if (!isset($args['attributes']['spVisibility'])) {
            $args['attributes']['spVisibility'] = ['type' => 'object'];
        }
        return $args;
    }

    public static function filterItems(array $items): array
    {
        if (is_admin()) {
            return $items;
        }

        $removed = [];
        $parents = [];
        $out     = [];

        foreach ($items as $key => $item) {
            $id    = (int) ($item->ID ?? 0);
            $url   = (string) ($item->url ?? '');
            $smart = self::smartToken($url);
            $rule  = self::ruleForItem($item);

            if ($smart !== null && !self::hasExplicitWho($item)) {
                if (in_array($smart, ['login', 'register'], true)) {
                    $rule['who'] = 'guests';
                } elseif (in_array($smart, ['logout', 'account'], true)) {
                    $rule['who'] = 'logged_in';
                }
            }

            $state = ($smart === 'register' && !get_option('users_can_register'))
                ? 'hide'
                : self::decide($rule);

            if ($state === 'hide') {
                $removed[$id] = true;
                $parents[$id] = true;
                continue;
            }

            if ($smart !== null || $state === 'lock') {
                $item = clone $item;
            }
            if ($smart !== null) {
                $item->url = self::smartUrl($smart);
            }
            if ($state === 'lock') {
                $parents[$id]  = true;
                $item->url     = self::lockUrl($url);
                $item->title   = '<span class="sp-nav-lock" aria-hidden="true">&#128274;</span> '
                    . (string) ($item->title ?? '');
            }

            $out[$key] = $item;
        }

        // Cascade to descendants. Repeat until stable.
        do {
            $changed = false;
            foreach ($out as $key => $item) {
                $parent = (int) ($item->menu_item_parent ?? 0);
                if ($parent !== 0 && isset($parents[$parent])) {
                    $removed[(int) ($item->ID ?? 0)] = true;
                    $parents[(int) ($item->ID ?? 0)] = true;
                    unset($out[$key]);
                    $changed = true;
                }
            }
        } while ($changed);

        return $out;
    }

    public static function filterBlock($html, $block)
    {
        if (!is_array($block) || is_admin() || (defined('REST_REQUEST') && REST_REQUEST && current_user_can('edit_posts'))) {
            return $html;
        }

        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        $className = (string) ($attrs['className'] ?? '');
        $blockAttr = is_array($attrs['spVisibility'] ?? null) ? $attrs['spVisibility'] : null;

        if ($blockAttr === null && !str_contains($className, 'sp-')) {
            return $html;
        }

        $fromAttr = self::normaliseRule($blockAttr ?? []);
        $fromCss  = self::ruleFromClasses($className);
        $rule     = self::mergeRules($fromAttr, $fromCss);
        $state    = self::decide($rule);

        if ($state === 'hide') {
            return '';
        }
        if ($state === 'lock') {
            $label = (new TierStore())->label($rule['tier']);
            return '<p class="sp-block-lock">&#128274; <a href="' . esc_url(self::lockUrl('')) . '">'
                /* translators: %s: tier label */
                . esc_html(sprintf(__('For %s members — sign in or join to see this.', 'sepo-wp-bridge'), $label))
                . '</a></p>';
        }
        return $html;
    }

    // --------------------------------------------------------------- helpers

    private static function decide(array $rule): string
    {
        $who      = $rule['who']    ?? 'everyone';
        $roles    = $rule['roles']  ?? [];
        $tier     = $rule['tier']   ?? '';
        $locked   = !empty($rule['locked']);
        $loggedIn = is_user_logged_in();

        if ($who === 'guests') {
            return $loggedIn ? 'hide' : 'show';
        }

        $admin = $loggedIn && current_user_can('manage_options');

        if ($roles !== []) {
            if (!$loggedIn) {
                return 'hide';
            }
            $user = wp_get_current_user();
            $have = $user && $user->exists() ? (array) $user->roles : [];
            if (!$admin && array_intersect($roles, $have) === []) {
                return 'hide';
            }
        }

        if ($tier === '') {
            return ($who === 'logged_in' && !$loggedIn) ? 'hide' : 'show';
        }

        $hasTier = false;
        if ($loggedIn) {
            if ($admin) {
                $hasTier = true;
            } else {
                try {
                    $hasTier = (new TierStore())->hasLevel((int) get_current_user_id(), $tier);
                } catch (Throwable $e) {
                    error_log('[sepo-wb] nav tier: ' . $e->getMessage());
                }
            }
        }

        return $hasTier ? 'show' : ($locked ? 'lock' : 'hide');
    }

    private static function ruleForItem(object $item): array
    {
        $classes = is_array($item->classes ?? null) ? implode(' ', $item->classes) : '';
        $meta    = get_post_meta((int) ($item->ID ?? 0), self::META_KEY, true);

        return self::mergeRules(
            self::normaliseRule(is_array($meta) ? $meta : []),
            self::ruleFromClasses($classes)
        );
    }

    private static function hasExplicitWho(object $item): bool
    {
        $meta = get_post_meta((int) ($item->ID ?? 0), self::META_KEY, true);
        if (is_array($meta) && in_array($meta['who'] ?? '', ['logged_in', 'guests'], true)) {
            return true;
        }
        $classes = is_array($item->classes ?? null) ? $item->classes : [];
        return (bool) array_intersect(['sp-logged-in', 'sp-guests'], array_map('strtolower', $classes));
    }

    private static function normaliseRule(array $raw): array
    {
        $who = (string) ($raw['who'] ?? 'everyone');
        if (!in_array($who, ['everyone', 'logged_in', 'guests'], true)) {
            $who = 'everyone';
        }
        $roles = [];
        foreach ((array) ($raw['roles'] ?? []) as $r) {
            $r = sanitize_key((string) $r);
            if ($r !== '') {
                $roles[$r === 'admin' ? 'administrator' : $r] = true;
            }
        }
        return [
            'who'    => $who,
            'roles'  => array_keys($roles),
            'tier'   => sanitize_key((string) ($raw['tier'] ?? '')),
            'locked' => filter_var($raw['locked'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private static function ruleFromClasses(string $classes): array
    {
        $rule = ['who' => 'everyone', 'roles' => [], 'tier' => '', 'locked' => false];
        if (!str_contains($classes, 'sp-')) {
            return self::normaliseRule($rule);
        }
        foreach (preg_split('/\s+/', strtolower(trim($classes))) ?: [] as $token) {
            if ($token === 'sp-logged-in') {
                $rule['who'] = 'logged_in';
            } elseif ($token === 'sp-guests') {
                $rule['who'] = 'guests';
            } elseif ($token === 'sp-locked') {
                $rule['locked'] = true;
            } elseif (str_starts_with($token, 'sp-role-') && strlen($token) > 8) {
                $rule['roles'][] = substr($token, 8);
            } elseif (str_starts_with($token, 'sp-tier-') && strlen($token) > 8) {
                $rule['tier'] = substr($token, 8);
            }
        }
        return self::normaliseRule($rule);
    }

    private static function mergeRules(array $primary, array $secondary): array
    {
        return [
            'who'    => $primary['who'] !== 'everyone' ? $primary['who'] : $secondary['who'],
            'roles'  => array_values(array_unique(array_merge($primary['roles'], $secondary['roles']))),
            'tier'   => $primary['tier'] !== '' ? $primary['tier'] : $secondary['tier'],
            'locked' => $primary['locked'] || $secondary['locked'],
        ];
    }

    private static function smartToken(string $url): ?string
    {
        $url = strtolower(trim($url));
        if (!str_starts_with($url, '#sp-') && !str_contains($url, '/#sp-')) {
            return null;
        }
        $fragment = substr($url, (int) strrpos($url, '#sp-') + 4);
        return in_array($fragment, ['login', 'register', 'logout', 'account', 'join'], true)
            ? $fragment
            : null;
    }

    private static function smartUrl(string $token): string
    {
        $here = self::currentUrl();
        return match ($token) {
            'login'    => wp_login_url($here),
            'register' => wp_registration_url(),
            'logout'   => wp_logout_url($here),
            'account'  => Settings::accountUrl(),
            'join'     => Settings::joinUrl(),
            default    => $here,
        };
    }

    private static function lockUrl(string $target): string
    {
        if (is_user_logged_in()) {
            return Settings::joinUrl();
        }
        $target = trim($target);
        if ($target === '' || str_starts_with($target, '#')) {
            $target = self::currentUrl();
        } elseif (str_starts_with($target, '/') && !str_starts_with($target, '//')) {
            $target = rtrim(home_url(), '/') . $target;
        }
        return wp_login_url($target);
    }

    private static function currentUrl(): string
    {
        $home = wp_parse_url(home_url());
        $base = ($home['scheme'] ?? 'https') . '://' . ($home['host'] ?? '')
            . (isset($home['port']) ? ':' . $home['port'] : '');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        return esc_url_raw($base . ($uri !== '' && $uri[0] === '/' ? $uri : '/'));
    }

    // --------------------------------------------------------------- admin UI

    public static function renderItemFields($itemId, $item = null): void
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return;
        }
        $stored = get_post_meta($itemId, self::META_KEY, true);
        $rule   = self::normaliseRule(is_array($stored) ? $stored : []);
        $base   = 'sp_nav_vis[' . $itemId . ']';
        $roles  = wp_roles()->get_names();

        echo '<fieldset class="sepo-wb-navvis" style="margin:8px 0;padding:8px 10px;border:1px solid #dcdcde;border-radius:4px;clear:both">';
        echo '<legend style="padding:0 4px;font-weight:600">' . esc_html__('Visibility', 'sepo-wp-bridge') . '</legend>';
        echo '<input type="hidden" name="sp_nav_vis_nonce[' . (int) $itemId . ']" value="'
            . esc_attr(wp_create_nonce('sp_nav_vis_' . $itemId)) . '">';

        echo '<p><label>' . esc_html__('Show to', 'sepo-wp-bridge') . '<br><select name="' . esc_attr($base) . '[who]">';
        foreach (['everyone' => __('Everyone', 'sepo-wp-bridge'), 'logged_in' => __('Signed-in users only', 'sepo-wp-bridge'), 'guests' => __('Signed-out visitors only', 'sepo-wp-bridge')] as $v => $l) {
            printf('<option value="%s"%s>%s</option>',
                esc_attr($v), selected($rule['who'], $v, false), esc_html($l));
        }
        echo '</select></label></p>';

        echo '<p><strong>' . esc_html__('Roles', 'sepo-wp-bridge') . '</strong> ' . esc_html__('(none ticked = any role)', 'sepo-wp-bridge') . '</p><p style="display:flex;flex-wrap:wrap;gap:4px 12px">';
        foreach ($roles as $slug => $name) {
            printf(
                '<label><input type="checkbox" name="%s[roles][]" value="%s"%s> %s</label>',
                esc_attr($base),
                esc_attr((string) $slug),
                checked(in_array($slug, $rule['roles'], true), true, false),
                esc_html(translate_user_role((string) $name))
            );
        }
        echo '</p>';

        $tiers = (new TierStore())->tiers();
        echo '<p><label>' . esc_html__('Members of tier', 'sepo-wp-bridge') . '<br><select class="widefat" name="' . esc_attr($base) . '[tier]">';
        echo '<option value="">' . esc_html__('Any (no tier needed)', 'sepo-wp-bridge') . '</option>';
        foreach ($tiers as $t) {
            printf('<option value="%s"%s>%s</option>', esc_attr($t['id']), selected($rule['tier'], $t['id'], false),
                /* translators: %s: tier label */
                esc_html(sprintf(__('%s and above', 'sepo-wp-bridge'), $t['label'])));
        }
        if ($rule['tier'] !== '' && !in_array($rule['tier'], array_column($tiers, 'id'), true)) {
            printf('<option value="%s" selected>%s</option>', esc_attr($rule['tier']), esc_html($rule['tier'] . ' (?)'));
        }
        echo '</select></label></p>';

        printf(
            '<p><label><input type="checkbox" name="%s[locked]" value="1"%s> %s</label></p>',
            esc_attr($base),
            checked($rule['locked'], true, false),
            esc_html__('Show it with a lock to people without the tier (instead of hiding it)', 'sepo-wp-bridge')
        );

        echo '</fieldset>';
    }

    public static function saveItemFields($menuId, $itemId = 0): void
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0 || !isset($_POST['sp_nav_vis_nonce'][$itemId])) {
            return;
        }
        $nonce = sanitize_text_field(wp_unslash((string) $_POST['sp_nav_vis_nonce'][$itemId]));
        if (!wp_verify_nonce($nonce, 'sp_nav_vis_' . $itemId)) {
            return;
        }
        if (!current_user_can('edit_theme_options')) {
            return;
        }

        $posted = isset($_POST['sp_nav_vis'][$itemId]) && is_array($_POST['sp_nav_vis'][$itemId])
            ? wp_unslash($_POST['sp_nav_vis'][$itemId])
            : [];

        $rule = self::normaliseRule($posted);

        $existing = array_keys(wp_roles()->get_names());
        $rule['roles'] = array_values(array_intersect($rule['roles'], $existing));

        if ($rule['who'] === 'everyone' && $rule['roles'] === [] && $rule['tier'] === '' && !$rule['locked']) {
            delete_post_meta($itemId, self::META_KEY);
            return;
        }
        update_post_meta($itemId, self::META_KEY, $rule);
    }
}