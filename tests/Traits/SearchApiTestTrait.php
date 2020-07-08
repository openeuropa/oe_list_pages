<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Traits;

use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;

/**
 * Trait SearchApiTestTrait for usage in Kernel and Functional tests.
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

  /**
   * Get available options of select box.
   *
   * @param string $field
   *   The label, id or name of select box.
   *
   * @return array
   *   Select box options.
   */
  protected function getSelectOptions(string $field): array {
    $page = $this->getSession()->getPage();
    $options = $page->findField($field)->findAll('css', 'option');
    $actual_options = [];
    foreach ($options as $option) {
      $actual_options[$option->getValue()] = $option->getText();
    }
    return $actual_options;
  }

}
