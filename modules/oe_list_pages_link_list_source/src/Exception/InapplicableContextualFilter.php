<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_link_list_source\Exception;

/**
 * Exception thrown when a contextual filter is not applicable.
 *
 * Used when a contextual filter is set on a link list but which is for a
 * field that is either empty on the current entity (in the context) or doesn't
 * exist altogether.
 */
class InapplicableContextualFilter extends \Exception {}
