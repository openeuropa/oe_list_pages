<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\ProcessorInterface;
use Drupal\facets\Result\ResultInterface;
use Drupal\facets\Widget\WidgetPluginBase;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceInterface;

/**
 * Base class for facet widgets.
 */
class ListPagesWidgetBase extends WidgetPluginBase implements ListPagesWidgetInterface {

  /**
   * {@inheritdoc}
   */
  public function prepareValueForUrl(FacetInterface $facet, array &$form, FormStateInterface $form_state): array {
    $value = $form_state->getValue($facet->id());
    if (!$value) {
      return [];
    }

    return is_array($value) ? array_values($value) : [$value];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDefaultFilterValue(FacetInterface $facet, array $form, FormStateInterface $form_state): array {
    return [
      'type' => 'static',
      'operator' => ListPresetFilter::OR_OPERATOR,
      'values' => $this->prepareValueForUrl($facet, $form, $form_state),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(FacetInterface $facet, ListSourceInterface $list_source, ListPresetFilter $filter): string {
    // Keep track of the original active items so we can reset them.
    $active_items = $facet->getActiveItems();
    $filter_values = $filter->getValues();
    $facet->setActiveItems($filter_values);
    $results = $this->processFacetResults($facet);

    $filter_label = [];
    foreach ($filter_values as $value) {
      $filter_label[$value] = $value;
      foreach ($results as $result) {
        if ($result->getRawValue() == $value) {
          $filter_label[$value] = $result->getDisplayValue();
        }
      }
    }

    // Reset active items.
    $facet->setActiveItems($active_items);
    return implode(', ', $filter_label);
  }

  /**
   * {@inheritdoc}
   */
  public function buildDefaultValueForm(array $form, FormStateInterface $form_state, FacetInterface $facet, ListPresetFilter $preset_filter = NULL): array {
    return $this->build($facet);
  }

  /**
   * {@inheritdoc}
   */
  public function getValueFromActiveFilters(FacetInterface $facet, string $key): ?string {
    $active_filters = $facet->getActiveItems();
    return $active_filters[$key] ?? NULL;
  }

  /**
   * Processes and returns the results of a given facet.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   *
   * @return array
   *   The results.
   */
  protected function processFacetResults(FacetInterface $facet): array {
    $results = $facet->getResults();
    foreach ($facet->getProcessorsByStage(ProcessorInterface::STAGE_PRE_QUERY) as $processor) {
      $processor->preQuery($facet);
    }

    foreach ($facet->getProcessorsByStage(ProcessorInterface::STAGE_BUILD) as $processor) {
      $results = $processor->build($facet, $results);
    }

    return $results;
  }

  /**
   * Transforms a list of Facet results to selectable options.
   *
   * @param array $results
   *   The results.
   *
   * @return array
   *   The options.
   */
  protected function transformResultsToOptions(array $results): array {
    $options = [];
    array_walk($results, function (ResultInterface &$result) use (&$options) {
      $options[$result->getRawValue()] = $result->getDisplayValue();
    });

    return $options;
  }

}
