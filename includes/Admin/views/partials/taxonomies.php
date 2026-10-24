<?php defined('ABSPATH') || exit; ?>
<div class="wb-toolbar">
  <input type="search" class="wb-search" data-wb-filter placeholder="<?php esc_attr_e('Filter taxonomies…', 'sepo-wp-bridge'); ?>" autocomplete="off">
  <button type="button" class="wb-btn wb-btn--primary" data-wb-add><?php esc_html_e('Add taxonomy', 'sepo-wp-bridge'); ?></button>
</div>
<div class="wb-loading" data-wb-loading><?php esc_html_e('Loading…', 'sepo-wp-bridge'); ?></div>
<div class="wb-empty" data-wb-empty hidden><?php esc_html_e('No custom taxonomies yet.', 'sepo-wp-bridge'); ?></div>
<div data-wb-list></div>
