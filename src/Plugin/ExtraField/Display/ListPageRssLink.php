<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Extra field displaying the list page pager.
 *
 * @ExtraFieldDisplay(
 *   id = "oe_list_page_rss_link",
 *   label = @Translation("Link to RSS feed"),
 *   deriver = "Drupal\oe_list_pages\Plugin\ExtraField\Derivative\ListPageDeriver",
 * )
 */
class ListPageRssLink extends ListPageExtraFieldBase {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('Link to RSS feed');
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity): array {
    return [$this->listBuilder->buildRssLink($entity)];
  }

}
