<?php

/**
 * @file
 * OE List Pages post updates.
 */

declare(strict_types = 1);

/**
 * Installs the new dependencies.
 */
function oe_list_pages_post_update_0001() {
  \Drupal::service('module_installer')->install([
    'facets',
    'search_api',
  ]);
}
