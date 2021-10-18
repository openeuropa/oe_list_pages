<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event thrown in order to determine sort options.
 */
class ListPageSortAlterEvent extends Event {

  /**
   * The entity type.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The sorting options.
   *
   * @var array
   */
  protected $options = [];

  /**
   * Constructs a new ListPageSourceAlterEvent.
   *
   * @param string $entity_type
   *   The The entity type.
   * @param string $bundle
   *   The bundle.
   */
  public function __construct(string $entity_type, string $bundle) {
    $this->entityType = $entity_type;
    $this->bundle = $bundle;
  }

  /**
   * Returns the entity type.
   *
   * @return string
   *   The entity type.
   */
  public function getEntityType(): string {
    return $this->entityType;
  }

  /**
   * Returns the bundle.
   *
   * @return string
   *   The bundle.
   */
  public function getBundle(): string {
    return $this->bundle;
  }

  /**
   * Returns the sort options.
   *
   * @return array
   *   The sort options.
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * Sets the sort information.
   *
   * @param array $options
   *   The sort options.
   */
  public function setOptions(array $options): void {
    $this->options = $options;
  }

}
