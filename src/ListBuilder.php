<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\oe_list_pages\Form\ListFacetsForm;

/**
 * Handles the list pages component building.
 */
class ListBuilder implements ListBuilderInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * The pager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The list execution manager.
   *
   * @var \Drupal\oe_list_pages\ListExecutionManagerInterface
   */
  protected $listExecutionManager;

  /**
   * ListManager constructor.
   *
   * @param \Drupal\oe_list_pages\ListExecutionManagerInterface $listExecutionManager
   *   The list execution manager.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager
   *   The pager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   */
  public function __construct(ListExecutionManagerInterface $listExecutionManager, EntityTypeManager $entityTypeManager, PagerManagerInterface $pager, EntityRepositoryInterface $entityRepository, FormBuilderInterface $formBuilder) {
    $this->listExecutionManager = $listExecutionManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->pager = $pager;
    $this->entityRepository = $entityRepository;
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public function buildList(ContentEntityInterface $entity): array {

    $list_execution = $this->listExecutionManager->executeList($entity);

    $build = [];

    $cache = new CacheableMetadata();
    $cache->addCacheTags($entity->getEntityType()->getListCacheTags());
    $list_source = $list_execution->getListSource();
    if (!$list_source) {
      $cache->applyTo($build);
      return $build;
    }

    $query = $list_execution->getQuery();
    $result = $list_execution->getResults();
    $wrapper = $list_execution->getListPluginWrapper();

    // Determine the view mode to render with and the sorting.
    $bundle_entity_type = $this->entityTypeManager->getDefinition($wrapper->getSourceEntityType())->getBundleEntityType();
    $storage = $this->entityTypeManager->getStorage($bundle_entity_type);
    $bundle = $storage->load($wrapper->getSourceEntityBundle());
    $view_mode = $bundle->getThirdPartySetting('oe_list_pages', 'default_view_mode', 'teaser');
    $cache->addCacheableDependency($query);
    $cache->addCacheTags(['search_api_list:' . $query->getIndex()->id()]);

    if (!$result->getResultCount()) {
      $cache->applyTo($build);
      return $build;
    }

    $this->pager->createPager($result->getResultCount(), $query->getOption('limit'));

    $items = [];

    // Build the entities.
    $builder = $this->entityTypeManager->getViewBuilder($wrapper->getSourceEntityType());
    foreach ($result->getResultItems() as $item) {
      $entity = $item->getOriginalObject()->getEntity();
      $cache->addCacheableDependency($entity);
      $entity = $this->entityRepository->getTranslationFromContext($entity);
      $items[] = $builder->view($entity, $view_mode);
    }

    $build['list'] = [
      '#type' => 'pattern',
      '#id' => 'list_item_block_one_column',
      '#fields' => [
        'items' => $items,
      ],
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    $cache->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFiltersForm(ContentEntityInterface $entity): array {
    $build = $ignored_filters = [];

    $list_execution = $this->listExecutionManager->executeList($entity);
    $list_source = $list_execution->getListSource();

    if (!$list_source) {
      return $build;
    }

    $build['form'] = $this->formBuilder->getForm(ListFacetsForm::class, $list_source, $ignored_filters);

    return $build;
  }

}
