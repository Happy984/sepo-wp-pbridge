<?php
namespace SepoWB\Features;

defined('ABSPATH') || exit;

/**
 * Every feature handler. A feature row names one public method here; the
 * plugin calls it once per request (on plugins_loaded) when the feature is
 * on, passing the feature's saved settings as named arguments.
 *
 * Handlers only add or remove hooks, so the order they run in does not
 * matter and one failing cannot stop the others.
 *
 * To add your own: extend this class with a public method, then filter
 * `sepo_wb_handlers_class` to your class name.
 */
class Handlers
{
    // ============================================================ catalogue

    /**
     * Public handler methods and their settings, read from the signatures.
     *
     * @return array<string,list<array{name:string,type:string,default:mixed}>>
     */
    public static function catalog(): array
    {
        static $cache = [];
        $class = self::className();
        if (isset($cache[$class])) {
            return $cache[$class];
        }
        $out = [];
        foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->isStatic() || $m->isConstructor() || str_starts_with($m->getName(), '__')) {
                continue;
            }
            $params = [];
            foreach ($m->getParameters() as $p) {
                $t = $p->getType();
                $type = $t instanceof \ReflectionNamedType ? $t->getName() : 'string';
                $params[] = [
                    'name'    => $p->getName(),
                    'type'    => in_array($type, ['int', 'array', 'bool', 'string'], true) ? $type : 'string',
                    'default' => $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null,
                ];
            }
            $out[$m->getName()] = $params;
        }
        ksort($out);
        return $cache[$class] = $out;
    }

    /** @return class-string<Handlers> */
    public static function className(): string
    {
        $class = (string) apply_filters('sepo_wb_handlers_class', self::class);
        return (class_exists($class) && is_a($class, self::class, true)) ? $class : self::class;
    }

    /**
     * Keep only settings the handler accepts, cast to the declared type.
     *
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    public static function sanitizeParams(string $handler, array $in): array
    {
        $out = [];
        foreach (self::catalog()[$handler] ?? [] as $p) {
            if (!array_key_exists($p['name'], $in)) {
                continue;
            }
            $v = $in[$p['name']];
            switch ($p['type']) {
                case 'int':
                    $out[$p['name']] = max(0, (int) $v);
                    break;
                case 'bool':
                    $out[$p['name']] = filter_var($v, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'array':
                    $list = is_array($v) ? $v : preg_split('/[\s,]+/', (string) $v);
                    $out[$p['name']] = array_values(array_filter(array_map(
                        static fn($s): string => trim(preg_replace('#[^A-Za-z0-9_\-./]#', '', (string) $s) ?? ''),
                        (array) $list
                    ), static fn(string $s): bool => $s !== ''));
                    break;
                default:
                    $out[$p['name']] = sanitize_text_field((string) $v);
            }
        }
        return $out;
    }

    private static function ip(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) wp_unslash($_SERVER['REMOTE_ADDR']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }

    // ================================================================ ADMIN

    public function disableAdminBar(): void
    {
        add_filter('show_admin_bar', '__return_false');
    }

    public function disableAdminBarForRoles(array $roles = ['subscriber']): void
    {
        add_filter('show_admin_bar', static function ($show) use ($roles) {
            $user = wp_get_current_user();
            if (!$show || !$user->exists()) {
                return $show;
            }
            return array_intersect($roles, (array) $user->roles) === [] ? $show : false;
        });
    }

    public function cleanDashboardWidgets(): void
    {
        add_action('wp_dashboard_setup', static function (): void {
            $widgets = [
                'dashboard_quick_press', 'dashboard_primary', 'dashboard_secondary',
                'dashboard_site_health', 'dashboard_activity', 'dashboard_recent_comments',
                'dashboard_incoming_links', 'dashboard_plugins',
            ];
            foreach ($widgets as $w) {
                remove_meta_box($w, 'dashboard', 'normal');
                remove_meta_box($w, 'dashboard', 'side');
            }
            remove_action('welcome_panel', 'wp_welcome_panel');
        }, 999);
    }

    public function removeWpLogo(): void
    {
        add_action('admin_bar_menu', static function ($bar): void {
            $bar->remove_node('wp-logo');
        }, 999);
    }

    public function hideAdminNotices(): void
    {
        add_action('admin_head', static function (): void {
            if (current_user_can('manage_options')) {
                return;
            }
            echo '<style>#wpbody-content > .notice, #wpbody-content > .update-nag, #wpbody-content .wrap > .notice:not(.inline){display:none!important}</style>';
        });
    }

    // ============================================================= SECURITY

    public function disableXmlRpc(): void
    {
        // xmlrpc_enabled only covers methods that need a password; system.* and
        // pingback.ping still answer. Refuse the whole endpoint instead.
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            status_header(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'XML-RPC is disabled on this site.';
            exit;
        }
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('xmlrpc_methods', '__return_empty_array');
        add_filter('wp_headers', static function (array $h): array {
            unset($h['X-Pingback']);
            return $h;
        });
        remove_action('wp_head', 'rsd_link');
    }

    /** @param string[] $allow REST namespaces signed-out visitors may still use */
    public function restrictRestApi(array $allow = ['oembed/1.0']): void
    {
        // The sign-in provider returns visitors here before they are signed in.
        $allow[] = 'sepo-wb/v1/auth';
        add_filter('rest_authentication_errors', static function ($result) use ($allow) {
            if (!empty($result) || is_user_logged_in()) {
                return $result;
            }
            $route = ltrim((string) ($GLOBALS['wp']->query_vars['rest_route'] ?? ''), '/');
            foreach ($allow as $ns) {
                $ns = trim((string) $ns, '/');
                if ($ns !== '' && ($route === $ns || str_starts_with($route, $ns . '/'))) {
                    return $result;
                }
            }
            return new \WP_Error('rest_forbidden', __('The REST API is only available to signed-in users.', 'sepo-wp-bridge'), ['status' => 401]);
        });
    }

    public function removeGeneratorTags(): void
    {
        remove_action('wp_head', 'wp_generator');
        add_filter('the_generator', '__return_empty_string');
    }

    public function disableAutoUpdates(): void
    {
        add_filter('automatic_updater_disabled', '__return_true');
        add_filter('auto_update_plugin', '__return_false');
        add_filter('auto_update_theme', '__return_false');
        add_filter('auto_update_core', '__return_false');
    }

    public function hideLoginErrors(): void
    {
        // Sign-in step messages (expired, wrong code, cancelled) say nothing about
        // whether an account exists, so they are kept; everything else is replaced.
        add_filter('login_errors', static fn($msg) => str_contains((string) $msg, 'sepo-si-msg') ? $msg : esc_html__('Login failed.', 'sepo-wp-bridge'));
    }

    public function disableUserEnumeration(): void
    {
        // ?author=N redirects to /author/username/ — refuse it for visitors.
        add_action('template_redirect', static function (): void {
            if (!is_user_logged_in() && isset($_GET['author'])) { // phpcs:ignore WordPress.Security.NonceVerification
                wp_safe_redirect(home_url('/'), 301);
                exit;
            }
        }, 1);
        add_filter('rest_endpoints', static function (array $endpoints): array {
            if (is_user_logged_in()) {
                return $endpoints;
            }
            foreach (array_keys($endpoints) as $route) {
                if (str_starts_with($route, '/wp/v2/users')) {
                    unset($endpoints[$route]);
                }
            }
            return $endpoints;
        });
        add_filter('wp_sitemaps_add_provider', static fn($provider, $name) => $name === 'users' ? false : $provider, 10, 2);
        add_filter('oembed_response_data', static function (array $data): array {
            unset($data['author_name'], $data['author_url']);
            return $data;
        });
    }

    public function disableApplicationPasswords(): void
    {
        add_filter('wp_is_application_passwords_available', '__return_false');
    }

    public function limitLoginAttempts(int $max = 5, int $lock = 15): void
    {
        $max = max(1, $max);
        $lock = max(1, $lock);
        $key = 'sepo_wb_login_' . md5(self::ip());

        add_filter('authenticate', static function ($user, $username) use ($max, $lock, $key) {
            if ($username === '' || $username === null) {
                return $user;
            }
            if ((int) get_transient($key) >= $max) {
                return new \WP_Error('too_many_attempts', sprintf(
                    /* translators: %d: minutes */
                    _n('Too many failed sign-ins. Try again in %d minute.', 'Too many failed sign-ins. Try again in %d minutes.', $lock, 'sepo-wp-bridge'),
                    $lock
                ));
            }
            return $user;
        }, 99, 2);

        add_action('wp_login_failed', static function () use ($lock, $key): void {
            set_transient($key, (int) get_transient($key) + 1, $lock * MINUTE_IN_SECONDS);
        });

        add_action('wp_login', static function () use ($key): void {
            delete_transient($key);
        });
    }

    public function enforceStrongPasswords(int $minLength = 12): void
    {
        $check = static function (string $pw) use ($minLength): bool {
            return mb_strlen($pw) >= $minLength
                && preg_match('/[A-Z]/', $pw) && preg_match('/[a-z]/', $pw)
                && preg_match('/[0-9]/', $pw) && preg_match('/[^A-Za-z0-9]/', $pw);
        };
        $validate = static function ($errors) use ($check, $minLength): void {
            $pw = isset($_POST['pass1']) ? (string) wp_unslash($_POST['pass1']) : ''; // phpcs:ignore WordPress.Security
            if ($pw !== '' && !$check($pw)) {
                $errors->add('weak_password', sprintf(
                    /* translators: %d: characters */
                    __('Use at least %d characters, with upper and lower case letters, a number and a symbol.', 'sepo-wp-bridge'),
                    $minLength
                ));
            }
        };
        add_action('user_profile_update_errors', $validate);
        add_action('validate_password_reset', $validate);
    }

    public function blockSuspiciousUploads(): void
    {
        add_filter('wp_handle_upload_prefilter', static function ($file) {
            $blocked = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar',
                'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp', 'sh', 'bash', 'bat', 'cmd', 'exe', 'com', 'msi', 'dll', 'htaccess'];
            // Every dotted part, so "photo.php.jpg" is caught too.
            $parts = array_slice(explode('.', strtolower((string) ($file['name'] ?? ''))), 1);
            if (array_intersect($parts, $blocked) !== [] || str_starts_with((string) ($file['name'] ?? ''), '.')) {
                $file['error'] = __('This file type is not allowed for security reasons.', 'sepo-wp-bridge');
            }
            return $file;
        });
    }

    public function rateLimitRequests(int $max = 120, int $window = 60): void
    {
        add_action('init', static function () use ($max, $window): void {
            if (is_admin() || wp_doing_cron() || is_user_logged_in() || (defined('WP_CLI') && WP_CLI)) {
                return;
            }
            $key = 'sepo_wb_rate_' . md5(self::ip());
            $count = (int) get_transient($key) + 1;
            if ($count > max(1, $max)) {
                status_header(429);
                header('Retry-After: ' . max(1, $window));
                wp_die(esc_html__('Too many requests. Please wait a moment and try again.', 'sepo-wp-bridge'), '', ['response' => 429]);
            }
            set_transient($key, $count, max(1, $window));
        }, 0);
    }

    // ========================================================== PERFORMANCE

    public function disableEmojis(): void
    {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
        remove_action('admin_enqueue_scripts', 'wp_enqueue_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('emoji_svg_url', '__return_false');
    }

    public function disableEmbeds(): void
    {
        remove_action('rest_api_init', 'wp_oembed_register_route');
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result');
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        add_action('wp_footer', static function (): void {
            wp_dequeue_script('wp-embed');
        });
    }

    public function disableHeartbeat(): void
    {
        $off = static function (): void {
            global $pagenow;
            if (in_array($pagenow, ['post.php', 'post-new.php'], true)) {
                return;   // keeps autosave and post locking working
            }
            wp_deregister_script('heartbeat');
        };
        add_action('wp_enqueue_scripts', $off, 1);
        add_action('admin_enqueue_scripts', $off, 1);
    }

    // ================================================================ MEDIA

    public function allowSvgUploads(): void
    {
        add_filter('upload_mimes', static function ($mimes) {
            if (current_user_can('manage_options')) {
                $mimes['svg'] = 'image/svg+xml';
            }
            return $mimes;
        });
        // PHP's file-type check often reports SVG as text/plain or text/html.
        add_filter('wp_check_filetype_and_ext', static function ($data, $file, $filename) {
            if (current_user_can('manage_options') && strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION)) === 'svg') {
                $data['ext'] = 'svg';
                $data['type'] = 'image/svg+xml';
            }
            return $data;
        }, 10, 3);
        add_filter('wp_handle_upload_prefilter', static function ($file) {
            if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'svg') {
                return $file;
            }
            $svg = is_readable((string) ($file['tmp_name'] ?? '')) ? (string) file_get_contents($file['tmp_name']) : '';
            if ($svg === '' || preg_match('/<script|\son[a-z]+\s*=|javascript:|<foreignObject|<!ENTITY|xlink:href\s*=\s*["\']\s*(?!#)/i', $svg)) {
                $file['error'] = __('This SVG contains scripts or external links and was refused.', 'sepo-wp-bridge');
            }
            return $file;
        });
    }

    public function allowWebpUploads(): void
    {
        add_filter('upload_mimes', static function ($mimes) {
            $mimes['webp'] = 'image/webp';
            return $mimes;
        });
    }

    public function sanitizeUploadFilenames(): void
    {
        add_filter('sanitize_file_name', static function ($name) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base = $ext !== '' ? substr($name, 0, -strlen($ext) - 1) : $name;
            $base = strtolower(remove_accents($base));
            $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', $base), '-');
            return ($base !== '' ? $base : 'file') . ($ext !== '' ? '.' . strtolower($ext) : '');
        }, 20);
    }

    public function enableLazyLoading(): void
    {
        add_filter('wp_lazy_loading_enabled', '__return_true');
    }

    public function disableLazyLoading(): void
    {
        add_filter('wp_lazy_loading_enabled', '__return_false');
    }

    public function disableBigImageScaling(): void
    {
        add_filter('big_image_size_threshold', '__return_false');
    }

    public function setJpegQuality(int $quality = 82): void
    {
        $q = max(1, min(100, $quality));
        add_filter('jpeg_quality', static fn() => $q);
        add_filter('wp_editor_set_quality', static fn($quality, $mime = '') => $mime === 'image/jpeg' || $mime === '' ? $q : $quality, 10, 2);
    }

    // ============================================================ BRANDING

    /**
     * Restyle wp-login.php (log in, lost/reset password, register) as one card.
     * {year} and {site} in the footer are replaced; leave a setting empty to
     * use the default (the logo falls back to the Site Icon, then the theme's
     * custom logo, then the WordPress logo).
     */
    public function styleLoginPage(
        string $heading = '',
        string $footer = '© {year} {site}',
        string $logo = '',
        string $color = '#00bcd4',
        string $colorDark = '#2e6b76'
    ): void {
        add_action('login_enqueue_scripts', static function () use ($logo, $color, $colorDark): void {
            wp_enqueue_style('sepo-wb-login', SEPO_WB_URL . 'assets/css/login.css', ['login'], SEPO_WB_VERSION);

            $css = '';
            $primary = sanitize_hex_color($color);
            $dark = sanitize_hex_color($colorDark);
            if ($primary || $dark) {
                $css .= 'body.login{' . ($primary ? '--primary:' . $primary . ';' : '') . ($dark ? '--primary-dark:' . $dark . ';' : '') . '}';
            }
            $url = $logo !== '' ? esc_url_raw($logo) : '';
            if ($url === '') {
                $url = (string) get_site_icon_url(192);
            }
            if ($url === '' && ($id = (int) get_theme_mod('custom_logo'))) {
                $url = (string) wp_get_attachment_image_url($id, 'medium');
            }
            if ($url !== '') {
                $css .= '#login h1 a,#login .wp-login-logo a{background-image:url("' . esc_url($url) . '")!important}';
            }
            if ($css !== '') {
                wp_add_inline_style('sepo-wb-login', $css);
            }
        });

        // The logo links to this site, not wordpress.org.
        add_filter('login_headerurl', static fn() => home_url('/'));
        add_filter('login_headertext', static fn() => get_bloginfo('name', 'display'));

        // A real heading above each form (readable by screen readers, unlike CSS content).
        add_filter('login_message', static function ($message) use ($heading) {
            $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash((string) $_REQUEST['action'])) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification
            switch ($action) {
                case 'lostpassword':
                case 'retrievepassword':
                    $title = __('Reset your password', 'sepo-wp-bridge');
                    break;
                case 'rp':
                case 'resetpass':
                    $title = __('Choose a new password', 'sepo-wp-bridge');
                    break;
                case 'register':
                    $title = __('Create an account', 'sepo-wp-bridge');
                    break;
                case 'sepo_2fa':
                    $title = __('Verification code', 'sepo-wp-bridge');
                    break;
                case 'confirm_admin_email':
                case 'postpass':
                case 'logout':
                case 'confirmaction':
                    return $message;
                default:
                    $title = $heading !== '' ? $heading : __('Welcome back', 'sepo-wp-bridge');
            }
            return '<h2 class="sepo-login-title">' . esc_html($title) . '</h2>' . $message;
        }, 5);

        add_action('login_footer', static function () use ($footer): void {
            $text = $footer !== '' ? $footer : '© {year} {site}';
            $text = strtr($text, ['{year}' => wp_date('Y'), '{site}' => get_bloginfo('name', 'display')]);
            echo '<p class="sepo-login-footer">' . esc_html($text) . '</p>';
        });
    }
}
