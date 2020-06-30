<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

/**
 * Defines the interface for ListSourceInterface factories.
 */
interface ListSourceFactoryInterface {

  /**
   * Generates facet source plugin id.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return string
   *   The generated id.
   */
  public static function generateFacetSourcePluginId(string $entity_type, string $bundle): string;

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
