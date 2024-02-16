<?php

declare(strict_types=1);

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
   * @param \Drupal\oe_list_pages\ListPageConfiguration $configuration
   *   The list page configuration.
   *
   * @return array
   *   The list render array.
   */
  public function buildList(ListPageConfiguration $configuration): array;

  /**
   * Builds the list page filters.
   *
   * @param \Drupal\oe_list_pages\ListPageConfiguration $configuration
   *   The list page configuration.
   *
   * @return array
   *   The filters render array.
   */
  public function buildFiltersForm(ListPageConfiguration $configuration): array;

  /**
   * Builds the list page selected filters.
   *
   * These are the filters that have been selected in the form and are currently
   * filtering the list.
   *
   * @param \Drupal\oe_list_pages\ListPageConfiguration $configuration
   *   The list page configuration.
   *
   * @return array
   *   The filters render array.
   */
  public function buildSelectedFilters(ListPageConfiguration $configuration): array;

  /**
   * Builds a descriptive pager information message.
   *
   * @param \Drupal\oe_list_pages\ListPageConfiguration $configuration
   *   The list page configuration.
   *
   * @return array
   *   The pager info render array.
   */
  public function buildPagerInfo(ListPageConfiguration $configuration): array;

  /**
   * Builds a link to the RSS representation of the list page.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   *
   * @return array
   *   The RSS link render array.
   */
  public function buildRssLink(ContentEntityInterface $entity): array;

  /**
   * Builds the sort form element.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The list page entity.
   *
   * @return array
   *   The sort element form.
   */
  public function buildSortElement(ContentEntityInterface $entity): array;

}
