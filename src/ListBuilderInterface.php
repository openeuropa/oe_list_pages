<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the interface for list builders.
 *
 * List builders are responsible for building the rendering element for a given
 * executed list.
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
