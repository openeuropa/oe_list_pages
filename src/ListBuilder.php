<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\facets\Processor\ProcessorPluginManager;
use Drupal\facets\UrlProcessor\UrlProcessorPluginManager;
use Drupal\facets\Utility\FacetsUrlGenerator;
use Drupal\oe_list_pages\Form\ListFacetsForm;
use Drupal\oe_list_pages\Plugin\facets\processor\DefaultStatusProcessorInterface;
use Drupal\oe_list_pages\Plugin\facets\widget\FulltextWidget;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Default list builder implementation.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ListBuilder implements ListBuilderInterface {

  use StringTranslationTrait;
  use FacetManipulationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

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
   * The facets manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetManager;

  /**
   * The facets URL generator.
   *
   * @var \Drupal\facets\Utility\FacetsUrlGenerator
   */
  protected $facetsUrlGenerator;

  /**
   * The facets processor plugin manager.
   *
   * @var \Drupal\facets\Processor\ProcessorPluginManager
   */
  protected $processorManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The URL processor manager.
   *
   * @var \Drupal\facets\UrlProcessor\UrlProcessorPluginManager
   */
  protected $urlProcessorManager;

  /**
   * The multiselect filter field manager.
   *
   * @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginManager
   */
  protected $multiselectFilterManager;

  /**
   * ListBuilder constructor.
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
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facetManager
   *   The facets manager.
   * @param \Drupal\facets\Utility\FacetsUrlGenerator $facetsUrlGenerator
   *   The facets URL generator.
   * @param \Drupal\facets\Processor\ProcessorPluginManager $processorManager
   *   The facets processor plugin manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\facets\UrlProcessor\UrlProcessorPluginManager $urlProcessorManager
   *   The URL processor manager.
   * @param \Drupal\oe_list_pages\MultiselectFilterFieldPluginManager $multiselectFilterManager
   *   The multiselect filter field manager.
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(ListExecutionManagerInterface $listExecutionManager, EntityTypeManager $entityTypeManager, PagerManagerInterface $pager, EntityRepositoryInterface $entityRepository, FormBuilderInterface $formBuilder, DefaultFacetManager $facetManager, FacetsUrlGenerator $facetsUrlGenerator, ProcessorPluginManager $processorManager, RequestStack $requestStack, UrlProcessorPluginManager $urlProcessorManager, MultiselectFilterFieldPluginManager $multiselectFilterManager) {
    $this->listExecutionManager = $listExecutionManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->pager = $pager;
    $this->entityRepository = $entityRepository;
    $this->formBuilder = $formBuilder;
    $this->facetManager = $facetManager;
    $this->facetsUrlGenerator = $facetsUrlGenerator;
    $this->processorManager = $processorManager;
    $this->requestStack = $requestStack;
    $this->urlProcessorManager = $urlProcessorManager;
    $this->multiselectFilterManager = $multiselectFilterManager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildList(ContentEntityInterface $entity): array {
    $build = [
      'list' => [],
    ];

    $cache = new CacheableMetadata();
    $cache->addCacheTags($entity->getEntityType()->getListCacheTags());

    $configuration = ListPageConfiguration::fromEntity($entity);
    $list_execution = $this->listExecutionManager->executeList($configuration);
    if (empty($list_execution)) {
      $cache->applyTo($build);
      return $build;
    }

    $list_source = $list_execution->getListSource();
    if (!$list_source) {
      $cache->applyTo($build);
      return $build;
    }

    $query = $list_execution->getQuery();
    $result = $list_execution->getResults();
    $configuration = $list_execution->getConfiguration();

    // Determine the view mode to render with and the sorting.
    $bundle_entity_type = $this->entityTypeManager->getDefinition($configuration->getEntityType())->getBundleEntityType();
    $storage = $this->entityTypeManager->getStorage($bundle_entity_type);
    $bundle = $storage->load($configuration->getBundle());
    $view_mode = $bundle->getThirdPartySetting('oe_list_pages', 'default_view_mode', 'teaser');
    $cache->addCacheableDependency($query);
    $cache->addCacheableDependency($bundle);
    $cache->addCacheTags(['search_api_list:' . $query->getIndex()->id()]);

    $this->pager->createPager($result->getResultCount(), $query->getOption('limit'));
    $build['pager'] = [
      '#type' => 'pager',
    ];

    if (!$result->getResultCount()) {
      $cache->applyTo($build);
      return $build;
    }

    $items = [];

    // Build the entities.
    $builder = $this->entityTypeManager->getViewBuilder($configuration->getEntityType());
    foreach ($result->getResultItems() as $item) {
      $entity = $item->getOriginalObject()->getEntity();
      $cache->addCacheableDependency($entity);
      $entity = $this->entityRepository->getTranslationFromContext($entity);
      $items[] = $builder->view($entity, $view_mode);
    }

    $build['list'] = [
      '#theme' => 'item_list__oe_list_pages_results',
      '#items' => $items,
    ];

    $cache->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFiltersForm(ContentEntityInterface $entity): array {
    $build = $ignored_filters = $exposed_filters = [];

    $configuration = ListPageConfiguration::fromEntity($entity);
    $list_execution = $this->listExecutionManager->executeList($configuration);
    $list_source = $list_execution->getListSource();

    if (!$list_source) {
      return $build;
    }

    $configuration = $list_execution->getConfiguration();
    $available_filters = $list_source->getAvailableFilters();

    if ($configuration->isExposedFiltersOverridden()) {
      $exposed_filters = $configuration->getExposedFilters();
    }
    else {
      $bundle_entity_type = $this->entityTypeManager->getDefinition($configuration->getEntityType())->getBundleEntityType();
      $storage = $this->entityTypeManager->getStorage($bundle_entity_type);
      $bundle = $storage->load($configuration->getBundle());
      $exposed_filters = $bundle->getThirdPartySetting('oe_list_pages', 'default_exposed_filters', []);
    }

    // By default ignore all filters.
    if (!empty($available_filters)) {
      $ignored_filters = $available_filters;
    }

    // If filters are selected then ignore the non-selected.
    if (!empty($available_filters)) {
      $ignored_filters = array_diff(array_keys($available_filters), array_values($exposed_filters));
    }

    $build = $this->formBuilder->getForm(ListFacetsForm::class, $list_source, $ignored_filters);

    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function buildSelectedFilters(ContentEntityInterface $entity): array {
    $build = [];
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['url']);

    $configuration = ListPageConfiguration::fromEntity($entity);
    $active_filters = [];

    // First, determine all the active values for filters that have a "default
    // status" processor. These won't exist in the URL so we need to get them
    // from the list execution.
    $list_execution = $this->listExecutionManager->executeList($configuration);
    $facets = $this->getKeyedFacetsFromSource($list_execution->getListSource());
    foreach ($facets as $facet) {
      $active_items = $facet->getActiveItems();
      if (!empty($active_items) && $this->facetHasDefaultStatus($facet, ['raw' => reset($active_items)])) {
        $active_filters[$facet->id()] = $facet->getActiveItems();
      }
    }

    // Then check for the selected filters from the URL.
    $list_source = $list_execution->getListSource();
    $available_filters = array_keys($list_source->getAvailableFilters());
    if (!$available_filters && !$active_filters) {
      $cache->applyTo($build);
      return $build;
    }

    // Load one of the source facets because we need to use it to determine the
    // current active filters using the query_string plugin.
    $facet = reset($facets);
    $query_string = $this->urlProcessorManager->createInstance('query_string', ['facet' => $facet]);
    $active_filters += $query_string->getActiveFilters();

    // Prepare the URL for each individual filter value that would remove it
    // from the active filters.
    foreach ($active_filters as $facet_id => $filters) {
      foreach ($filters as $key => $value) {
        $filter_remaining_active = $active_filters;
        unset($filter_remaining_active[$facet_id][$key]);
        $filter_remaining_active = array_filter($filter_remaining_active);
        if (!$filter_remaining_active) {
          // If there are no more active filters, we just generate a URL
          // to the current page, with no query parameters.
          $urls[$facet_id][$key] = Url::fromRoute('<current>');
          continue;
        }

        // Re-key so that the URL generator always can rely on key 0.
        foreach ($filter_remaining_active as $facet_id_remaining => &$values_remaining) {
          $values_remaining = array_values($values_remaining);
        }

        $urls[$facet_id][$key] = $this->facetsUrlGenerator->getUrl($filter_remaining_active, FALSE);
      }
    }

    $items = [];
    foreach ($active_filters as $facet_id => $filters) {
      $facet = $facets[$facet_id];
      $item = [];

      foreach ($filters as $key => $value) {
        $display_value = $this->getFacetResultDisplayLabel($facet, $value, $list_source);

        $item['items'][] = [
          'url' => $urls[$facet_id][$key],
          'label' => $display_value,
          'raw' => $value,
        ];
      }

      if (empty($item['items'])) {
        continue;
      }

      $item['name'] = $facet->getName();
      $items[$facet_id] = $item;
    }

    if ($this->countTotalSelectedFilters($items) === 1) {
      // If we only have one selected filter, it means its URL will remove
      // all filters. However, it can also be the filter of a facet that uses
      // a DefaultStatusProcessorInterface processor, in which case we need
      // kill the URL and only display it as a label.
      $facet_id = key($items);
      $facet = $facets[$facet_id];
      if ($this->facetHasDefaultStatus($facet, reset($items[$facet_id]['items']))) {
        foreach ($items as $facet_id => &$filters) {
          $filters['items'][0]['url'] = NULL;
        }
      }
    }

    foreach ($items as $facet_id => $item) {
      $build[$facet_id] = [
        '#theme' => 'oe_list_pages_selected_facet',
        '#label' => $item['name'],
        '#items' => $item['items'],
      ];
    }

    $cache->applyTo($build);
    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function buildPagerInfo(ContentEntityInterface $entity): array {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['url']);
    $build = [];

    $configuration = ListPageConfiguration::fromEntity($entity);
    $list_execution = $this->listExecutionManager->executeList($configuration);
    $results = $list_execution->getResults();
    if (!$results->getResultCount()) {
      $build = [
        '#markup' => $this->t('No results have been found'),
      ];
      $cache->applyTo($build);
      return $build;
    }

    $offset = $results->getQuery()->getOption('offset', FALSE);
    $limit = $results->getQuery()->getOption('limit', FALSE);
    if ($offset === FALSE || $limit == FALSE) {
      $cache->applyTo($build);
      return $build;
    }

    $first = $offset === 0 ? 1 : $offset;
    $last = $limit + $offset;
    if (count($results->getResultItems()) < $limit) {
      $last = $results->getResultCount();
    }

    $build = [
      '#markup' => $this->t('Showing results @first to @last', ['@first' => $first, '@last' => $last]),
    ];
    $cache->applyTo($build);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRssLink(ContentEntityInterface $entity): array {
    $url = Url::fromRoute('entity.node.list_page_rss', ['node' => $entity->id()]);
    $url->setOption('query', $this->requestStack->getCurrentRequest()->query->all());
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['url.query_args']);
    $link = Link::fromTextAndUrl(t('RSS'), $url)->toRenderable();
    $cache->applyTo($link);
    return $link;
  }

  /**
   * Returns the display label of a facet result.
   *
   * At this point, we can expect the facet to have already been built so it
   * should have the results.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param string $value
   *   The raw value.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   *
   * @return string|null
   *   The display value.
   */
  protected function getFacetResultDisplayLabel(FacetInterface $facet, string $value, ListSourceInterface $list_source): ?string {
    if ($facet->getWidgetInstance() instanceof FulltextWidget) {
      // For facets that use the full text widget, the actual value is the
      // selected item.
      return $value;
    }

    $id = $this->multiselectFilterManager->getPluginIdForFacet($facet, $list_source);
    $preset_filter = new ListPresetFilter($facet->id(), [$value]);
    if (!$id) {
      return $this->getDefaultFilterValuesLabel($facet, $preset_filter);
    }

    $config = [
      'facet' => $facet,
      'list_source' => $list_source,
      'preset_filter' => $preset_filter,
    ];
    /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
    $plugin = $this->multiselectFilterManager->createInstance($id, $config);
    return $plugin->getDefaultValuesLabel();
  }

  /**
   * Counts the total number of selected filters to be returned.
   *
   * @param array $items
   *   The list of selected filters ready to be printed.
   *
   * @return int
   *   The total count.
   */
  protected function countTotalSelectedFilters(array $items): int {
    $total = 0;
    foreach ($items as $facet_id => $filters) {
      foreach ($filters['items'] as $key => $value) {
        $total++;
      }
    }

    return $total;
  }

  /**
   * Checks if the facet uses a default status processor.
   *
   * This processor sets a default active item to the facet if there are no
   * other active items in the URL.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facets.
   * @param array $filters
   *   The selected filters for this facet.
   *
   * @return bool
   *   Whether the facet uses this type of processor.
   */
  protected function facetHasDefaultStatus(FacetInterface $facet, array $filters): bool {
    $configs = $facet->getProcessorConfigs();
    if (!$configs) {
      return FALSE;
    }

    $plugin_ids = array_keys($configs);
    foreach ($plugin_ids as $plugin_id) {
      $processor = $this->processorManager->createInstance($plugin_id, ['facet' => $facet]);
      if ($processor instanceof DefaultStatusProcessorInterface && $filters['raw'] === $configs[$plugin_id]['settings']['default_status']) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
