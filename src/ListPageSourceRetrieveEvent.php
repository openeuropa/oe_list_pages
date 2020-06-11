<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines a ListPageSourceRetrieveEvent event.
 */
class ListPageSourceRetrieveEvent extends Event implements RefinableCacheableDependencyInterface {

  use RefinableCacheableDependencyTrait;

  /**
   * The list of entity types.
   *
   * @var array
   */
  protected $entityTypeIds;

  /**
   * The list of bundles.
   *
   * @var array
   */
  protected $bundles;

  /**
   * Constructs a new ListPageSourceRetrieveEvent.
   *
   * @param array $entity_type_ids
   *   The list of entity type ids.
   * @param array $bundles
   *   The list of entity bundles.
   */
  public function __construct(array $entity_type_ids, array $bundles = []) {
    $this->entityTypeIds = $entity_type_ids;
    $this->bundles = $bundles;
  }

  /**
   * Returns the allowed entity types.
   *
   * @return string[]
   *   The list of entity types.
   */
  public function getEntityTypes(): array {
    return $this->entityTypeIds;
  }

  /**
   * Set the allowed entity types.
   *
   * @param array $entity_type_ids
   *   The list of entity types.
   */
  public function setEntityTypes(array $entity_type_ids): void {
    $this->entityTypeIds = $entity_type_ids;
  }

  /**
   * Returns the allowed bundles.
   *
   * @return string[]
   *   The list of allowed bundles.
   */
  public function getBundles(): array {
    return $this->bundles;
  }

  /**
   * Set the allowed bundles.
   *
   * @param string $entity_type
   *   The entity type.
   * @param array $bundles
   *   The bundles.
   */
  public function setBundles(string $entity_type, array $bundles): void {
    $this->entityTypeIds = [$entity_type => $entity_type];
    $this->bundles = $bundles;
  }

}
