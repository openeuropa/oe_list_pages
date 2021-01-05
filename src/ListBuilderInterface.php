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

  /**
   * Builds the list page selected filters.
   *
   * These are the filters that have been selected in the form and are currently
   * filtering the list.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity page.
   *
   * @return array
   *   The filters render array.
   */
  public function buildSelectedFilters(ContentEntityInterface $entity): array;

  /**
   * Builds a descriptive pager information message.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity page.
   *
   * @return array
   *   The pager info render array.
   */
  public function buildPagerInfo(ContentEntityInterface $entity): array;

  /**
   * Builds a RSS link.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity page.
   *
   * @return array
   *   The RSS link render array.
   */
  public function buildRssLink(ContentEntityInterface $entity): array;

}
