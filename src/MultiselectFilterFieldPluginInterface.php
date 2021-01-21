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
   * @return array
   *   The default values array.
   */
  public function getDefaultValues(): array;

  /**
   * Builds and returns the default value form for this plugin.
   *
   * @return array
   *   The default value form array.
   */
  public function buildDefaultValueForm(): array;

  /**
   * Returns the label for the filter values set as default values.
   *
   * @return string
   *   The label.
   */
  public function getDefaultValuesLabel(): string;

}
