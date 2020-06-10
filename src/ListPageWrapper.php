<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\emr\EntityMetaWrapper;

/**
 * Wrapper for the list page.
 */
class ListPageWrapper extends EntityMetaWrapper {

  /**
   * Set the entity/bundle pair for source.
   *
   * @param string $entity_type
   *   Entity type name.
   * @param string $bundle
   *   Bundle of entity type.
   */
  public function setListPageSource(string $entity_type, string $bundle): void {
    $this->entityMeta->set('oe_list_page_source', $entity_type . ':' . $bundle);
  }

  /**
   * Returns the entity meta source.
   *
   * @return string
   *   The configuration.
   */
  public function getListPageSource(): ?string {
    return $this->entityMeta->get('oe_list_page_source')->value;
  }

  /**
   * Returns the entity meta configuration.
   *
   * @return array
   *   The configuration.
   */
  public function getListPageConfiguration(): array {
    return $this->entityMeta->get('oe_list_page_config')->isEmpty() ? [] : unserialize($this->entityMeta->get('oe_list_page_config')->value);
  }

  /**
   * Sets the entity meta configuration.
   *
   * @param array $configuration
   *   The plugin configuration.
   */
  public function setListPageConfiguration(array $configuration): void {
    $this->entityMeta->set('oe_list_page_config', serialize($configuration));
  }

}
