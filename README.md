# Sepo WordPress Bridge

![Version](https://img.shields.io/badge/version-1.5.1-0b3d33)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

One WordPress panel that brings together:

- feature toggles;
- custom post types, taxonomies and custom fields;
- membership tiers;
- a cookie consent banner;
- social sign-in with two-factor authentication;
- menu and block visibility.

It has no outside dependency. Everything lives under **WP Bridge** in the admin menu.

<p align="center">
  <a href="https://edu.sepodesk.com/sponsor/projects-maintenance/">
    <img src="https://img.shields.io/badge/%E2%9D%A4%20SUPPORT%20OUR%20OPEN--SOURCE%20PROJECTS-0b6b45?style=for-the-badge&logo=githubsponsors&logoColor=white" alt="Support Our Open-Source Projects" />
  </a>
</p>

<p align="center">
  Your support helps us maintain, improve and build open-source software for everyone.
  <br />
  <strong>Every contribution makes a difference. Thank you for supporting our work!</strong>
</p>

## Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Getting started](#getting-started)
- [Setting up social sign-in](#setting-up-social-sign-in)
- [Shortcodes and blocks](#shortcodes-and-blocks)
- [Uninstalling](#uninstalling)
- [Troubleshooting](#troubleshooting)
- [For developers](#for-developers)
- [Changelog](#changelog)
- [License](#license)

## Features

### Features tab

- Security, performance, admin and media switches, grouped by category. Each has a plain description.
- Some have settings, such as:
  - login attempts and lock-out minutes;
  - password length and rate limits;
  - roles and JPEG quality;
  - REST namespaces left open.
- **Styled login page:** a clean single-card login in your colours, with your logo (**Features → Branding**).

### Post types, taxonomies and fields

- **Post types:**
  - singular and plural names, slug and address;
  - menu icon;
  - what the editing screen shows;
  - public, archive and hierarchical options.
- **Taxonomies:** categories (nested) or tags (flat), attached to any post types.
- **Meta fields:** groups of fields shown as a box on the editing screen.
  - Field types: text, paragraph, number, yes/no, choice list, email, link and date.
  - Values are registered for the REST API and the block editor.

There are several ways to show fields on the site:

- **Automatically**, before or after the content: as a table, a grid of tiles or one inline line.
- **As columns** in the admin posts lists, sortable.
- **Blocks:** **Post field** and **Field group**. Both work inside Query Loop templates.
- **Shortcodes:** `[sepo_field]` and `[sepo_fields]`.
- **Elementor:** **Post field** and **Field group** widgets, plus dynamic tags for any text, number, button or link.

### Membership

- Ranked tiers, where Gold includes Silver.
- A site-wide default tier for chosen post types.
- Join and account links.
- A **Members only** box on each post: who can read it, the message locked-out readers see, or a page to send them to.
- Locked content is also removed from excerpts, feeds, the REST API and comments. Anyone who can edit the post still sees it.
- Each user has a **Tier** on their profile, and the Users list shows it.

### Cookie consent

- Visitors choose:
  - **Accept all**;
  - **Reject all** (necessary cookies only);
  - **Choose cookies**, with a switch per category and every cookie listed.
- **Scan site for cookies** finds what your pages set and describes known services for you.
- Tracking scripts and embedded videos, maps and posts wait until the visitor allows their category.
- Google Consent Mode v2 and the WP Consent API are supported.
- Visitors can change their mind at any time from:
  - the corner cookie button;
  - a `#cookie-settings` menu link;
  - the `[sepo_cookie_settings]` shortcode.
- The banner can be a card, a bar or a centred window, with your wording and the theme's colours in light and dark mode.
- Each choice is recorded with a consent ID, the banner version and a shortened IP address.
  - Records can be downloaded as CSV.
  - Old records are deleted after the period you set.

### Menu and block visibility

- Every menu item and block can be shown to:
  - everyone;
  - signed-in or signed-out visitors;
  - chosen roles;
  - a tier.

  Hidden items can be removed or shown with a lock.
- **CSS classes:** `sp-logged-in`, `sp-guests`, `sp-role-editor`, `sp-tier-gold`, `sp-locked`.
- **Smart menu links:** `#sp-login`, `#sp-register`, `#sp-logout`, `#sp-account`, `#sp-join`.

### Sign-in

- **Social sign-in** with Google, Facebook, GitHub, Microsoft and LinkedIn. It is off until you switch it on.
- **Account matching:**
  - An existing account is matched only on an email address the provider has verified.
  - Creating accounts on first sign-in is a separate setting, off by default, and never gives the administrator role.
- People connect and disconnect providers from their own profile.
- **Two-factor authentication** works with any authenticator app.
  - The QR code is drawn on your own server.
  - Ten one-time recovery codes are shown once.
  - It covers both password and provider sign-ins.
  - Devices can be remembered for a number of days.
  - It can be required for chosen roles.
- Administrators can reset two-factor for someone who has lost their phone. The reset is recorded.
- Client secrets and authenticator secrets are stored encrypted.

## Requirements

| | Minimum |
|---|---|
| WordPress | 6.0 |
| PHP | 8.0 |

## Installation

### Upload the zip (recommended)

1. Download `sepo-wp-bridge.zip`.
2. In your WordPress dashboard, go to **Plugins → Add New Plugin → Upload Plugin**.
3. Choose the zip file and click **Install Now**.
4. Click **Activate**.

### Upload the folder

1. Unzip the download.
2. Copy the `sepo-wp-bridge` folder into `wp-content/plugins/` on your server.
3. Go to **Plugins** and activate **Sepo WordPress Bridge**.

> [!NOTE]
> GitHub's **Code → Download ZIP** names the folder after the branch, for example `sepo-wp-bridge-main`. Rename it to `sepo-wp-bridge` before uploading.

## Getting started

1. Open **WP Bridge** in the admin menu.
2. Work through the tabs:
   - **Features:** switch on what you need.
   - **Post types**, **Taxonomies** and **Meta fields:** build your content structure.
   - **Membership:** set your tiers.
   - **Cookies:** run a scan and switch the banner on.
   - **Sign-in:** set up providers and two-factor.

## Setting up social sign-in

Everything is done on the **WP Bridge → Sign-in** tab. There are no files to edit.

1. Switch sign-in on at the top of the tab.
2. For each provider you want:
   1. Create an app in the provider's developer console.
   2. Add the redirect address shown on the tab, exactly as written. It has a copy button.
   3. Paste the client ID and client secret into the provider's fields.
3. Choose the account options:
   - show the buttons on the login page;
   - match existing accounts by verified email;
   - create new accounts, and the role they get.
4. Optionally, turn on two-factor authentication. Choose the roles that must use it, and how long to remember a device.
5. Click **Save sign-in settings**.

Secrets are encrypted when you save and are never shown again. To change one, paste a new value.

## Shortcodes and blocks

| Shortcode | What it shows |
|---|---|
| `[sepo_field field="price" label="Price:"]` | One field value. |
| `[sepo_fields group="event_details" style="grid"]` | A field group. Options: `style` (`table`, `grid` or `inline`), `title` (`yes` or `no`), `empty` (`yes` lists empty fields), `post` (another post's values). |
| `[sepo_cookie_settings]` | A link that opens the cookie choices. |
| `[sepo_cookie_list]` | The full cookie table and the visitor's current choice, for your cookie policy. |
| `[sepo_social_login]` | The provider buttons. |
| `[sepo_account_security]` | A front-end page for linked accounts and two-factor. |

The blocks are **Post field** and **Field group**. Every block also gets a **Visibility** panel.

## Uninstalling

**Deactivating** the plugin (**Plugins → Deactivate**) stops it. Your settings are kept.

> [!WARNING]
> While the plugin is deactivated, its post types, gates, cookie banner and two-factor prompt stop working. Content behind a members-only gate becomes readable.

**Deleting** the plugin (**Plugins → Deactivate**, then **Delete**) removes the plugin files.

- **Your posts, terms, saved field values and users are never deleted.**
- **By default the settings are kept too**, so a reinstall picks up where you left off.
- **To remove the settings**, first tick **Delete this plugin's settings, post type and field definitions when it is deleted** under **WP Bridge → Membership** and save, then delete the plugin. This removes:
  - feature, post type, taxonomy and field definitions;
  - tiers, user tiers and the members-only and menu visibility settings;
  - the cookie banner settings and consent records;
  - the sign-in settings, provider links and two-factor enrolments.

Accounts created by a provider sign-in are kept.

## Troubleshooting

**A feature locked me out of the dashboard.**
Turn on safe mode by adding this line to `wp-config.php`, or ask your host to add it:

```php
define('SEPO_WB_SAFE_MODE', true);
```

With safe mode on, no features run, including the two-factor prompt. Sign in, fix the setting, then remove the line.

This is an emergency step only. All normal setup is done in the admin screens.

**Someone lost their phone and recovery codes.**
An administrator opens that user under **Users**, finds the sign-in section and resets two-factor.

## For developers

### REST API

All routes are under `/wp-json/sepo-wb/v1/`.

| Route | Access |
|---|---|
| `/features`, `/cpts`, `/taxonomies`, `/metagroups`, `/membership`, with `/save` and `/delete` | `manage_options` |
| `/cookies`, with `/save`, `/list`, `/scan-urls`, `/scan-results`, `/version`, `/log` | `manage_options` |
| `POST /consent` | Public: records a visitor's choice |
| `/signin` | `manage_options` |
| `/account/security`, `/account/2fa/begin\|confirm\|disable\|recovery`, `/account/unlink` | The signed-in user's own account |
| `/users/{id}/2fa/reset` | `edit_user` |
| `/auth/callback/{provider}` | Public: the OAuth redirect address |

### PHP

```php
SepoWB\Integration\FieldDisplay::renderGroup('event_details', $postId);
(new SepoWB\TierStore())->hasLevel($userId, 'gold');
SepoWB\Integration\MembershipGate::passes($postId);
sepo_has_cookie_consent('analytics');
```

### JavaScript

```js
SepoConsent.open();
SepoConsent.has('marketing');
SepoConsent.get();
SepoConsent.on(fn);
document.addEventListener('sepo:consent', handler);
```

To hold back a script of your own until consent is given:

```html
<script type="text/plain" data-sepo-consent="analytics">…</script>
```

Add `data-sepo-allow` to a script or iframe that the banner should never hold back.

### Filters

| Filter | Purpose |
|---|---|
| `sepo_wb_handlers_class` | Use your own Handlers subclass to add features. |
| `sepo_wb_tiers` | Define tiers in code. |
| `sepo_wb_user_has_tier` | Grant tiers from a shop or subscription plugin. |
| `sepo_wb_can_view` | Have the final say on a gated post. |
| `sepo_wb_lock_html` | Change the lock card. |
| `sepo_wb_join_url`, `sepo_wb_account_url` | Change the join and account links. |
| `sepo_wb_post_type_args`, `sepo_wb_taxonomy_args` | Change registration arguments. |
| `sepo_wb_field_allowed` | Let the Post field block print other meta. |
| `sepo_wb_fields_html` | Change a field group's output. |
| `sepo_wb_cookie_services` | Add or change the services the scanner knows. |
| `sepo_wb_cookie_scan_urls` | Choose the pages a scan loads. |
| `sepo_wb_consent_block` | Return `false` to leave a page's scripts alone. |
| `sepo_wb_signin_providers` | Add or change a sign-in provider. |
| `sepo_wb_social_login_allowed` | Return `false` to refuse a provider sign-in. |

### Actions

| Action | When it runs |
|---|---|
| `sepo_wb_social_user_created` | A provider sign-in created an account. |
| `sepo_wb_consent_saved` | A visitor's choice was recorded. |
| `sepo_wb_consent_settings_saved` | The cookie banner settings were saved. |

### Styling field output

Restyle the `.sepo-fields` classes with these custom properties:

- `--sepo-fields-line`
- `--sepo-fields-soft`
- `--sepo-fields-label`
- `--sepo-fields-value`
- `--sepo-fields-radius`
- `--sepo-fields-gap`

### Meta keys

| Object | Keys |
|---|---|
| Posts | `_sp_require_membership`, `_sp_membership_message`, `_sp_membership_redirect` |
| Menu items | `_sp_nav_visibility` |
| Users | `_sepo_wb_tier`, `_sepo_wb_social`, `_sepo_wb_social_<provider>`, `_sepo_wb_2fa`, `_sepo_wb_2fa_counter`, `_sepo_wb_2fa_log` |

## Changelog

### 1.5.1
- Provider client secrets pasted on the Sign-in tab are stored encrypted. Setup is done entirely in the admin.

### 1.5.0
- Sign-in tab with social sign-in from Google, Facebook, GitHub, Microsoft and LinkedIn.
  - State and PKCE checks.
  - Accounts are matched only on verified addresses.
- Two-factor authentication with:
  - an authenticator app and a QR code drawn on the server;
  - recovery codes and remembered devices;
  - required roles.
- `[sepo_social_login]` and `[sepo_account_security]` shortcodes.
- A Sign-in section on the profile screen, a Users column and an administrator reset.
- Personal data export and erase include linked accounts.
- **Restrict REST API** always lets the sign-in callback through.

### 1.4.0
- Field groups shown on the site automatically.
- Field values as sortable admin columns.
- The Field group block and `[sepo_fields]` shortcode.
- Elementor widgets and dynamic tags.
- Fields can be hidden from the site.

### 1.3.0
- Cookies tab, with:
  - a consent banner;
  - a site scanner;
  - held-back scripts and embeds;
  - Google Consent Mode v2 and the WP Consent API;
  - consent records with CSV export.

### 1.2.0
- Styled login page (Branding).

### 1.1.0
- Admin REST API.
- Membership tab and the Members only box.
- Block visibility and the `[sepo_field]` shortcode.
- New field types.
- Many fixes and security improvements.

### 1.0.0
- First release.

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)
