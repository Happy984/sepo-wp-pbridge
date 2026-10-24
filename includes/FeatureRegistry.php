<?php
namespace SepoWB;

use SepoWB\Features\Handlers;

defined('ABSPATH') || exit;

/**
 * The list of features: one row per feature, naming the Handlers method it
 * runs, its category, whether it is on by default, and any settings passed
 * to the handler.
 */
final class FeatureRegistry
{
    public const OPTION = 'sepo_wb_features';
    /** Default keys already offered once, so a deleted default stays deleted. */
    public const SEEN_OPTION = 'sepo_wb_defaults_seen';

    public const CATEGORIES = ['security', 'performance', 'admin', 'branding', 'media', 'other'];

    /** Features shipped with the plugin. */
    public static function defaults(): array
    {
        $f = static function (string $label, string $category, bool $default, string $handler, string $description, array $params = []): array {
            return compact('label', 'category', 'default', 'handler', 'description', 'params');
        };

        return [
            // ---- security
            'disable_xmlrpc'           => $f(__('Disable XML-RPC', 'sepo-wp-bridge'), 'security', true, 'disableXmlRpc', __('Turns off xmlrpc.php, a common target for password guessing. Jetpack and some old mobile apps need it.', 'sepo-wp-bridge')),
            'remove_generator'         => $f(__('Remove WordPress version tag', 'sepo-wp-bridge'), 'security', true, 'removeGeneratorTags', __('Stops the page source and feeds from saying which WordPress version runs the site.', 'sepo-wp-bridge')),
            'block_dangerous_uploads'  => $f(__('Block dangerous uploads', 'sepo-wp-bridge'), 'security', true, 'blockSuspiciousUploads', __('Refuses PHP, script and executable files in the media library, even for administrators.', 'sepo-wp-bridge')),
            'hide_login_errors'        => $f(__('Hide login error details', 'sepo-wp-bridge'), 'security', false, 'hideLoginErrors', __('Shows "Login failed." instead of saying whether the username or the password was wrong.', 'sepo-wp-bridge')),
            'disable_user_enumeration' => $f(__('Block user enumeration', 'sepo-wp-bridge'), 'security', false, 'disableUserEnumeration', __('Stops signed-out visitors listing usernames through ?author=N, the REST users endpoint and author sitemaps.', 'sepo-wp-bridge')),
            'limit_login_attempts'     => $f(__('Limit login attempts', 'sepo-wp-bridge'), 'security', false, 'limitLoginAttempts', __('Locks an IP address out after too many failed sign-ins.', 'sepo-wp-bridge'), ['max' => 5, 'lock' => 15]),
            'strong_passwords'         => $f(__('Require strong passwords', 'sepo-wp-bridge'), 'security', false, 'enforceStrongPasswords', __('New passwords need upper and lower case, a number and a symbol.', 'sepo-wp-bridge'), ['minLength' => 12]),
            'disable_app_passwords'    => $f(__('Disable application passwords', 'sepo-wp-bridge'), 'security', false, 'disableApplicationPasswords', __('Turns off REST API application passwords. Leave on if an app or service signs in with one.', 'sepo-wp-bridge')),
            'restrict_rest_api'        => $f(__('Restrict REST API to signed-in users', 'sepo-wp-bridge'), 'security', false, 'restrictRestApi', __('Signed-out visitors get 401 from the REST API, except the routes listed. Breaks plugins whose public pages call the API — list their namespaces.', 'sepo-wp-bridge'), ['allow' => ['oembed/1.0', 'contact-form-7/v1', 'sepo-resources/v1', 'sepo-lms/v1', 'wc/store']]),
            'rate_limit'               => $f(__('Rate-limit visitors', 'sepo-wp-bridge'), 'security', false, 'rateLimitRequests', __('Answers 429 to an IP address that makes too many page requests in a short time. Signed-in users are not limited.', 'sepo-wp-bridge'), ['max' => 120, 'window' => 60]),
            'disable_auto_updates'     => $f(__('Disable automatic updates', 'sepo-wp-bridge'), 'security', false, 'disableAutoUpdates', __('Stops WordPress, plugins and themes updating themselves. You must then update by hand.', 'sepo-wp-bridge')),

            // ---- performance
            'disable_emojis'           => $f(__('Disable emoji scripts', 'sepo-wp-bridge'), 'performance', true, 'disableEmojis', __('Removes the emoji detection script and styles; browsers show emoji natively.', 'sepo-wp-bridge')),
            'disable_embeds'           => $f(__('Disable oEmbed', 'sepo-wp-bridge'), 'performance', false, 'disableEmbeds', __('Stops other sites embedding your posts and removes the embed script. Embedding YouTube etc. in your own posts still works.', 'sepo-wp-bridge')),
            'disable_heartbeat'        => $f(__('Limit the Heartbeat API', 'sepo-wp-bridge'), 'performance', false, 'disableHeartbeat', __('Turns off the background Heartbeat everywhere except the post editor, where it saves drafts and locks posts.', 'sepo-wp-bridge')),

            // ---- admin
            'clean_dashboard'          => $f(__('Clean dashboard widgets', 'sepo-wp-bridge'), 'admin', false, 'cleanDashboardWidgets', __('Removes WordPress news, quick draft, activity and site health from the dashboard.', 'sepo-wp-bridge')),
            'hide_admin_bar'           => $f(__('Hide admin bar on the site', 'sepo-wp-bridge'), 'admin', false, 'disableAdminBar', __('Hides the toolbar on the front end for everyone.', 'sepo-wp-bridge')),
            'hide_admin_bar_roles'     => $f(__('Hide admin bar for some roles', 'sepo-wp-bridge'), 'admin', false, 'disableAdminBarForRoles', __('Hides the front-end toolbar for the roles listed.', 'sepo-wp-bridge'), ['roles' => ['subscriber', 'customer']]),
            'remove_wp_logo'           => $f(__('Remove WordPress logo from toolbar', 'sepo-wp-bridge'), 'admin', false, 'removeWpLogo', __('Removes the W menu from the toolbar.', 'sepo-wp-bridge')),
            'hide_admin_notices'       => $f(__('Hide admin notices for non-admins', 'sepo-wp-bridge'), 'admin', false, 'hideAdminNotices', __('Hides plugin and update notices for users who cannot manage the site. Administrators still see them.', 'sepo-wp-bridge')),

            // ---- branding
            'style_login'              => $f(__('Styled login page', 'sepo-wp-bridge'), 'branding', true, 'styleLoginPage', __('Turns the log in, lost password and register screens into one clean card in your colours, with your logo and a footer line. Logo: leave empty to use the Site Icon or the theme logo. Footer: {year} and {site} are filled in.', 'sepo-wp-bridge'), ['heading' => '', 'footer' => '© {year} {site}', 'logo' => '', 'color' => '#00bcd4', 'colorDark' => '#2e6b76']),

            // ---- media
            'allow_webp'               => $f(__('Allow WebP uploads', 'sepo-wp-bridge'), 'media', true, 'allowWebpUploads', __('Lets the media library accept .webp images on servers that refuse them.', 'sepo-wp-bridge')),
            'allow_svg'                => $f(__('Allow SVG uploads (administrators)', 'sepo-wp-bridge'), 'media', false, 'allowSvgUploads', __('Lets administrators upload SVG. Files containing scripts or event handlers are refused.', 'sepo-wp-bridge')),
            'sanitize_filenames'       => $f(__('Clean upload file names', 'sepo-wp-bridge'), 'media', false, 'sanitizeUploadFilenames', __('Lower-cases file names and replaces spaces and accents, e.g. "Rapport Été.pdf" → "rapport-ete.pdf".', 'sepo-wp-bridge')),
            'enable_lazy_loading'      => $f(__('Lazy-load images', 'sepo-wp-bridge'), 'media', true, 'enableLazyLoading', __('Images and iframes load as they scroll into view (WordPress default).', 'sepo-wp-bridge')),
            'disable_lazy_loading'     => $f(__('Turn off lazy loading', 'sepo-wp-bridge'), 'media', false, 'disableLazyLoading', __('Loads every image at once. Use instead of the option above, not with it.', 'sepo-wp-bridge')),
            'disable_big_image_scaling'=> $f(__('Keep full-size uploads', 'sepo-wp-bridge'), 'media', false, 'disableBigImageScaling', __('Stops WordPress shrinking images wider than 2560px on upload.', 'sepo-wp-bridge')),
            'jpeg_quality'             => $f(__('Set JPEG quality', 'sepo-wp-bridge'), 'media', false, 'setJpegQuality', __('Quality WordPress uses when it makes resized copies of JPEG images (1–100).', 'sepo-wp-bridge'), ['quality' => 82]),
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        $rows = get_option(self::OPTION, []);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $key => $row) {
            if (is_array($row)) {
                $out[(string) $key] = self::normalise($row);
            }
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function normalise(array $row): array
    {
        $cat = (string) ($row['category'] ?? 'other');
        return [
            'label'       => (string) ($row['label'] ?? ''),
            'category'    => in_array($cat, self::CATEGORIES, true) ? $cat : 'other',
            'default'     => !empty($row['default']),
            'handler'     => (string) ($row['handler'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'params'      => is_array($row['params'] ?? null) ? $row['params'] : [],
        ];
    }

    /**
     * Add or update a feature. Throws on bad input so the caller can show why.
     *
     * @param array<string,mixed> $row
     */
    public function save(string $key, array $row): void
    {
        $key = sanitize_key($key);
        if ($key === '') {
            throw new \InvalidArgumentException(__('Key: use lower-case letters, numbers, - and _.', 'sepo-wp-bridge'));
        }
        $handler = trim((string) ($row['handler'] ?? ''));
        $catalog = Handlers::catalog();
        if (!isset($catalog[$handler])) {
            throw new \InvalidArgumentException(__('Choose a handler from the list.', 'sepo-wp-bridge'));
        }
        $label = sanitize_text_field((string) ($row['label'] ?? ''));
        if ($label === '') {
            throw new \InvalidArgumentException(__('Give the feature a label.', 'sepo-wp-bridge'));
        }

        $rows = $this->all();
        $rows[$key] = self::normalise([
            'label'       => $label,
            'category'    => sanitize_key((string) ($row['category'] ?? 'other')),
            'default'     => !empty($row['default']),
            'handler'     => $handler,
            'description' => sanitize_textarea_field((string) ($row['description'] ?? '')),
            'params'      => Handlers::sanitizeParams($handler, is_array($row['params'] ?? null) ? $row['params'] : []),
        ]);
        update_option(self::OPTION, $rows);
    }

    public function delete(string $key): bool
    {
        $rows = $this->all();
        if (!isset($rows[$key])) {
            return false;
        }
        unset($rows[$key]);
        update_option(self::OPTION, $rows);
        return true;
    }

    /**
     * Add defaults the site has never been offered (new in this version),
     * without bringing back ones the owner deleted.
     */
    public function mergeNewDefaults(): void
    {
        $seen = get_option(self::SEEN_OPTION, []);
        $seen = is_array($seen) ? $seen : [];
        $rows = $this->all();
        $changed = false;
        foreach (self::defaults() as $key => $row) {
            if (in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;
            if (!isset($rows[$key])) {
                $rows[$key] = $row;
            } elseif ($rows[$key]['description'] === '') {
                $rows[$key]['description'] = $row['description'];
                $rows[$key]['params'] = $rows[$key]['params'] ?: $row['params'];
            }
            $changed = true;
        }
        if ($changed) {
            update_option(self::OPTION, $rows);
            update_option(self::SEEN_OPTION, $seen, false);
        }
    }

    /** Put the shipped features back as they were, keeping custom ones. */
    public function restoreDefaults(): void
    {
        $rows = $this->all();
        foreach (self::defaults() as $key => $row) {
            $rows[$key] = $row;
        }
        update_option(self::OPTION, $rows);
        update_option(self::SEEN_OPTION, array_keys(self::defaults()), false);
    }
}
