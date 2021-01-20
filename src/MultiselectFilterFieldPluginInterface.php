<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

/**
 * Interface of a multiselect filter field plugin.
 */
interface MultiselectFilterFieldPluginInterface {

  /**
   * Gets the default values for the field.
   *
   * Only invoked upon a positive result of the self::applies() method.
   *
   * @return array
   *   The default values array.
   */
  public function getDefaultValues(): array;

  /**
   * Builds and returns the default value form for this plugin.
   *
   * Only invoked upon a positive result of the self::applies() method.
   *
   * @return array
   *   The default value form array.
   */
  public function buildDefaultValueForm(): array;

}
