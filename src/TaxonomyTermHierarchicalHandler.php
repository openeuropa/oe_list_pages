<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\taxonomy\TermInterface;

/**
 * Implements list pages hierarchy logic for taxonomy terms.
 */
class TaxonomyTermHierarchicalHandler extends BaseHierarchicalHandler implements HierarchicalHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function getHierarchy(string $id): array {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $term = $term_storage->load($id);
    $children = [$term->id()];
    $this->getAllChildren($term, $children);
    return $children;
  }

  /**
   * Gets all children from a taxonomy term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term.
   * @param array $children
   *   The existing children path.
   *
   * @return array
   *   The children.
   */
  protected function getAllChildren(TermInterface $term, array &$children) {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $narrower = $term_storage->getChildren($term);

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
