<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_filters_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\oe_list_pages\Form\ListFacetsForm;
use Drupal\oe_list_pages\ListSourceFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a controller that renders the facets form and prints the results.
 *
 * This is used for testing the form and asserting results.
 */
class FacetsFormTestController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The facets manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetsManager;

  /**
   * The list source factory.
   *
   * @var \Drupal\oe_list_pages\ListSourceFactory
   */
  protected $listSourceFactory;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new FacetsFormTestController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facets_manager
   *   The facets manager.
   * @param \Drupal\oe_list_pages\ListSourceFactory $listSourceFactory
   *   The list source factory.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pagerManager
   *   The pager manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, DefaultFacetManager $facets_manager, ListSourceFactory $listSourceFactory, PagerManagerInterface $pagerManager, StateInterface $state) {
    $this->entityTypeManager = $entityTypeManager;
    $this->facetsManager = $facets_manager;
    $this->listSourceFactory = $listSourceFactory;
    $this->pagerManager = $pagerManager;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('facets.manager'),
      $container->get('oe_list_pages.list_source.factory'),
      $container->get('pager.manager'),
      $container->get('state')
    );
  }

  /**
   * Builds the page.
   */
  public function build() {
    $list_source = $this->listSourceFactory->get('node', 'content_type_one');

    // Run the query for a given source and print the results on the page so
    // we can assert them.
    $per_page = 10;
    $query = $list_source->getQuery($per_page);
    $result = $query->execute();
    $item_list = [];

    /** @var \Drupal\search_api\Item\ItemInterface[] $items */
    $items = $result->getResultItems();
    foreach ($items as $item) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $item->getOriginalObject()->getEntity();
      $item_list[] = $entity->label();
    }

    $build['results'] = [
      '#theme' => 'item_list',
      '#items' => $item_list,
    ];

    $this->pagerManager->createPager($result->getResultCount(), $per_page);
    $build['pager'] = [
      '#type' => 'pager',
    ];

    $build['list_items']['#cache']['max-age'] = 0;
    $ignored_filters = $this->state->get('oe_list_pages_test.ignored_filters', []);
    $build['form'] = \Drupal::formBuilder()->getForm(ListFacetsForm::class, $list_source, $ignored_filters);

    return $build;
  }

}
