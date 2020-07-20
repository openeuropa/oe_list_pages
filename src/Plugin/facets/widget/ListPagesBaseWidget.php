<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\facets\FacetInterface;
use Drupal\facets\Widget\WidgetPluginBase;

/**
 * Base class for facet widgets.
 */
class ListPagesBaseWidget extends WidgetPluginBase {

  /**
   * {@inheritdoc}
   */
  public function activeFilters(FacetInterface $facet) {
    $urlProcessorManager = \Drupal::service('plugin.manager.facets.url_processor');
    $url_processor = $urlProcessorManager->createInstance($facet->getFacetSourceConfig()->getUrlProcessorName(), ['facet' => $facet]);
    $active_filters = $url_processor->getActiveFilters();

    if (isset($active_filters[''])) {
      unset($active_filters['']);
    }
    return $active_filters[$facet->id()][0] ?? NULL;
  }

}
