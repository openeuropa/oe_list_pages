<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\rdf_skos\Entity\Concept;

/**
 * Implements list pages hierarchy logic for skos concepts.
 */
class SkosConceptHierarchicalHandler extends BaseHierarchicalHandler implements HierarchicalHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function getHierarchy(string $id): array {
    $skos_concept_storage = $this->entityTypeManager->getStorage('skos_concept');
    $concept = $skos_concept_storage->load($id);
    $children = [$concept->id()];
    $this->getAllChildren($concept, $children);
    return $children;
  }

  /**
   * Gets all chidren from a concept.
   *
   * @param \Drupal\rdf_skos\Entity\Concept $concept
   *   The concept.
   * @param array $children
   *   The existing children path.
   *
   * @return array
   *   The children.
   */
  protected function getAllChildren(Concept $concept, array &$children) {
    $narrower = $concept->getNarrower();

    if (empty($narrower)) {
      return [];
    }

    foreach ($narrower as $child) {
      $children[] = $child->id();
      $this->getAllChildren($child, $children);
    }

    return $children;
  }

}
