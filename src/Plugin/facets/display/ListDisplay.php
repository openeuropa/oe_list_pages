<?php

namespace Drupal\oe_list_pages\Plugin\search_api\display;

use Drupal\search_api\Display\DisplayPluginBase;

/**
 * Represents a List page display.
 *
 * @SearchApiDisplay(
 *   id = "oe_list_pages",
 *   deriver = "Drupal\oe_list_pages\Plugin\search_api\display\ViewsDisplayDeriver"
 * )
 */
class ListDisplay extends DisplayPluginBase {

  /**
   * {@inheritdoc}
   */
  public function isRenderedInCurrentRequest() {
    return TRUE;
  }

}
