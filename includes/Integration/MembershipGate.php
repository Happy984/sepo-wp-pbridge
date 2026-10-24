<?php
namespace SepoWB\Integration;

use SepoWB\Settings;
use SepoWB\TierStore;

defined('ABSPATH') || exit;

/**
 * Members-only content. A post is gated when it names a tier, or when it
 * names none and its post type follows the site-wide default tier.
 *
 * Post meta:
 *   _sp_require_membership   tier id; '' = follow the default; 'public' = never gated
 *   _sp_membership_message   optional text for the lock card
 *   _sp_membership_redirect  optional address to send visitors to instead
 *
 * Gated content is hidden from the page, excerpts, feeds, the REST API and
 * comments. Anyone who can edit the post always sees it.
 */
final class MembershipGate
{
    public const META_TIER     = '_sp_require_membership';
    public const META_MESSAGE  = '_sp_membership_message';
    public const META_REDIRECT = '_sp_membership_redirect';
    public const PUBLIC        = 'public';
    public const TYPES_OPTION  = 'sepo_wb_gate_types';

    private static ?TierStore $store = null;

    public static function hook(): void
    {
        add_filter('the_content', [__CLASS__, 'gateContent'], 8);
        add_filter('get_the_excerpt', [__CLASS__, 'gateExcerpt'], 99, 2);
        add_filter('the_content_feed', [__CLASS__, 'gateFeedContent'], 8);
        add_filter('the_excerpt_rss', [__CLASS__, 'gateFeedContent'], 8);
        add_action('rest_api_init', [__CLASS__, 'hookRest']);
        add_filter('comments_template', [__CLASS__, 'gateComments'], 99);
        add_filter('comments_open', [__CLASS__, 'gateCommentsOpen'], 99, 2);
        add_filter('comments_array', [__CLASS__, 'gateCommentsArray'], 99, 2);
        add_action('template_redirect', [__CLASS__, 'maybeRedirect']);
        // Block themes render comments with blocks that skip the classic filters.
        add_filter('render_block', [__CLASS__, 'gateCommentBlocks'], 10, 3);
    }

    public static function gateCommentBlocks($html, $block, $instance = null)
    {
        $name = is_array($block) ? (string) ($block['blockName'] ?? '') : '';
        if ($name !== 'core/comments' && $name !== 'core/post-comments-form' && $name !== 'core/comments-query-loop' && $name !== 'core/post-comments') {
            return $html;
        }
        $id = $instance instanceof \WP_Block && !empty($instance->context['postId']) ? (int) $instance->context['postId'] : (int) get_the_ID();
        return ($id <= 0 || self::passes($id)) ? $html : '';
    }

    public static function store(): TierStore
    {
        return self::$store ??= new TierStore();
    }

    /** Post types that follow the default tier. @return list<string> */
    public static function defaultTypes(): array
    {
        $t = get_option(self::TYPES_OPTION, ['post']);
        return is_array($t) ? array_values(array_map('strval', $t)) : ['post'];
    }

    /** The tier a post requires, or '' for public. */
    public static function tierFor(int $postId): string
    {
        if ($postId <= 0) {
            return '';
        }
        $own = sanitize_key((string) get_post_meta($postId, self::META_TIER, true));
        if ($own === self::PUBLIC) {
            return '';
        }
        if ($own !== '') {
            return $own;
        }
        $default = self::store()->defaultTier();
        if ($default !== '' && in_array(get_post_type($postId), self::defaultTypes(), true)) {
            return $default;
        }
        return '';
    }

    public static function passes(int $postId): bool
    {
        $tier = self::tierFor($postId);
        if ($tier === '') {
            return true;
        }
        if (current_user_can('edit_post', $postId)) {
            return true;
        }
        $uid = get_current_user_id();
        $ok = $uid > 0 && self::store()->hasLevel($uid, $tier);
        return (bool) apply_filters('sepo_wb_can_view', $ok, $postId, $tier, $uid);
    }

    // ------------------------------------------------------------ filters

    public static function gateContent($content)
    {
        $id = (int) get_the_ID();
        if ($id <= 0 || self::passes($id)) {
            return $content;
        }
        return self::renderLock($id);
    }

