<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Form\FormStateInterface;

/**
 * Defines list page configuration subforms.
 */
interface ListPageConfigurationSubformInterface {

  /**
   * Returns the list page configuration.
   *
   * @return \Drupal\oe_list_pages\ListPageConfiguration
   *   The list page configuration.
   */
  public function getConfiguration(): ListPageConfiguration;

  /**
   * Builds the subform.
   *
   * @param array $form
   *   The form element into which to build the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The subform elements.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array;

  /**
   * Submits the subform.
   *
   * @param array $form
   *   The form element into which to subform was built.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void;

}
