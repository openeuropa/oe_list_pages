<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines the multiselect filter field plugin annotation.
 *
 * Example definition:
 * @code
 * @MultiselectFieldFilter(
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
class MultiselectFieldFilter extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The field types this plugin applies to.
   *
   * @var array
   */
  public $fieldTypes;

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
