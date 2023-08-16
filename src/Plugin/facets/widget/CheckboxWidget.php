<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Plugin\facets\widget\CheckboxWidget as FacetsCheckboxWidget;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceInterface;

/**
 * The checkbox widget.
 *
 * @FacetsWidget(
 *   id = "oe_list_pages_checkbox",
 *   label = @Translation("List pages checkbox"),
 *   description = @Translation("A checkbox filter widget"),
 * )
 */
class CheckboxWidget extends FacetsCheckboxWidget implements ListPagesWidgetInterface {

  /**
   * {@inheritdoc}
   */
  public function prepareValueForUrl(FacetInterface $facet, array &$form, FormStateInterface $form_state): array {
    if (empty($form_state->getValue($facet->id()))) {
      return [];
    }
    return parent::prepareValueForUrl($facet, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildDefaultValueForm(array $form, FormStateInterface $form_state, FacetInterface $facet, ListPresetFilter $preset_filter = NULL): array {
    return parent::buildDefaultValueForm($form, $form_state, $facet, $preset_filter);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(FacetInterface $facet, ListSourceInterface $list_source, ListPresetFilter $filter): string {
    return parent::getDefaultValuesLabel($facet, $list_source, $filter);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDefaultFilterValue(FacetInterface $facet, array $form, FormStateInterface $form_state): array {
    return parent::prepareDefaultFilterValue($facet, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getValueFromActiveFilters(FacetInterface $facet, string $key): ?string {
    return parent::getValueFromActiveFilters($facet, $key);
  }

}
