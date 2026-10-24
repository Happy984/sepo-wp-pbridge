<?php defined('ABSPATH') || exit; ?>
<div class="wrap wb-wrap">
<div class="wb-app" data-wb-manage="<?php echo current_user_can('manage_options') ? '1' : '0'; ?>">
  <header class="wb-head">
    <div class="wb-kicker"><?php esc_html_e('Sepo · WordPress bridge', 'sepo-wp-bridge'); ?></div>
    <h1 class="wb-title"><?php esc_html_e('WordPress configuration', 'sepo-wp-bridge'); ?></h1>
    <p class="wb-sub"><?php esc_html_e('Switch security and performance features on or off, and manage the post types, taxonomies, custom fields and membership tiers this plugin owns.', 'sepo-wp-bridge'); ?></p>
  </header>

  <nav class="wb-tabs" role="tablist" aria-label="<?php esc_attr_e('Sections', 'sepo-wp-bridge'); ?>">
    <?php foreach ($tabs as $slug => $label) : ?>
      <button type="button" role="tab" id="wb-tab-<?php echo esc_attr($slug); ?>" aria-controls="wb-panel-<?php echo esc_attr($slug); ?>"
              class="wb-tab<?php echo $slug === $active ? ' is-on' : ''; ?>" data-wb-tab="<?php echo esc_attr($slug); ?>"
              aria-selected="<?php echo $slug === $active ? 'true' : 'false'; ?>" tabindex="<?php echo $slug === $active ? '0' : '-1'; ?>">
        <?php echo esc_html($label); ?>
      </button>
    <?php endforeach; ?>
  </nav>

  <?php foreach (array_keys($tabs) as $slug) : ?>
    <section class="wb-panel" role="tabpanel" id="wb-panel-<?php echo esc_attr($slug); ?>" aria-labelledby="wb-tab-<?php echo esc_attr($slug); ?>" data-wb-panel="<?php echo esc_attr($slug); ?>" <?php echo $slug !== $active ? 'hidden' : ''; ?>>
      <?php require __DIR__ . '/partials/' . $slug . '.php'; ?>
    </section>
  <?php endforeach; ?>

  <div class="wb-toast" data-wb-toast role="status" aria-live="polite" hidden></div>
</div>
</div>
