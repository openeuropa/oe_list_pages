<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_sort\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_list_pages\Plugin\ExtraField\Display\ListPageExtraFieldBase;

/**
 * Extra field displaying the sort list form.
 *
 * @ExtraFieldDisplay(
 *   id = "oe_list_page_sort_form",
 *   label = @Translation("List pages sort form"),
 *   deriver = "Drupal\oe_list_pages\Plugin\ExtraField\Derivative\ListPageDeriver",
 * )
 */
class ListPageSortForm extends ListPageExtraFieldBase {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('List page sort form');
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {
    return [
      \Drupal::formBuilder()->getForm('Drupal\oe_list_pages_sort\Form\SortForm'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {
    // We take over the main ::view() method so we don't need this.
  }

}
