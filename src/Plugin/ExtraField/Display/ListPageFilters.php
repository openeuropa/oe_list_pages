<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_list_pages\ListPageConfiguration;

/**
 * Extra field displaying the list page filters.
 *
 * @ExtraFieldDisplay(
 *   id = "oe_list_page_filters",
 *   label = @Translation("List page filters"),
 *   deriver = "Drupal\oe_list_pages\Plugin\ExtraField\Derivative\ListPageDeriver",
 * )
 */
class ListPageFilters extends ListPageExtraFieldBase {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('List page filters');
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {
    $configuration = ListPageConfiguration::fromEntity($entity);
    $form = $this->listBuilder->buildFiltersForm($configuration);
    if (!$form || !isset($form['facets'])) {
      // Return just the cache so we have an empty rendered value in case there
      // are no facets in the form.
      return isset($form['#cache']) ? ['#cache' => $form['#cache']] : [];
    }

    if (!empty($form['#cache']['tags'])) {
      $entity->addCacheTags($form['#cache']['tags']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {
    // We take over the main ::view() method so we don't need this.
  }

}
