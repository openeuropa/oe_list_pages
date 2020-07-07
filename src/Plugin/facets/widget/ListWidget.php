<?php

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Widget\WidgetPluginBase;

/**
 * The dropdown widget.
 *
 * @FacetsWidget(
 *   id = "oe_list_page_list_widget",
 *   label = @Translation("List page list"),
 *   description = @Translation("A configurable widget that shows a dropdown."),
 * )
 */
class ListWidget extends OEListPagesBaseWidget {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'default_option_label' => 'Choose',
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $options = [];
    $result = $facet->getResults();
    /** @var \Drupal\facets\Result\Result $result_item */
    foreach ($result as $result_item) {
      $options[$result_item->getRawValue()] = $result_item->getDisplayValue();
    }

    $build[$facet->id()] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $facet->getName()
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $config = $this->getConfiguration();

    $message = $this->t('To achieve the standard behavior of a dropdown, you need to enable the facet setting below <em>"Ensure that only one result can be displayed"</em>.');
    $form['warning'] = [
      '#markup' => '<div class="messages messages--warning">' . $message . '</div>',
    ];

    $form += parent::buildConfigurationForm($form, $form_state, $facet);

    $form['default_option_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default option label'),
      '#default_value' => $config['default_option_label'],
    ];

    return $form;
  }

}
