<?php
namespace SepoWB\Integration;

use WP_Post;
use WP_User;

defined('ABSPATH') || exit;

/**
 * Editing screens for membership: a "Members only" box on posts, a tier
 * picker on user profiles, and a Tier column on the Users list.
 */
final class MembershipAdmin
{
    public static function hook(): void
    {
        add_action('add_meta_boxes', [__CLASS__, 'addBox']);
        add_action('save_post', [__CLASS__, 'saveBox'], 10, 2);

        add_action('show_user_profile', [__CLASS__, 'userField']);
        add_action('edit_user_profile', [__CLASS__, 'userField']);
        add_action('personal_options_update', [__CLASS__, 'saveUserField']);
        add_action('edit_user_profile_update', [__CLASS__, 'saveUserField']);

        add_filter('manage_users_columns', [__CLASS__, 'userColumn']);
        add_filter('manage_users_custom_column', [__CLASS__, 'userColumnValue'], 10, 3);
    }

    public static function addBox(): void
    {
        if (MembershipGate::store()->tiers() === []) {
            return;
        }
        $types = get_post_types(['public' => true]);
        unset($types['attachment']);
        foreach ($types as $type) {
            add_meta_box('sepo_wb_membership', __('Members only', 'sepo-wp-bridge'), [__CLASS__, 'renderBox'], $type, 'side', 'default');
        }
    }

    public static function renderBox(WP_Post $post): void
    {
        $store   = MembershipGate::store();
        $tier    = sanitize_key((string) get_post_meta($post->ID, MembershipGate::META_TIER, true));
        $message = (string) get_post_meta($post->ID, MembershipGate::META_MESSAGE, true);
        $to      = (string) get_post_meta($post->ID, MembershipGate::META_REDIRECT, true);
        $default = $store->defaultTier();
        $follows = $default !== '' && in_array($post->post_type, MembershipGate::defaultTypes(), true);

        wp_nonce_field('sepo_wb_membership_' . $post->ID, 'sepo_wb_membership_nonce');
        ?>
        <p>
            <label for="sepo-wb-tier" style="display:block;font-weight:600;margin-bottom:3px"><?php esc_html_e('Who can read this', 'sepo-wp-bridge'); ?></label>
            <select id="sepo-wb-tier" name="sepo_wb_tier" class="widefat">
                <option value="" <?php selected($tier, ''); ?>>
                    <?php
                    echo esc_html($follows
                        /* translators: %s: tier label */
                        ? sprintf(__('Site default (%s members)', 'sepo-wp-bridge'), $store->label($default))
                        : __('Everyone (site default)', 'sepo-wp-bridge'));
                    ?>
                </option>
                <?php if ($follows) : ?>
                    <option value="<?php echo esc_attr(MembershipGate::PUBLIC); ?>" <?php selected($tier, MembershipGate::PUBLIC); ?>><?php esc_html_e('Everyone', 'sepo-wp-bridge'); ?></option>
                <?php endif; ?>
                <?php foreach ($store->tiers() as $t) : ?>
                    <option value="<?php echo esc_attr($t['id']); ?>" <?php selected($tier, $t['id']); ?>>
                        <?php
                        /* translators: %s: tier label */
                        echo esc_html(sprintf(__('%s members and above', 'sepo-wp-bridge'), $t['label']));
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="sepo-wb-msg" style="display:block;font-weight:600;margin-bottom:3px"><?php esc_html_e('Message for locked-out readers', 'sepo-wp-bridge'); ?></label>
            <textarea id="sepo-wb-msg" name="sepo_wb_message" class="widefat" rows="3" placeholder="<?php esc_attr_e('Optional', 'sepo-wp-bridge'); ?>"><?php echo esc_textarea($message); ?></textarea>
        </p>
        <p>
            <label for="sepo-wb-to" style="display:block;font-weight:600;margin-bottom:3px"><?php esc_html_e('Or send them to', 'sepo-wp-bridge'); ?></label>
            <input id="sepo-wb-to" type="url" name="sepo_wb_redirect" class="widefat" value="<?php echo esc_attr($to); ?>" placeholder="https://">
            <span class="description"><?php esc_html_e('Optional. An address on this site, e.g. your pricing page.', 'sepo-wp-bridge'); ?></span>
        </p>
        <?php
    }