    public static function gateExcerpt($excerpt, $post = null)
    {
        $id = $post instanceof \WP_Post ? (int) $post->ID : (int) get_the_ID();
        if ($id <= 0 || self::passes($id)) {
            return $excerpt;
        }
        return sprintf(
            /* translators: %s: tier label */
            esc_html__('For %s members.', 'sepo-wp-bridge'),
            self::store()->label(self::tierFor($id))
        );
    }

    public static function gateFeedContent($content)
    {
        $id = (int) get_the_ID();
        return ($id <= 0 || self::passes($id)) ? $content : '';
    }

    public static function hookRest(): void
    {
        foreach (get_post_types(['show_in_rest' => true]) as $type) {
            add_filter('rest_prepare_' . $type, [__CLASS__, 'gateRest'], 8, 2);
        }
    }

    public static function gateRest($response, $post)
    {
        if (!$response instanceof \WP_REST_Response || !$post instanceof \WP_Post || self::passes((int) $post->ID)) {
            return $response;
        }
        $data = $response->get_data();
        if (is_array($data)) {
            foreach (['content', 'excerpt'] as $k) {
                if (isset($data[$k]) && is_array($data[$k])) {
                    $data[$k]['rendered'] = '';
                    if (isset($data[$k]['raw'])) {
                        $data[$k]['raw'] = '';
                    }
                    $data[$k]['protected'] = true;
                }
            }
            if (isset($data['meta']) && is_array($data['meta'])) {
                $data['meta'] = [];
            }
            $response->set_data($data);
        }
        return $response;
    }

    public static function gateComments($template)
    {
        $id = (int) get_the_ID();
        return ($id <= 0 || self::passes($id)) ? $template : __DIR__ . '/no-comments.php';
    }

    public static function gateCommentsOpen($open, $postId)
    {
        return ($open && !self::passes((int) $postId)) ? false : $open;
    }

    public static function gateCommentsArray($comments, $postId)
    {
        return self::passes((int) $postId) ? $comments : [];
    }

    /** A post with a redirect address sends locked-out visitors there. */
    public static function maybeRedirect(): void
    {
        if (!is_singular()) {
            return;
        }
        $id = (int) get_queried_object_id();
        if ($id <= 0 || self::passes($id)) {
            return;
        }
        $to = (string) get_post_meta($id, self::META_REDIRECT, true);
        if ($to !== '') {
            wp_safe_redirect(add_query_arg('redirect_to', rawurlencode((string) get_permalink($id)), $to));
            exit;
        }
    }

    private static function renderLock(int $postId): string
    {
        $tier    = self::tierFor($postId);
        $label   = self::store()->label($tier);
        $message = (string) get_post_meta($postId, self::META_MESSAGE, true);
        if ($message === '') {
            $message = sprintf(
                /* translators: %s: tier label */
                __('This content is for %s members and above.', 'sepo-wp-bridge'),
                $label
            );
        }

        $loggedIn = is_user_logged_in();
        $href  = $loggedIn ? Settings::joinUrl() : wp_login_url((string) get_permalink($postId));
        $cta   = $loggedIn
            /* translators: %s: tier label */
            ? sprintf(__('Become a %s member', 'sepo-wp-bridge'), $label)
            : __('Log in to continue', 'sepo-wp-bridge');

        $html = '<div class="sp-membership-lock" style="max-width:560px;margin:2rem auto;text-align:center;padding:2.25rem 1.5rem;border:1px dashed rgba(128,128,128,.4);border-radius:12px">'
            . '<div style="font-size:2rem;line-height:1;margin-bottom:.6rem" aria-hidden="true">&#128274;</div>'
            . '<h2 style="margin:.2rem 0 .5rem;font-size:1.3rem">'
            /* translators: %s: tier label */
            . esc_html(sprintf(__('%s members only', 'sepo-wp-bridge'), $label)) . '</h2>'
            . '<p style="opacity:.8;line-height:1.55;margin:0 0 1rem">' . esc_html($message) . '</p>'
            . '<p style="margin:0"><a class="sp-membership-cta wp-element-button" href="' . esc_url($href) . '">' . esc_html($cta) . '</a></p>';
        if (!$loggedIn && get_option('users_can_register')) {
            $html .= '<p style="margin:.75rem 0 0;font-size:.9em"><a href="' . esc_url(wp_registration_url()) . '">' . esc_html__('Create an account', 'sepo-wp-bridge') . '</a></p>';
        }
        $html .= '</div>';

        return (string) apply_filters('sepo_wb_lock_html', $html, $postId, $tier);
    }
}
