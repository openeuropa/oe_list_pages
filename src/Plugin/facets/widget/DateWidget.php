<?php

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Widget\WidgetPluginBase;

/**
 * The dropdown widget.
 *
 * @FacetsWidget(
 *   id = "oe_list_page_date_widget",
 *   label = @Translation("List page date"),
 *   description = @Translation("A configurable widget that shows a dropdown."),
 * )
 */
class DateWidget extends OEListPagesBaseWidget {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {

    $operators = [
      'gt' => $this->t('Greater than'),
      'lt' => $this->t('Lower than'),
      'bt' => $this->t('In betweent')
    ];

    $build[$facet->id().'_op'] = [
      '#type' => 'select',
      '#title' => $facet->getName(),
      '#options' => $operators,
    ];

    $build[$facet->id().'_date1'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#format' => 'm/d/Y',
    ];

    $build[$facet->id().'_date2'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
      '#format' => 'm/d/Y',
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareValue(FacetInterface $facet, array &$form, FormStateInterface $form_state) {
    $values = [$form_state->getValue($facet->id() . '_op'), $form_state->getValue($facet->id() . '_date1')];
    if ($form_state->getValue($facet->id().'op') == 'bt') {
      $values[] = $form_state->getValue($facet->id() . '_date2');
    }
    return [implode('|', $values)];
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return 'date_comparison';
  }

}
