<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_open_vocabularies\Event;

/**
 * Update Search API configuration.
 *
 * @internal
 */
final class SearchApiConfigurationEvents {

  /**
   * Event update search API facet configuration.
   */
  const UPDATE_SEARCH_API_FACET = 'oe_list_pages_open_vocabularies.update_search_api_facet_config';

}
