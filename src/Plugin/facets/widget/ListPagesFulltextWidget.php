<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;

/**
 * The fulltext search widget.
 *
 * @FacetsWidget(
 *   id = "oe_list_pages_fulltext",
 *   label = @Translation("List pages fulltext"),
 *   description = @Translation("A fulltext search widget."),
 * )
 */
class ListPagesFulltextWidget extends ListPagesBaseWidget {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $build[$facet->id()] = [
      '#type' => 'textfield',
      '#title' => $facet->getName(),
      '#default_value' => $this->getValueFromActiveFilters($facet, '0'),
    ];

    $build['#cache']['contexts'] = [
      'url.query_args',
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return 'fulltext_comparison';
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {

    $form['fulltext_all_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fulltext search on all fields'),
      '#default_value' => $this->getConfiguration()['fulltext_all_fields'],
    ];

    return $form;
  }

}
