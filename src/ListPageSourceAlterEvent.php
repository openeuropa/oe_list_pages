<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event thrown in order to alter the list source.
 *
 * The entity types and bundles that can be selected for a list source can
 * be altered by subscribing to this event.
 */
class ListPageSourceAlterEvent extends Event {

  /**
   * The list of entity types.
   *
   * @var array
   */
  protected $entityTypes;

  /**
   * The list of bundles.
   *
   * @var array
   */
  protected $bundles;

  /**
   * The list source.
   *
   * @var \Drupal\oe_list_pages\ListSourceInterface
   */
  protected $listSource = NULL;

  /**
   * Constructs a new ListPageSourceAlterEvent.
   *
   * @param array $entity_types
   *   The list of entity type ids.
   * @param array $bundles
   *   The list of entity bundles.
   */
  public function __construct(array $entity_types = [], array $bundles = []) {
    $this->entityTypes = $entity_types;
    $this->bundles = $bundles;
  }

  /**
   * Returns the allowed entity types.
   *
   * @return string[]
   *   The list of entity types.
   */
  public function getEntityTypes(): array {
    return $this->entityTypes;
  }

  /**
   * Set the allowed entity types.
   *
   * @param array $entity_types
   *   The list of entity types.
   */
  public function setEntityTypes(array $entity_types): void {
    $this->entityTypes = $entity_types;
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
    $this->entityTypes = [$entity_type];
    $this->bundles = $bundles;
  }

  /**
   * Returns the list source.
   *
   * @return \Drupal\oe_list_pages\ListSourceInterface|null
   *   The list source if set.
   */
  public function getListSource(): ?ListSourceInterface {
    return $this->listSource;
  }

  /**
   * Sets the list source.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   */
  public function setListSource(ListSourceInterface $list_source): void {
    $this->listSource = $list_source;
  }

}
