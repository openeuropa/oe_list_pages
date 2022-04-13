<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_sort\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configurable search form class.
 */
class SortForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $config = NULL): array {
    $form_state->set('oe_list_page_sort_config', $config);
    $input_value = '';

    if (!empty($config['input']['name'])) {
      $input_value = $this->getRequest()->get($config['input']['name']);
    }

    $form['list_page_sort'] = [
      '#prefix' => '<div class="bcl-search-form__group">',
      '#suffix' => '</div>',
      '#type' => 'select',
      '#options' => [
        'az' => 'A-Z',
        'za' => 'Z-A',
      ],
      '#title' => $this->t('Sort by'),
      '#size' => 20,
      '#margin_class' => 'mb-0',
      '#attributes' => [
        'placeholder' => $config['input']['placeholder'],
        'class' => [
          $config['input']['classes'],
          'rounded-0',
          'rounded-start',
        ],
      ],
      '#default_value' => $input_value,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $form_state->get('oe_list_page_sort_config');
    $url = Url::fromUri('base:' . $config['form']['action'], [
      'language' => $this->languageManager->getCurrentLanguage(),
      'absolute' => TRUE,
      'query' => [
        'sort_by' => $form_state->getValue('search_input'),
      ],
    ]);
    $form_state->setRedirectUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'oe_list_pages_sort_form';
  }

}
