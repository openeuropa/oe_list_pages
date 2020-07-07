<?php

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Widget\WidgetPluginBase;

class OeListPagesBaseWidget extends WidgetPluginBase {

  /**
   * {@inheritdoc}
   */
  public function prepareValue(FacetInterface $facet, array &$form, FormStateInterface $form_state) {
    return $active_filters[$facet->id()] = [$form_state->getValue($facet->id())];
  }
}
