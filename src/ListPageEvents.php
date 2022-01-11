<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

/**
 * Contains all events thrown in the oe_list_pages component.
 */
final class ListPageEvents {

  /**
   * Event fired when altering the entity types.
   *
   * @var string
   */
  const ALTER_ENTITY_TYPES = 'oe_list_pages.entity_types_alter';

  /**
   * Event fired when altering the bundles.
   *
   * @var string
   */
  const ALTER_BUNDLES = 'oe_list_pages.bundles_alter';

  /**
   * Event fired after the RSS is built and before it's rendered.
   *
   * @var string
   */
  const ALTER_RSS_BUILD = "oe_list_pages.rss_build_alter";

  /**
   * Event fired after the RSS item is built and before it's rendered.
   *
   * @var string
   */
  const ALTER_RSS_ITEM_BUILD = "oe_list_pages.rss_build_item_alter";


  /**
   * Event fired to determine sort options.
   *
   * @var string
   */
  const ALTER_SORT_OPTIONS = "oe_list_pages.alter_sort_options";

}
