<?php

/**
 * @file
 * OE List Pages post updates.
 */

declare(strict_types = 1);

use Drupal\facets\Entity\Facet;

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

/**
 * Removes the date_processor_handler from existing facets.
 */
function oe_list_pages_post_update_0003() {
  $facets = Facet::loadMultiple();
  foreach ($facets as $facet) {
    $processors = $facet->getProcessorConfigs();
    if (!isset($processors['date_processor_handler'])) {
      continue;
    }

    $facet->removeProcessor('date_processor_handler');
    $facet->save();
  }
}
