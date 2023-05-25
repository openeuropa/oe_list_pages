<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event thrown to see if exposing of the frontend sort is disallowed.
 */
class ListPageDisallowSortEvent extends Event {

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
   * Whether the exposing of frontend sorting is disallowed.
   *
   * @var bool
   */
  protected $disallowed = FALSE;

  /**
   * Constructs a new ListPageDisallowSortEvent.
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
   * Checks whether sorting is disallowed.
   *
   * @return bool
   *   Whether exposed sorting is disallowed.
   */
  public function isDisallowed(): bool {
    return $this->disallowed;
  }

  /**
   * Allows exposed sorting.
   */
  public function allow(): void {
    $this->disallowed = FALSE;
  }

  /**
   * Disallows exposed sorting.
   */
  public function disallow(): void {
    $this->disallowed = TRUE;
  }

}
