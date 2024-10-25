<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages\ListPageSortOptionsResolver;
use Drupal\oe_list_pages\ListSourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Prepares a sort element for list pages.
 */
class ListPageSortForm extends FormBase {

  /**
   * The list page sort options resolver.
   *
   * @var \Drupal\oe_list_pages\ListPageSortOptionsResolver
   */
  protected $sortOptionsResolver;

  /**
   * Constructs a new ListPageSortForm.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\oe_list_pages\ListPageSortOptionsResolver $sortOptionsResolver
   *   The sort options resolver.
   */
  public function __construct(RequestStack $requestStack, ListPageSortOptionsResolver $sortOptionsResolver) {
    $this->requestStack = $requestStack;
    $this->sortOptionsResolver = $sortOptionsResolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('oe_list_pages.sort_options_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_list_pages_sort_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ListPageConfiguration $configuration = NULL, ?ListSourceInterface $list_source = NULL, $current_sort = NULL) {
    $options = $this->sortOptionsResolver->getSortOptions($list_source, ListPageSortOptionsResolver::SCOPE_USER);
    if (count($options) < 2 || !$this->sortOptionsResolver->isExposedSortAllowed($list_source) || !$configuration->isExposedSort()) {
      // We shouldn't show a select element with one option.
      return $form;
    }
    $sort = $current_sort ? ListPageSortOptionsResolver::generateSortMachineName($current_sort) : NULL;
    $form['sort'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#options' => $options,
      '#default_value' => $sort,
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // We do nothing here as we reload the page via Ajax.
  }

  /**
   * Ajax callback for when the user selects a sort option.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The redirect response.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    $sort = $form_state->getValue('sort');
    if ($sort) {
      $sort = explode('__', $sort);
    }

    $url = Url::fromRoute('<current>');
    $query = array_filter($this->requestStack->getCurrentRequest()->query->all(), function ($value, $key) {
      return in_array($key, ['sort', 'f']);
    }, ARRAY_FILTER_USE_BOTH);
    if ($sort) {
      $query['sort'] = [
        'name' => $sort[0],
        'direction' => $sort[1],
      ];
    }

    $url->setOption('query', $query);

    $command = new RedirectCommand($url->toString());
    $response->addCommand($command);
    return $response;
  }

}
