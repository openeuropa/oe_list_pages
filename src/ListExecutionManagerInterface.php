<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

/**
 * Defines an interface for list execution managers.
 *
 * List execution managers are responsible for executing the associated Search
 * API source of a given entity, via its entity meta plugin.
 */
interface ListExecutionManagerInterface {

  /**
   * Executes the list associated with an entity with oe_list_page plugin info.
   *
   * Keeps a static cache of the already executed lists.
   *
   * @param \Drupal\oe_list_pages\ListPageConfiguration $configuration
   *   The list page configuration.
   *
   * @return \Drupal\oe_list_pages\ListExecutionResults
   *   The list execution.
   */
  public function executeList(ListPageConfiguration $configuration): ?ListExecutionResults;

}
