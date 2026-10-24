<?php
namespace SepoWB;

defined('ABSPATH') || exit;

/**
 * Which features the site owner switched on or off. A feature with no entry
 * here follows its registry default.
 */
final class FeatureState
{
    public const OPTION = 'sepo_wb_toggles';

    /** @return array<string,bool> */
    public function all(): array
    {
        $o = get_option(self::OPTION, []);
        return is_array($o) ? array_map('boolval', $o) : [];
    }

    public function isEnabled(string $key, bool $default = false): bool
    {
        $all = $this->all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function set(string $key, bool $on): void
    {
        $all = $this->all();
        $all[$key] = $on;
        update_option(self::OPTION, $all);
    }

    public function forget(string $key): void
    {
        $all = $this->all();
        unset($all[$key]);
        update_option(self::OPTION, $all);
    }
}