    public static function saveBox(int $postId, WP_Post $post): void
    {
        if (!isset($_POST['sepo_wb_membership_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['sepo_wb_membership_nonce'])), 'sepo_wb_membership_' . $postId)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || !current_user_can('edit_post', $postId)) {
            return;
        }
        $tier = isset($_POST['sepo_wb_tier']) ? sanitize_key(wp_unslash((string) $_POST['sepo_wb_tier'])) : '';
        if ($tier !== '' && $tier !== MembershipGate::PUBLIC && MembershipGate::store()->tier($tier) === null) {
            $tier = '';
        }
        $message = isset($_POST['sepo_wb_message']) ? sanitize_textarea_field(wp_unslash((string) $_POST['sepo_wb_message'])) : '';
        $to      = isset($_POST['sepo_wb_redirect']) ? esc_url_raw(trim(wp_unslash((string) $_POST['sepo_wb_redirect']))) : '';
        // Only addresses on this site, so a post cannot bounce readers elsewhere.
        if ($to !== '' && wp_validate_redirect($to, '') === '') {
            $to = '';
        }

        foreach ([MembershipGate::META_TIER => $tier, MembershipGate::META_MESSAGE => $message, MembershipGate::META_REDIRECT => $to] as $k => $v) {
            if ($v === '') {
                delete_post_meta($postId, $k);
            } else {
                update_post_meta($postId, $k, $v);
            }
        }
    }

    public static function userField(WP_User $user): void
    {
        $store = MembershipGate::store();
        if ($store->tiers() === []) {
            return;
        }
        $held = $store->userTier((int) $user->ID);
        $can = current_user_can('edit_users');
        ?>
        <h2><?php esc_html_e('Membership', 'sepo-wp-bridge'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="sepo-wb-user-tier"><?php esc_html_e('Tier', 'sepo-wp-bridge'); ?></label></th>
                <td>
                    <?php if ($can) : ?>
                        <?php wp_nonce_field('sepo_wb_user_tier_' . $user->ID, 'sepo_wb_user_tier_nonce'); ?>
                        <select id="sepo-wb-user-tier" name="sepo_wb_user_tier">
                            <option value=""><?php esc_html_e('None', 'sepo-wp-bridge'); ?></option>
                            <?php foreach ($store->tiers() as $t) : ?>
                                <option value="<?php echo esc_attr($t['id']); ?>" <?php selected($held, $t['id']); ?>><?php echo esc_html($t['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else : ?>
                        <strong><?php echo esc_html($held !== '' ? $store->label($held) : __('None', 'sepo-wp-bridge')); ?></strong>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function saveUserField(int $userId): void
    {
        if (!current_user_can('edit_users')
            || !isset($_POST['sepo_wb_user_tier_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['sepo_wb_user_tier_nonce'])), 'sepo_wb_user_tier_' . $userId)) {
            return;
        }
        $tier = isset($_POST['sepo_wb_user_tier']) ? sanitize_key(wp_unslash((string) $_POST['sepo_wb_user_tier'])) : '';
        $store = MembershipGate::store();
        $store->setUserTier($userId, $tier !== '' && $store->tier($tier) !== null ? $tier : '');
    }

    /** @param array<string,string> $cols */
    public static function userColumn(array $cols): array
    {
        if (MembershipGate::store()->tiers() !== []) {
            $cols['sepo_wb_tier'] = __('Tier', 'sepo-wp-bridge');
        }
        return $cols;
    }

    public static function userColumnValue($value, string $column, int $userId)
    {
        if ($column !== 'sepo_wb_tier') {
            return $value;
        }
        $held = MembershipGate::store()->userTier($userId);
        return $held !== '' ? esc_html(MembershipGate::store()->label($held)) : '—';
    }
}
