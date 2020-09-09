<?php

namespace Drupal\oe_list_pages\Plugin\ExtraField\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creatives a derivative for all content bundles that have oe_list_pages meta.
 */
class ListPageDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity_type.bundle.info service
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Construct the ListPageDeriver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $plugin_id = $base_plugin_definition['id'];

    // Add the original, non-derived, plugin to the list.
    $this->derivatives[$plugin_id] = $base_plugin_definition;

    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_type) {
      if (!($entity_type instanceof ContentEntityTypeInterface) || empty($entity_type->getBundleEntityType())) {
        continue;
      }
      $bundle_entity_storage = $this->entityTypeManager->getStorage($entity_type->getBundleEntityType());
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type->id());
      foreach($bundles as $bundle_id => $bundle_definition) {
        $bundle = $bundle_entity_storage->load($bundle_id);
        $meta_bundles = $bundle->getThirdPartySetting('emr', 'entity_meta_bundles', []);

        if (!in_array('oe_list_page', $meta_bundles)) {
          continue;
        }

        $key = $entity_type->id() . ':' . $bundle_id;
        $this->derivatives[$key] = $base_plugin_definition;
        $this->derivatives[$key]['bundles'] = [$entity_type->id() . '.' . $bundle_id];
        $this->derivatives[$key]['derived'] = TRUE;
      }
    }

    return $this->derivatives;
  }

}
