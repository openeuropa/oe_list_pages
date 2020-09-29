<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Widget\WidgetPluginBase;

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
  public function buildDefaultValuesWidget(FacetInterface $facet, array $parents = []): ?array {
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
