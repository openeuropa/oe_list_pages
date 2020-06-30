<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\search_api\Entity\Index;

/**
 * Interface class for ListSourceFactory class.
 */
interface ListSourceFactoryInterface {

  /**
   * Generates search id.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return string
   *   The generated id.
   */
  public function generateSearchId(string $entity_type, string $bundle): string;

  /**
   * Creates a new list source.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param \Drupal\search_api\Entity\Index $index
   *   The Search API Index.
   *
   * @return \Drupal\oe_list_pages\ListSource
   *   The created list source
   */
  public function create(string $entity_type, string $bundle, Index $index): ListSource;

  /**
   * Gets the associated list source with the entity type/bundle.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return \Drupal\oe_list_pages\ListSource|null
   *   The list source if exists.
   */
  public function get(string $entity_type, string $bundle): ?ListSource;

}
