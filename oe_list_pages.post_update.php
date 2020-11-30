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

/**
 * Installs Multivalue Form Element.
 */
function oe_list_pages_post_update_0002() {
  \Drupal::service('module_installer')->install([
    'multivalue_form_element',
  ]);
}
