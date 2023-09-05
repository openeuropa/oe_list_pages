<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\Result;
use Drupal\oe_list_pages\ListPresetFilter;

/**
 * The checkbox widget.
 *
 * @FacetsWidget(
 *   id = "oe_list_pages_checkbox",
 *   label = @Translation("List pages checkbox"),
 *   description = @Translation("A checkbox filter widget"),
 * )
 */
class MultiselectCheckboxWidget extends MultiselectWidget implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function buildDefaultValueForm(array $form, FormStateInterface $form_state, FacetInterface $facet, ListPresetFilter $preset_filter = NULL): array {
    $form = parent::buildDefaultValueForm($form, $form_state, $facet, $preset_filter);
    // No need to set operator for checkboxes.
    $form['oe_list_pages_filter_operator']['#access'] = FALSE;
    $form['oe_list_pages_filter_operator']['#default_value'] = 'or';

    $list_source = $form_state->get('list_source');
    $plugin_id = $this->multiselectPluginManager->getPluginIdForFacet($facet, $list_source);
    $filter_values = $preset_filter ? $preset_filter->getValues() : [];
    $form[$facet->id()] = $form[$facet->id()][$plugin_id];
    $form[$facet->id()]['#type'] = 'checkboxes';
    $form[$facet->id()]['#default_value'] = array_filter($filter_values);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDefaultFilterValue(FacetInterface $facet, array $form, FormStateInterface $form_state): array {
    echo (__FUNCTION__);
    return [
      'operator' => $form_state->getValue('oe_list_pages_filter_operator'),
      'values' => $form_state->getValue($facet->id()) ?? [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $results = $this->prepareResults($facet->getResults());

    $options = $this->transformResultsToOptions($results);

    if ($options) {
      $build[$facet->id()] = [
        '#type' => 'checkboxes',
        '#title' => $facet->getName(),
        '#options' => $options,
        '#default_value' => $facet->getActiveItems(),
      ];
    }

    $build['#cache']['contexts'] = [
      'url.query_args',
      'url.path',
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareValueForUrl(FacetInterface $facet, array &$form, FormStateInterface $form_state): array {
    return array_filter($form_state->getValue($facet->id()), function ($value) {
      // Remove 0 (integer) values, that are unchecked checkboxes.
      // "0" (string) can be a value (for boolean fields).
      return $value !== 0;
    });
  }

}
