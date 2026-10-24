<?php defined('ABSPATH') || exit; ?>
<div class="wb-toolbar">
  <input type="search" class="wb-search" data-wb-filter placeholder="<?php esc_attr_e('Filter features…', 'sepo-wp-bridge'); ?>" autocomplete="off">
  <button type="button" class="wb-btn" data-wb-feature-restore><?php esc_html_e('Restore defaults', 'sepo-wp-bridge'); ?></button>
  <button type="button" class="wb-btn wb-btn--primary" data-wb-feature-add><?php esc_html_e('Add feature', 'sepo-wp-bridge'); ?></button>
</div>
<div class="wb-loading" data-wb-loading><?php esc_html_e('Loading…', 'sepo-wp-bridge'); ?></div>
<div class="wb-empty" data-wb-empty hidden><?php esc_html_e('No features registered yet.', 'sepo-wp-bridge'); ?></div>
<div data-wb-list></div>
<p class="wb-note"><?php
/* translators: %s: constant */
printf(esc_html__('Locked yourself out with a feature? Add %s to wp-config.php to skip all features until you fix it.', 'sepo-wp-bridge'), '<code>define(\'SEPO_WB_SAFE_MODE\', true);</code>');
?></p>
