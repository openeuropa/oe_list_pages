<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

/**
 * Contains all events thrown in the oe_list_pages component.
 */
final class ListPageEvents {

  /**
   * Name of the event fired on retrieving allowed entity types.
   *
   * @var string
   */
  const ALTER_ALLOWED_ENTITY_TYPES = 'oe_list_pages.allowed_entity_types_alter';

  /**
   * Name of the event fired on retrieving allowed bundles.
   *
   * @var string
   */
  const ALTER_ALLOWED_BUNDLES = 'oe_list_pages.allowed_bundles_alter';

}
