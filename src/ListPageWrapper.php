<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Wraps a node carrying the list page configuration.
 */
class ListPageWrapper {

  /**
   * The wrapped entity (host node).
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected ContentEntityInterface $entity;

  /**
   * Constructs a new wrapper.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The host entity (a node).
   */
  public function __construct(ContentEntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Returns the wrapped entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The host entity.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Sets the entity/bundle pair for the list page source.
   *
   * @param string $entity_type
   *   Entity type name.
   * @param string $bundle
   *   Bundle of entity type.
   */
  public function setSource(string $entity_type, string $bundle): void {
    $this->entity->set('oe_list_page_source', $entity_type . ':' . $bundle);
  }

  /**
   * Returns the list page source.
   *
   * @return string|null
   *   The entity_type:bundle pair, or NULL if not set.
   */
  public function getSource(): ?string {
    if (!$this->entity->hasField('oe_list_page_source') || $this->entity->get('oe_list_page_source')->isEmpty()) {
      return NULL;
    }
    return $this->entity->get('oe_list_page_source')->value;
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
    [$entity_type] = explode(':', $source);
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
    [, $bundle] = explode(':', $source);
    return $bundle;
  }

  /**
   * Returns the list page configuration.
   *
   * @return array
   *   The unserialized configuration array.
   */
  public function getConfiguration(): array {
    if (!$this->entity->hasField('oe_list_page_config') || $this->entity->get('oe_list_page_config')->isEmpty()) {
      return [];
    }
    $value = $this->entity->get('oe_list_page_config')->value;
    return $value === NULL ? [] : unserialize($value, ['allowed_classes' => [ListPresetFilter::class]]);
  }

  /**
   * Sets the list page configuration.
   *
   * @param array $configuration
   *   The configuration.
   */
  public function setConfiguration(array $configuration): void {
    $this->entity->set('oe_list_page_config', serialize($configuration));
  }

}
