<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\search_api\Query\QueryInterface;

/**
 * Interface for search_api processors that are time aware.
 */
interface TimeAwareProcessorInterface {

  /**
   * Adds time based cache tags to a query based on a generated property.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to add the tags to.
   * @param string $property
   *   The property being used.
   */
  public function addTimeCacheTags(QueryInterface $query, string $property): void;

}
