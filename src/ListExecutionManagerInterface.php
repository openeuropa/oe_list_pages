<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\EntityInterface;

/**
 * Handles the list pages execution.
 */
interface ListExecutionManagerInterface {

  /**
   * Executes the list associated with an entity with oe_list_page plugin info.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\oe_list_pages\ListExecution
   *   The list execution.
   */
  public function executeList(EntityInterface $entity): ?ListExecution;

}
