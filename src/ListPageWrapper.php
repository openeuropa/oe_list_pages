<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\emr\EntityMetaWrapper;

/**
 * Wrapper for the list page entity meta.
 */
class ListPageWrapper extends EntityMetaWrapper {

  /**
   * Set the entity/bundle pair for the list page source.
   *
   * @param string $entity_type
   *   Entity type name.
   * @param string $bundle
   *   Bundle of entity type.
   */
  public function setSource(string $entity_type, string $bundle): void {
    $this->entityMeta->set('oe_list_page_source', $entity_type . ':' . $bundle);
  }

  /**
   * Returns the entity meta list page source.
   *
   * This is a pair of entity_type:bundle that will be used for querying on this
   * list page.
   *
   * @return string|null
   *   The entity_type:bundle pair source.
   */
  public function getSource(): ?string {
    return $this->entityMeta->get('oe_list_page_source')->value;
  }

  /**
   * Returns the entity type used as the source.
   *
   * @return string|null
   *   The entity type.
   */
  public function getSourceEntityType(): ?string {
    $source = $this->getSource();
    if (!$source) {
      return NULL;
    }

    list($entity_type, $bundle) = explode(':', $source);
    return $entity_type;
  }

  /**
   * Returns the bundle used in the source.
   *
   * @return string|null
   *   The bundle.
   */
  public function getSourceEntityBundle(): ?string {
    $source = $this->getSource();
    if (!$source) {
      return NULL;
    }

    list($entity_type, $bundle) = explode(':', $source);
    return $bundle;
  }

  /**
   * Returns the entity meta configuration.
   *
   * @return array
   *   The list page configuration.
   */
  public function getConfiguration(): array {
    return $this->entityMeta->get('oe_list_page_config')->isEmpty() ? [] : unserialize($this->entityMeta->get('oe_list_page_config')->value);
  }

  /**
   * Sets the entity meta configuration.
   *
   * @param array $configuration
   *   The list page configuration.
   */
  public function setConfiguration(array $configuration): void {
    $this->entityMeta->set('oe_list_page_config', serialize($configuration));
  }

}
