<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\EntityInterface;

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
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\oe_list_pages\ListExecutionResults
   *   The list execution.
   */
  public function executeList(EntityInterface $entity): ?ListExecutionResults;

}
