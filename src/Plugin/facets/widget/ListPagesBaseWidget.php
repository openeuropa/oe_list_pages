<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Widget\WidgetPluginBase;

/**
 * Base class for facet widgets.
 */
class ListPagesBaseWidget extends WidgetPluginBase implements ListPagesWidgetInterface {

  /**
   * {@inheritdoc}
   */
  public function prepareValueForUrl(FacetInterface $facet, array &$form, FormStateInterface $form_state): array {
    return [$form_state->getValue($facet->id())];
  }

  /**
   * {@inheritdoc}
   */
  public function getValueFromActiveFilters(FacetInterface $facet, string $key): ?string {
    $active_filters = $facet->getActiveItems();
    return $active_filters[$key] ?? NULL;
  }

}
