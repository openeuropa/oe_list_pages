<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Handles the list pages building.
 */
interface ListBuilderInterface {

  /**
   * Builds the list content to be rendered.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   *
   * @return array
   *   The list render array.
   */
  public function buildList(ContentEntityInterface $entity): array;

  /**
   * Builds the list page filters.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity page.
   *
   * @return array
   *   The filters render array.
   */
  public function buildFiltersForm(ContentEntityInterface $entity): array;

}
