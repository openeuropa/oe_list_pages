<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Traits;

use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;

/**
 * Contains helpful methods for testing the list pages functionality.
 */
trait FacetsTestTrait {

  /**
   * Generate a valid facet it.
   *
   * @param string $field
   *   The field.
   * @param string $search_id
   *   The search id.
   * @param string $suffix
   *   The suffix.
   *
   * @return string
   *   The facet id.
   */
  protected function generateFacetId($field, $search_id, $suffix = ''): string {
    return str_replace(':', '_', $search_id . $field . $suffix);
  }

  /**
   * Creates a facet for the specified field.
   *
   * @param string $field
   *   The field.
   * @param string $search_id
   *   The search id.
   * @param string $suffix
   *   The suffix.
   * @param string $widget_id
   *   The widget id.
   * @param array $widget_config
   *   The widget config.
   *
   * @return \Drupal\facets\FacetInterface
   *   The created facet.
   */
  protected function createFacet(string $field, string $search_id, string $suffix = '', string $widget_id = '', array $widget_config = []): FacetInterface {
    $facet_id = $this->generateFacetId($field, $search_id, $suffix);
    $entity = Facet::create([
      'id' => $facet_id,
      'name' => 'Facet for ' . $field,
    ]);
    $entity->setUrlAlias($facet_id);
    $entity->setFieldIdentifier($field);
    $entity->setEmptyBehavior(['behavior' => 'none']);
    $entity->setFacetSourceId($search_id);
    if (!empty($widget_id)) {
      $entity->setWidget($widget_id, $widget_config);
    }
    else {
      $entity->setWidget('links', ['show_numbers' => TRUE]);
    }

    $entity->addProcessor([
      'processor_id' => 'url_processor_handler',
      'weights' => ['pre_query' => -10, 'build' => -10],
      'settings' => [],
    ]);
    $entity->save();

    return $entity;
  }

}
