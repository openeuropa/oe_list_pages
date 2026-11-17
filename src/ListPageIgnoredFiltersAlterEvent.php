<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event fired on List page form build preparation to update ignored filters.
 */
class ListPageIgnoredFiltersAlterEvent extends Event {

  /**
   * The list source.
   *
   * @var \Drupal\oe_list_pages\ListSourceInterface
   */
  protected $listSource;

  /**
   * The list of ignored filters.
   *
   * @var string[]
   */
  protected $ignoredFilters;

  /**
   * ListPageFormBuilderEvent constructor.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param array $ignored_filters
   *   The list of ignored filters.
   */
  public function __construct(ListSourceInterface $list_source, array $ignored_filters) {
    $this->listSource = $list_source;
    $this->ignoredFilters = $ignored_filters;
  }

  /**
   * Set list source.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $listSource
   *   The list source.
   */
  public function setListSource(ListSourceInterface $listSource): void {
    $this->listSource = $listSource;
  }

  /**
   * Return the facet object.
   *
   * @return \Drupal\facets\FacetInterface
   *   The facet object instance.
   */
  public function getListSource(): ListSourceInterface {
    return $this->listSource;
  }

  /**
   * Set ignored filters.
   *
   * @param array $ignoredFilters
   *   The ignored filters;.
   */
  public function setIgnoredFilters(array $ignoredFilters): void {
    $this->ignoredFilters = $ignoredFilters;
  }

  /**
   * Returns the list of ignored filters.
   *
   * @return array|string[]
   *   The list of ignored filters;
   */
  public function getIgnoredFilters(): array {
    return $this->ignoredFilters;
  }

}
