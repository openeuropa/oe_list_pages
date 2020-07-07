<?php

namespace Drupal\oe_list_pages\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\facets\Utility\FacetsUrlGenerator;
use Drupal\oe_list_pages\Plugin\facets\widget\OeListPagesBaseWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the facets form for lists.
 */
class ListFacetsForm extends FormBase {

  /**
   * The facets manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetsManager;

  /**
   * The facets url generator.
   *
   * @var \Drupal\facets\Utility\FacetsUrlGenerator
   */
  protected $facetsUrlGenerator;

  /**
   * Class constructor.
   */
  public function __construct(DefaultFacetManager $facets_manager, FacetsUrlGenerator $facets_url_generator) {
    $this->facetsManager = $facets_manager;
    $this->facetsUrlGenerator = $facets_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('facets.manager'),
      $container->get('facets.utility.url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_list_pages_facets_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $search_id = NULL) {

    $facets = $this->facetsManager->getFacetsByFacetSourceId($search_id);
    /** @var \Drupal\facets\FacetInterface $facet */
    foreach ($facets as $facet) {
      $form['facets'][$facet->id()] = $this->facetsManager->build($facet);
    }

    $form['search_id'] = [
      '#type' => 'value',
      '#value' => $search_id,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $search_id = $form_state->getValue('search_id');
    $active_filters = [];
    $facets = $this->facetsManager->getFacetsByFacetSourceId($search_id);
    /** @var \Drupal\facets\FacetInterface $facet */
    foreach ($facets as $facet) {
      $widget = $facet->getWidgetInstance();
      if ($widget instanceof OeListPagesBaseWidget) {
        $active_filters[$facet->id()] = $widget->prepareValue($facet, $form, $form_state);
      }
    }
    $url = $this->facetsUrlGenerator->getUrl($active_filters, FALSE);
    $form_state->setRedirectUrl($url);
  }
}