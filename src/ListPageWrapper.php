<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\emr\EntityMetaWrapper;

/**
 * Wrapper for the list page.
 */
class ListPageWrapper extends EntityMetaWrapper {

  /**
   * Set the entity/bundle pair.
   *
   * @param string $entity_type
   *   Entity type name.
   * @param string $bundle
   *   Bundle of entity type.
   */
  public function setListPageSource(string $entity_type, string $bundle): void {
    $this->entityMeta->set('oe_list_pages_source', $entity_type . ':' . $bundle);
    $this->entityMeta->set('oe_list_pages_config', serialize([
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ]));

  }

  /**
   * Returns the entity meta configuration.
   *
   * @return array
   *   The configuration.
   */
  public function getListPageConfiguration(): array {
    return $this->entityMeta->get('oe_list_pages_config')->isEmpty() ? [] : unserialize($this->entityMeta->get('oe_list_pages_config')->value);
  }

  /**
   * Sets the entity meta configuration.
   *
   * @param array $configuration
   *   The plugin configuration.
   */
  public function setPluginConfiguration(array $configuration): void {
    $this->entityMeta->set('oe_list_pages_config', serialize($configuration));
  }

}
