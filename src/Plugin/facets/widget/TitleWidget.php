<?php

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Widget\WidgetPluginBase;

/**
 * The dropdown widget.
 *
 * @FacetsWidget(
 *   id = "oe_list_page_title_widget",
 *   label = @Translation("List page title"),
 *   description = @Translation("A fulltext search widget."),
 * )
 */
class TitleWidget extends OEListPagesBaseWidget {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $active_values = $facet->getActiveItems();
    $build[$facet->id()] = [
      '#type' => 'textfield',
      '#title' => $facet->getName(),
      '#default_value' => $active_values[0] ?? ''
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return 'title_comparison';
  }

}
