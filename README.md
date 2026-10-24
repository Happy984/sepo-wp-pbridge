---
Title: Sepo WordPress Bridge
Contributors: Euneolink Solutions
Tags: custom post types, custom fields, membership, security, performance
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Description: One panel for feature toggles, custom post types, taxonomies, custom fields, membership tiers and menu/block visibility. No outside dependency. Everything lives under **WP Bridge** in the admin menu, in five tabs
---

* **Features** — security, performance, admin and media switches, grouped by category, each with a plain description. Some have settings (login attempts and lock-out minutes, password length, rate limit, roles, JPEG quality, REST namespaces left open). Add your own by extending the `SepoWB\Features\Handlers` class.
* **Post types** — singular/plural names, slug, address, menu icon, what the editing screen shows, public/archive/hierarchical.
* **Taxonomies** — categories (nested) or tags (flat), attached to any post types.
* **Meta fields** — groups of fields shown as a box on the editing screen: text, paragraph, number, yes/no, choice list, email, link, date. Values are saved as post meta, registered for the REST API and block editor, and can be shown with the **Post field** block or `[sepo_field field="price" label="Price:"]`.
* **Styled login page** — a clean single-card login in your colours and logo (Features → Branding).
* **Membership** — ranked tiers (Gold includes Silver), a site-wide default tier for chosen post types, join and account links.

Membership in use:

* Each post has a **Members only** box: who can read it, the message locked-out readers see, or a page to send them to.
* Each user has a **Tier** on their profile; the Users list shows it.
* Locked posts show a lock card instead of the content. The content is also removed from excerpts, feeds, the REST API and comments. Anyone who can edit the post still sees it.

Menu and block visibility:

* Each menu item (Appearance → Menus) has a **Visibility** box: everyone, signed-in or signed-out visitors, roles, and a tier — hidden, or shown with a lock. Rules apply to the item's sub-menu too.
* Each block has a **Visibility** panel in the block editor with the same choices.
* CSS classes work too: `sp-logged-in`, `sp-guests`, `sp-role-editor`, `sp-tier-gold`, `sp-locked`.
* Smart menu links: `#sp-login`, `#sp-register`, `#sp-logout`, `#sp-account`, `#sp-join` (login/register show only to visitors; logout/account only to signed-in users).

## Installation

1. Upload the `sepo-wp-bridge` folder to `wp-content/plugins/`, or upload the zip under Plugins → Add New.
2. Activate it.
3. Open **WP Bridge** in the admin menu.

If a feature ever locks you out, add `define('SEPO_WB_SAFE_MODE', true);` to wp-config.php. No features run until you remove it.

## For developers

* REST API (manage_options): `/wp-json/sepo-wb/v1/features`, `/cpts`, `/taxonomies`, `/metagroups`, `/membership`, with `/save` and `/delete` sub-routes.
* PHP: `(new SepoWB\TierStore())->hasLevel($userId, 'gold')`, `SepoWB\Integration\MembershipGate::passes($postId)`.
* Filters:
  * `sepo_wb_handlers_class` — your own Handlers subclass.
  * `sepo_wb_tiers` — tiers in code.
  * `sepo_wb_user_has_tier` — grant tiers from a shop or subscription plugin.
  * `sepo_wb_can_view` — final say on a gated post.
  * `sepo_wb_lock_html` — the lock card.
  * `sepo_wb_join_url` and `sepo_wb_account_url` — the join and account links.
  * `sepo_wb_post_type_args` and `sepo_wb_taxonomy_args` — registration arguments.
  * `sepo_wb_field_allowed` — let the Post field block print other meta.
* Meta keys:
  * Posts: `_sp_require_membership`, `_sp_membership_message`, `_sp_membership_redirect`.
  * Menu items: `_sp_nav_visibility`.
  * Users: `_sepo_wb_tier`.

## Uninstall 

Deleting the plugin removes its settings only if "Delete this plugin's settings…" is ticked under WP Bridge → Membership. Posts, terms and saved field values are never deleted.
