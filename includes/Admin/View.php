<?php
namespace SepoWB\Admin;

defined('ABSPATH') || exit;

final class View
{
    /** @return array<string,string> */
    public static function tabs(): array
    {
        return [
            'features'   => __('Features', 'sepo-wp-bridge'),
            'cpts'       => __('Post types', 'sepo-wp-bridge'),
            'taxonomies' => __('Taxonomies', 'sepo-wp-bridge'),
            'metagroups' => __('Meta fields', 'sepo-wp-bridge'),
            'membership' => __('Membership', 'sepo-wp-bridge'),
            'cookies'    => __('Cookies', 'sepo-wp-bridge'),
            'signin'     => __('Sign-in', 'sepo-wp-bridge'),
        ];
    }

    public static function page(): void
    {
        $tabs = self::tabs();
        $active = 'features';
        if (isset($_GET['tab']) && is_string($_GET['tab']) && isset($tabs[$_GET['tab']])) { // phpcs:ignore WordPress.Security.NonceVerification
            $active = sanitize_key($_GET['tab']); // phpcs:ignore WordPress.Security.NonceVerification
        }
        require __DIR__ . '/views/panel.php';
    }
}
