<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_list_pages\ListPageConfiguration;

/**
 * Extra field displaying the list page results.
 *
 * @ExtraFieldDisplay(
 *   id = "oe_list_page_results",
 *   label = @Translation("List page results"),
 *   deriver = "Drupal\oe_list_pages\Plugin\ExtraField\Derivative\ListPageDeriver",
 * )
 */
class ListPageResults extends ListPageExtraFieldBase {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('List page results');
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {
    $configuration = ListPageConfiguration::fromEntity($entity);
    return [$this->listBuilder->buildList($configuration)];
  }

}
