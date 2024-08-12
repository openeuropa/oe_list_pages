<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\facets\Exception\InvalidQueryTypeException;
use Drupal\facets\FacetInterface;
use Drupal\facets\Utility\FacetsUrlGenerator;
use Drupal\oe_list_pages\ListFacetManagerWrapper;
use Drupal\oe_list_pages\ListSourceInterface;
use Drupal\oe_list_pages\Plugin\facets\widget\ListPagesWidgetInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes the facets of a given search for a list page.
 */
class ListFacetsForm extends FormBase {

  /**
   * The facets manager.
   *
   * @var \Drupal\oe_list_pages\ListFacetManagerWrapper
   */
  protected $facetsManager;

  /**
   * The facets url generator.
   *
   * @var \Drupal\facets\Utility\FacetsUrlGenerator
   */
  protected $facetsUrlGenerator;

  /**
   * Constructs an instance of ListFacetsForm.
   *
   * @param \Drupal\oe_list_pages\ListFacetManagerWrapper $facets_manager
   *   The facets manager.
   * @param \Drupal\facets\Utility\FacetsUrlGenerator $facets_url_generator
   *   The facets url generator.
   */
  public function __construct(ListFacetManagerWrapper $facets_manager, FacetsUrlGenerator $facets_url_generator) {
    $this->facetsManager = $facets_manager;
    $this->facetsUrlGenerator = $facets_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_list_pages.list_facet_manager_wrapper'),
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
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function buildForm(array $form, FormStateInterface $form_state, ListSourceInterface $list_source = NULL, array $ignored_filters = []) {
    if (!$list_source) {
      return [];
    }

    $cache = new CacheableMetadata();
    $cache->addCacheTags(['config:facets_facet_list']);

    $source_id = $list_source->getSearchId();

    /** @var \Drupal\facets\FacetInterface[] $facets */
    $facets = $this->facetsManager->getFacetsByFacetSourceId($source_id, $list_source->getIndex());
    if (!$facets) {
      $cache->applyTo($form);
      return $form;
    }

    // Sort facets by weight.
    uasort($facets, function (FacetInterface $a, FacetInterface $b) {
      if ($a->getWeight() == $b->getWeight()) {
        return 0;
      }
      return ($a->getWeight() < $b->getWeight()) ? -1 : 1;
    });

    foreach ($facets as $facet) {
      try {
        // Check that we are able to determine the query type and not crash
        // the application if we cannot. Just skip it.
        $facet->getQueryType();
      }
      catch (InvalidQueryTypeException $exception) {
        continue;
      }

      $widget = $facet->getWidgetInstance();

      // If facet id should be ignored due to query configuration.
      if (in_array($facet->id(), $ignored_filters)) {
        continue;
      }

      if ($widget instanceof ListPagesWidgetInterface) {
        $form['facets'][$facet->id()] = $this->facetsManager->getFacetManager()->build($facet);
        $cache->addCacheTags($facet->getCacheTags());
      }
    }

    if (!isset($form['facets'])) {
      $cache->applyTo($form);
      return $form;
    }

    $form_state->set('source_id', $source_id);

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#op' => 'submit',
    ];

    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear filters'),
      '#op' => 'reset',
    ];

    $cache->applyTo($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $current_url = Url::fromRoute('<current>');
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#op'] === 'reset') {
      // Add a fragment to the URL to make sure the page gets reloaded at the
      // same location on the viewport from where the filters were set.
      $current_url->setOption('fragment', 'oe-list-pages-facets-form');
      $form_state->setRedirectUrl($current_url);
      return;
    }

    $source_id = $form_state->get('source_id');
    $active_filters = [];
    $facets = $this->facetsManager->getFacetsByFacetSourceId($source_id);
    /** @var \Drupal\facets\FacetInterface $facet */
    foreach ($facets as $facet) {
      $widget = $facet->getWidgetInstance();
      if ($widget instanceof ListPagesWidgetInterface) {
        $active_filters[$facet->id()] = $widget->prepareValueForUrl($facet, $form, $form_state);
      }
    }

    $active_filters = array_filter($active_filters);
    if ($active_filters) {
      $url = $this->facetsUrlGenerator->getUrl($active_filters, FALSE);
      // Add a fragment to the URL to make sure the page gets reloaded at the
      // same location on the viewport from where the filters were set.
      $url->setOption('fragment', 'oe-list-pages-facets-form');
      $form_state->setRedirectUrl($url);
      return;
    }

    // If there are no active filters, we redirect to the current URL without
    // any filters in the URL.
    $form_state->setRedirectUrl($current_url);
  }

}
