<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_link_list_source;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Maps a contextual filter field to another one.
 *
 * This is used when we have different field names across entity types but they
 * represent the same kind of data and want to have contextual filters being
 * applied based on them.
 */
class ContextualFilterFieldMapper {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * ContextualFilterFieldMapper constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * Returns the corresponding field name on the given entity.
   *
   * The given entity can be a contextual entity and the purpose of this method
   * is to determine what is the field name that should be used to retrieve the
   * values that map to the given field name.
   *
   * @param string $field_name
   *   The given field name.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The contextual entity.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheable_metadata
   *   Cache metadata to bubble up.
   *
   * @return string|null
   *   Returns a field name if found, otherwise NULL.
   */
  public function getCorrespondingFieldName(string $field_name, ContentEntityInterface $entity, CacheableMetadata $cacheable_metadata): ?string {
    // The first priority is the field that has the same name.
    if ($entity->hasField($field_name)) {
      return $field_name;
    }

    // Otherwise, we check the config to see if we have a mapping from this
    // field name to another one.
    $config = $this->configFactory->get('oe_list_pages_link_list_source.contextual_field_map');
    $cacheable_metadata->addCacheableDependency($config);
    $maps = $config->get('maps');
    if (!$maps) {
      $maps = [];
    }

    foreach ($maps as $map) {
      if (isset($map[$field_name]) && $entity->hasField($map[$field_name])) {
        return $map[$field_name];
      }
    }

    return NULL;
  }

}
