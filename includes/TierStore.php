<?php
namespace SepoWB;

defined('ABSPATH') || exit;

/**
 * Membership tiers. The tier list lives in an option, each user's tier in
 * user meta `_sepo_wb_tier`. Tiers are ranked, so a higher tier passes a
 * gate set to a lower one ("gold" satisfies "silver").
 */
final class TierStore
{
    public const TIERS_OPTION   = 'sepo_wb_tiers';
    public const USER_META      = '_sepo_wb_tier';
    public const DEFAULT_OPTION = 'sepo_wb_default_tier';

    /** @var array<int,array{id:string,label:string,rank:int}>|null */
    private static ?array $cache = null;

    /** @return array<int,array{id:string,label:string,rank:int}> sorted by rank */
    public function tiers(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $raw = apply_filters('sepo_wb_tiers', get_option(self::TIERS_OPTION, []));
        $out = [];
        $seen = [];
        foreach (is_array($raw) ? $raw : [] as $t) {
            if (!is_array($t)) {
                continue;
            }
            $id = sanitize_key((string) ($t['id'] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = [
                'id'    => $id,
                'label' => (string) ($t['label'] ?? '') !== '' ? (string) $t['label'] : ucfirst($id),
                'rank'  => (int) ($t['rank'] ?? 0),
            ];
        }
        usort($out, static fn($a, $b) => $a['rank'] <=> $b['rank']);
        return self::$cache = $out;
    }

    /**
     * Replace the tier list.
     *
     * @param array<int,array<string,mixed>> $tiers
     */
    public function save(array $tiers): void
    {
        $clean = [];
        $seen = [];
        foreach ($tiers as $t) {
            $id = sanitize_key((string) ($t['id'] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $clean[] = [
                'id'    => $id,
                'label' => sanitize_text_field((string) ($t['label'] ?? '')) ?: ucfirst($id),
                'rank'  => (int) ($t['rank'] ?? 0),
            ];
        }
        update_option(self::TIERS_OPTION, $clean);
        self::$cache = null;
        if (!isset($seen[$this->defaultTier()])) {
            update_option(self::DEFAULT_OPTION, '');
        }
    }

    /** @return array{id:string,label:string,rank:int}|null */
    public function tier(string $id): ?array
    {
        foreach ($this->tiers() as $t) {
            if ($t['id'] === $id) {
                return $t;
            }
        }
        return null;
    }

    /** @return array<string,string> id => label */
    public function labels(): array
    {
        $out = [];
        foreach ($this->tiers() as $t) {
            $out[$t['id']] = $t['label'];
        }
        return $out;
    }

    public function label(string $id): string
    {
        return $this->labels()[$id] ?? ucfirst($id);
    }

    /** The user's own tier id, or ''. */
    public function userTier(int $uid): string
    {
        return $uid > 0 ? sanitize_key((string) get_user_meta($uid, self::USER_META, true)) : '';
    }

    /**
     * Does the user hold $tier, or a tier that outranks it?
     *
     * @param string|int $uid
     */
    public function hasLevel($uid, string $tier): bool
    {
        $tier = sanitize_key($tier);
        if ($tier === '') {
            return true;
        }
        $uid = (int) $uid;
        if ($uid <= 0) {
            return false;
        }
        $wanted = $this->tier($tier);
        if ($wanted === null) {
            return false;   // unknown tier: fail closed
        }
        $held = $this->userTier($uid);
        $have = $held !== '' ? $this->tier($held) : null;
        $ok = $held === $tier || ($have !== null && $have['rank'] >= $wanted['rank']);

        /** Let a shop or membership plugin grant access its own way. */
        return (bool) apply_filters('sepo_wb_user_has_tier', $ok, $uid, $tier, $held);
    }

    public function setUserTier(int $uid, string $tier): void
    {
        if ($uid <= 0) {
            return;
        }
        $tier = sanitize_key($tier);
        if ($tier === '') {
            delete_user_meta($uid, self::USER_META);
            return;
        }
        update_user_meta($uid, self::USER_META, $tier);
    }

    /** Site-wide tier applied to posts with no gate of their own. */
    public function defaultTier(): string
    {
        return sanitize_key((string) get_option(self::DEFAULT_OPTION, ''));
    }

    public function setDefaultTier(string $tier): void
    {
        $tier = sanitize_key($tier);
        update_option(self::DEFAULT_OPTION, $tier !== '' && $this->tier($tier) !== null ? $tier : '');
    }
}
