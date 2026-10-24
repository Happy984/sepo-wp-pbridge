<?php
namespace SepoWB;

defined('ABSPATH') || exit;

/**
 * General settings, one option array. Tiers and the default tier live in
 * their own options (see TierStore) so code can filter or seed them.
 */
final class Settings
{
    public const OPTION = 'sepo_wb_settings';

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'join_url'            => '',
            'account_url'         => '',
            'delete_on_uninstall' => 0,
        ];
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        $saved = get_option(self::OPTION, []);
        return array_merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    /** @return mixed */
    public static function get(string $key)
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    public static function sanitize(array $in): array
    {
        return [
            'join_url'            => esc_url_raw(trim((string) ($in['join_url'] ?? ''))),
            'account_url'         => esc_url_raw(trim((string) ($in['account_url'] ?? ''))),
            'delete_on_uninstall' => empty($in['delete_on_uninstall']) ? 0 : 1,
        ];
    }

    /** Where "become a member" buttons and #sp-join links go. */
    public static function joinUrl(): string
    {
        $url = (string) self::get('join_url');
        return (string) apply_filters('sepo_wb_join_url', $url !== '' ? $url : home_url('/membership'));
    }

    /** Where #sp-account links go. */
    public static function accountUrl(): string
    {
        $url = (string) self::get('account_url');
        return (string) apply_filters('sepo_wb_account_url', $url !== '' ? $url : admin_url('profile.php'));
    }
}
