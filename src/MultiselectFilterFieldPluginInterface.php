<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface of a multiselect filter field plugin.
 */
interface MultiselectFilterFieldPluginInterface {

  /**
   * Gets the default values for the field.
   *
   * @return array
   *   The default values array.
   */
  public function getDefaultValues(): array;

  /**
   * Builds and returns the default value form for this plugin.
   *
   * @return array
   *   The default value form array.
   */
  public function buildDefaultValueForm(): array;

  /**
   * Prepares the default filter values after submission.
   *
   * @param array $values
   *   The initial values taken from the form state in
   *   MultiselectWidget::prepareDefaultFilterValue().
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The prepared values.
   */
  public function prepareDefaultFilterValues(array $values, array $form, FormStateInterface $form_state): array;

  /**
   * Returns the label for the filter values set as default values.
   *
   * @return string
   *   The label.
   */
  public function getDefaultValuesLabel(): string;

  /**
   * Extracts the values from the type of field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The items list.
   *
   * @return array
   *   The values.
   */
  public function getFieldValues(FieldItemListInterface $items): array;

}
