<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_link_list_source;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface to indicate a search API processor is aware of contextual filters.
 *
 * This allows the processor plugins that index custom values into the index
 * also provide corresponding values from an entity from context.
 */
interface ContextualAwareProcessorInterface {

  /**
   * Given an entity, return contextual filter values.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The filter values.
   */
  public function getContextualValues(ContentEntityInterface $entity): array;

}
