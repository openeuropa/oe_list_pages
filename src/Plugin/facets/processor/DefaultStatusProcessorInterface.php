<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\processor;

/**
 * Used by facets processors that set a default value on the facet.
 *
 * Processors that implement this interface need to ensure they have a
 * configuration value, keyed as "default_status".
 */
interface DefaultStatusProcessorInterface {}
