<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\ProcessorInterface;
use Drupal\facets\Widget\WidgetPluginBase;
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
  public function prepareDefaultValueFilter(FacetInterface $facet, array &$form, FormStateInterface $form_state): array {
    return $this->prepareValueForUrl($facet, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(FacetInterface $facet, ListSourceInterface $list_source = NULL, array $filter_value = []): string {
    $active_items = $facet->getActiveItems();
    $facet->setActiveItems($filter_value);
    foreach ($facet->getProcessorsByStage(ProcessorInterface::STAGE_BUILD) as $processor) {
      $results = $processor->build($facet, []);
    }
    $filter_label = [];
    foreach ($filter_value as $value) {
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
  public function buildDefaultValuesWidget(FacetInterface $facet, ListSourceInterface $list_source = NULL, array $parents = []): ?array {
    return $this->build($facet);
  }

  /**
   * {@inheritdoc}
   */
  public function getValueFromActiveFilters(FacetInterface $facet, string $key): ?string {
    $active_filters = $facet->getActiveItems();
    return $active_filters[$key] ?? NULL;
  }

}
