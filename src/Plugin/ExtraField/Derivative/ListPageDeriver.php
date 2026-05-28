<?php

namespace Drupal\oe_list_pages\Plugin\ExtraField\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a derivative for every bundle that carries the list page fields.
 */
class ListPageDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs the deriver.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EntityFieldManagerInterface $entityFieldManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_type) {
      if (!($entity_type instanceof ContentEntityTypeInterface) || empty($entity_type->getBundleEntityType())) {
        continue;
      }
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type->id());
      foreach ($bundles as $bundle_id => $bundle_definition) {
        $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type->id(), $bundle_id);
        if (!isset($field_definitions['oe_list_page_source'])) {
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
