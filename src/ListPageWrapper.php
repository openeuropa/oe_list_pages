<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\emr\EntityMetaWrapper;

/**
 * Wrapper for the list page plugins.
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
    $this->entityMeta->set('list_page_plugin', $entity_type . ':' . $bundle);
    $this->entityMeta->set('list_page_plugin_config', serialize([
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ]));

  }

  /**
   * Returns the plugin configuration.
   *
   * @return array
   *   The plugin configuration.
   */
  public function getListPageConfiguration(): array {
    return $this->entityMeta->get('list_page_plugin_config')->isEmpty() ? [] : unserialize($this->entityMeta->get('list_page_plugin_config')->value);
  }

  /**
   * Sets the plugin configuration.
   *
   * @param array $configuration
   *   The plugin configuration.
   */
  public function setPluginConfiguration(array $configuration): void {
    $this->entityMeta->set('list_page_plugin_config', serialize($configuration));
  }

}
