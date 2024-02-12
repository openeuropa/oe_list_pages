<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines the multiselect filter field plugin annotation.
 *
 * Example definition:
 * @code
 * @MultiselectFilterField(
 *   id = "multiselect_filter_example",
 *   label = @Translation("Multiselect filter example"),
 *   field_types = {
 *     "example_type",
 *   },
 *   weight = 0
 * )
 * @endcode
 *
 * @Annotation
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class MultiselectFilterField extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The Drupal field types this plugin applies to.
   *
   * @var array
   */
  public $field_types;

  /**
   * The Search API data types this plugin applies to.
   *
   * This is optional in case a more specific Drupal field type could be
   * determined on the facet.
   *
   * @var array
   */
  public $data_types;

  /**
   * The Facet IDs this plugin applies to.
   *
   * This is when the plugin needs to be targeted towards a very specific
   * facet.
   *
   * @var array
   */
  public $facet_ids;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The plugin weight.
   *
   * @var int
   */
  public $weight = 0;

}
