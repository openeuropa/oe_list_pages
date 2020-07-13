<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Traits;

use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;

/**
 * Trait SearchApiTestTrait contains methods for search api capabilities.
 *
 * @package Drupal\Tests\oe_list_pages\Traits
 */
trait SearchApiTestTrait {

  /**
   * Creates a facet for the specified field.
   *
   * @param string $field
   *   The field.
   * @param string $search_id
   *   The search id.
   *
   * @return \Drupal\facets\FacetInterface
   *   The created facet.
   */
  private function createFacet(string $field, string $search_id): FacetInterface {
    $entity = Facet::create([
      // Id just needs to be unique when generated.
      'id' => md5($search_id) . '_' . $field,
      'name' => 'Facet for ' . $field,
    ]);
    $entity->setWidget('links');
    $entity->setFieldIdentifier($field);
    $entity->setEmptyBehavior(['behavior' => 'none']);
    $entity->setFacetSourceId($search_id);
    $entity->save();

    return $entity;
  }

}
