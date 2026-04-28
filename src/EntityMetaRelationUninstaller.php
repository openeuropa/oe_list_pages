<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Field\FieldException;

/**
 * Uninstalls an entity meta type from a content entity type.
 */
class EntityMetaRelationUninstaller {

  /**
   * Uninstalls an entity meta type from a content entity type / bundles.
   *
   * @param string $entity_meta_type
   *   The entity meta type id.
   * @param string $entity_type
   *   The host entity type id.
   * @param array $bundles
   *   The host bundle ids it was installed on.
   */
  public static function uninstallEntityMetaTypeOnContentEntityType(string $entity_meta_type, string $entity_type, array $bundles = []): void {
    $definition = \Drupal::entityTypeManager()->getDefinition($entity_type);

    $field_config = \Drupal::entityTypeManager()
      ->getStorage('field_config')
      ->load('entity_meta_relation.node_meta_relation.emr_meta_revision');
    if (!$field_config) {
      throw new FieldException("Field config 'entity_meta_relation.node_meta_relation.emr_meta_revision' not found. Without this field, we cannot properly uninstall");
    }

    $handler_settings = $field_config->getSetting('handler_settings');
    $target_bundles = array_filter($handler_settings['target_bundles'], function ($bundle) use ($entity_meta_type) {
      return $bundle !== $entity_meta_type;
    });

    $handler_settings['target_bundles'] = $target_bundles;
    $field_config->setSetting('handler_settings', $handler_settings);
    $field_config->save();

    $bundle_entity_storage = \Drupal::entityTypeManager()->getStorage($definition->getBundleEntityType());
    foreach ($bundles as $bundle_id) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityBundleBase $bundle */
      $bundle = $bundle_entity_storage->load($bundle_id);
      if (!$bundle) {
        continue;
      }
      $entity_meta_bundles = $bundle->getThirdPartySetting('emr', 'entity_meta_bundles');
      if (empty($entity_meta_bundles) || !in_array($entity_meta_type, $entity_meta_bundles)) {
        continue;
      }

      $entity_meta_bundles = array_filter($entity_meta_bundles, function ($entity_meta_bundle) use ($entity_meta_type) {
        return $entity_meta_type !== $entity_meta_bundle;
      });
      $bundle->setThirdPartySetting('emr', 'entity_meta_bundles', $entity_meta_bundles);
      $bundle->save();
    }
  }

}
