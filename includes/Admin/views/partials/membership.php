<?php defined('ABSPATH') || exit; ?>
<div class="wb-loading" data-wb-loading><?php esc_html_e('Loading…', 'sepo-wp-bridge'); ?></div>
<form class="wb-membership" data-wb-membership hidden>
  <h2 class="wb-h2"><?php esc_html_e('Tiers', 'sepo-wp-bridge'); ?></h2>
  <p class="wb-help"><?php esc_html_e('A higher rank includes every lower one: Gold members can read Silver posts. Give users a tier on their profile (Users → Edit).', 'sepo-wp-bridge'); ?></p>
  <table class="wb-table wb-tiers">
    <thead><tr>
      <th><?php esc_html_e('Name', 'sepo-wp-bridge'); ?></th>
      <th><?php esc_html_e('Id', 'sepo-wp-bridge'); ?></th>
      <th><?php esc_html_e('Rank', 'sepo-wp-bridge'); ?></th>
      <th><?php esc_html_e('Members', 'sepo-wp-bridge'); ?></th>
      <th></th>
    </tr></thead>
    <tbody data-wb-tiers></tbody>
  </table>
  <p><button type="button" class="wb-btn" data-wb-tier-add><?php esc_html_e('Add tier', 'sepo-wp-bridge'); ?></button></p>

  <h2 class="wb-h2"><?php esc_html_e('Site default', 'sepo-wp-bridge'); ?></h2>
  <div class="wb-grid2">
    <label class="wb-field"><?php esc_html_e('Posts with no tier of their own need', 'sepo-wp-bridge'); ?>
      <select data-wb-default-tier></select>
    </label>
    <fieldset class="wb-field"><legend><?php esc_html_e('Applies to', 'sepo-wp-bridge'); ?></legend><div class="wb-checks" data-wb-gate-types></div></fieldset>
  </div>
  <p class="wb-help"><?php esc_html_e('Each post can override this in its "Members only" box. Pages are best left out so your join and login pages stay open.', 'sepo-wp-bridge'); ?></p>

  <h2 class="wb-h2"><?php esc_html_e('Links', 'sepo-wp-bridge'); ?></h2>
  <div class="wb-grid2">
    <label class="wb-field"><?php esc_html_e('Join / pricing page', 'sepo-wp-bridge'); ?>
      <input type="url" data-wb-join placeholder="<?php echo esc_attr(home_url('/membership')); ?>">
    </label>
    <label class="wb-field"><?php esc_html_e('Account page', 'sepo-wp-bridge'); ?>
      <input type="url" data-wb-account placeholder="<?php echo esc_attr(admin_url('profile.php')); ?>">
    </label>
  </div>
  <p class="wb-help"><?php esc_html_e('Used by lock cards and by menu links pointing to #sp-join and #sp-account. Menu links to #sp-login, #sp-register and #sp-logout also work.', 'sepo-wp-bridge'); ?></p>

  <h2 class="wb-h2"><?php esc_html_e('Uninstall', 'sepo-wp-bridge'); ?></h2>
  <label class="wb-field wb-field--check"><input type="checkbox" data-wb-delete-data> <span><?php esc_html_e('Delete this plugin’s settings, post type and field definitions when it is deleted', 'sepo-wp-bridge'); ?></span></label>
  <p class="wb-help"><?php esc_html_e('Posts, terms and saved field values are never deleted.', 'sepo-wp-bridge'); ?></p>

  <p class="wb-actions"><button type="submit" class="wb-btn wb-btn--primary"><?php esc_html_e('Save membership settings', 'sepo-wp-bridge'); ?></button></p>
</form>
