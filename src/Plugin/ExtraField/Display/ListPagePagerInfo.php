<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_list_pages\ListPageConfiguration;

/**
 * Extra field displaying the list page pager.
 *
 * @ExtraFieldDisplay(
 *   id = "oe_list_page_pager_info",
 *   label = @Translation("List page pager info"),
 *   deriver = "Drupal\oe_list_pages\Plugin\ExtraField\Derivative\ListPageDeriver",
 * )
 */
class ListPagePagerInfo extends ListPageExtraFieldBase {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('List page pager info');
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {
    $configuration = ListPageConfiguration::fromEntity($entity);
    return [$this->listBuilder->buildPagerInfo($configuration)];
  }

}
