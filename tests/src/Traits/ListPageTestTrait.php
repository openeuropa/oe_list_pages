<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Helpers for tests that work with the list-page node fields.
 */
trait ListPageTestTrait {

  /**
   * Installs the list page fields on a given node bundle.
   *
   * @param string $bundle
   *   The node bundle id.
   */
  protected function installListPageFields(string $bundle): void {
    if (!FieldStorageConfig::loadByName('node', 'oe_list_page_source')) {
      FieldStorageConfig::create([
        'entity_type' => 'node',
        'field_name' => 'oe_list_page_source',
        'type' => 'string',
        'cardinality' => 1,
        'translatable' => FALSE,
      ])->save();
    }
    if (!FieldStorageConfig::loadByName('node', 'oe_list_page_config')) {
      FieldStorageConfig::create([
        'entity_type' => 'node',
        'field_name' => 'oe_list_page_config',
        'type' => 'string_long',
        'cardinality' => 1,
        'translatable' => TRUE,
      ])->save();
    }

    if (!FieldConfig::loadByName('node', $bundle, 'oe_list_page_source')) {
      FieldConfig::create([
        'entity_type' => 'node',
        'bundle' => $bundle,
        'field_name' => 'oe_list_page_source',
        'label' => 'List Pages source',
        'required' => TRUE,
        'translatable' => FALSE,
      ])->save();
    }
    if (!FieldConfig::loadByName('node', $bundle, 'oe_list_page_config')) {
      FieldConfig::create([
        'entity_type' => 'node',
        'bundle' => $bundle,
        'field_name' => 'oe_list_page_config',
        'label' => 'List Pages configuration',
        'required' => FALSE,
        'translatable' => FALSE,
      ])->save();
    }

    // Place the configuration widget on the bundle's default form display.
    $form_display_storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');
    $form_display = $form_display_storage->load('node.' . $bundle . '.default');
    if (!$form_display) {
      $form_display = $form_display_storage->create([
        'targetEntityType' => 'node',
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $form_display->setComponent('oe_list_page_config', [
      'type' => 'oe_list_pages_configuration',
      'weight' => 1,
      'region' => 'content',
      'settings' => [],
    ]);
    $form_display->removeComponent('oe_list_page_source');
    $form_display->save();
  }

}
