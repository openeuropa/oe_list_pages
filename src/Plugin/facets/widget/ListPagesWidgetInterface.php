<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;

/**
 * Interface for list pages widget.
 */
interface ListPagesWidgetInterface {

  /**
   * Prepares the values to be passed to url generator using the submitted form.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The active filters to be handled by url generator.
   */
  public function prepareValueForUrl(FacetInterface $facet, array &$form, FormStateInterface $form_state): array;

  /**
   * Get active filters for the facet.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param string $key
   *   The key.
   *
   * @return string|null
   *   The value from active filters.
   */
  public function getValueFromActiveFilters(FacetInterface $facet, string $key):  ?string;

}
