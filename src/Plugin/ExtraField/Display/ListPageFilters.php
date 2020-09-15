<?php

declare(strict_types = 1);

namespace Drupal\oe_List_pages\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Extra field displaying the list page filters.
 *
 * @ExtraFieldDisplay(
 *   id = "oe_list_page_filters",
 *   label = @Translation("List page filters"),
 *   deriver = "Drupal\oe_list_pages\Plugin\ExtraField\Derivative\ListPageDeriver",
 * )
 */
class ListPageFilters extends ListPageBaseFieldDisplay {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('List page filters');
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {
    return [$this->listBuilder->buildFiltersForm($entity)];
  }

}
