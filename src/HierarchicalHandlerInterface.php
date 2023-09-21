<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

/**
 * Defines an interface for list pages filters hierarchical handlers.
 */
interface HierarchicalHandlerInterface {

  /**
   * Gets the hierarchy for an entity id.
   *
   * @param string $id
   *   The entity id.
   *
   * @return array
   *   A list of ids including itself and all the hierarchical parents.
   */
  public function getHierarchy(string $id): array;

}
