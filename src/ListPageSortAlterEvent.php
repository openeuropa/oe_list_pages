<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Symfony\Contracts\EventDispatcher\Event;

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
   * The scope of the sorting options.
   *
   * The sorting options may differ for when configuring the list page by an
   * admin versus the options presented to users in the frontend. So the two
   * possible options are SCOPE_CONFIGURATION or SCOPE_USER.
   *
   * @var string
   */
  protected $scope = ListPageSortOptionsResolver::SCOPE_CONFIGURATION;

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

  /**
   * Returns the scope.
   *
   * @return string
   *   The scope.
   */
  public function getScope(): string {
    return $this->scope;
  }

  /**
   * Sets the scope.
   *
   * @param string $scope
   *   The scope.
   */
  public function setScope(string $scope): void {
    $this->scope = $scope;
  }

}
