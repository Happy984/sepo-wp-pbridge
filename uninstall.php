<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Settings are removed only when "Delete this plugin's settings…" is ticked
 * in WP Bridge → Membership (or SEPO_WB_DELETE_DATA is true in wp-config.php).
 * Posts, terms, saved field values and users are never deleted.
 */
defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

wp_clear_scheduled_hook('sepo_wb_consent_purge');

// Short-lived counters: always cleared.
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_sepo\\_wb\\_%' OR option_name LIKE '\\_transient\\_timeout\\_sepo\\_wb\\_%'"); // phpcs:ignore WordPress.DB

$settings = get_option('sepo_wb_settings', []);
$delete = (defined('SEPO_WB_DELETE_DATA') && SEPO_WB_DELETE_DATA) || (is_array($settings) && !empty($settings['delete_on_uninstall']));

if ($delete) {
    foreach ([
        'sepo_wb_features', 'sepo_wb_defaults_seen', 'sepo_wb_toggles', 'sepo_wb_handler_params',
        'sepo_wb_cpts', 'sepo_wb_taxonomies', 'sepo_wb_metagroups', 'sepo_wb_flush',
        'sepo_wb_tiers', 'sepo_wb_default_tier', 'sepo_wb_gate_types', 'sepo_wb_settings', 'sepo_wb_version',
        'sepo_wb_consent', 'sepo_wb_signin',
    ] as $opt) {
        delete_option($opt);
    }
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sepo_wb_consent_log"); // phpcs:ignore WordPress.DB
    $wpdb->delete($wpdb->usermeta, ['meta_key' => '_sepo_wb_tier']); // phpcs:ignore WordPress.DB
    // Provider links and two-factor state. Accounts made by a provider sign-in are kept.
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\\_sepo\\_wb\\_social%' OR meta_key LIKE '\\_sepo\\_wb\\_2fa%'"); // phpcs:ignore WordPress.DB
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_sp_require_membership','_sp_membership_message','_sp_membership_redirect','_sp_nav_visibility')"); // phpcs:ignore WordPress.DB
}
