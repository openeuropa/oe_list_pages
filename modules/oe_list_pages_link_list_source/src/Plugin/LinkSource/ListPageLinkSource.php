<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_link_list_source\Plugin\LinkSource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\facets\FacetInterface;
use Drupal\oe_link_lists\Event\EntityValueResolverEvent;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkSourcePluginBase;
use Drupal\oe_list_pages\FacetManipulationTrait;
use Drupal\oe_list_pages\Form\ListPageConfigurationSubformFactory;
use Drupal\oe_list_pages\ListExecutionManagerInterface;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages\ListSourceFactoryInterface;
use Drupal\oe_list_pages\ListSourceInterface;
use Drupal\oe_list_pages\MultiselectFilterFieldPluginManager;
use Drupal\oe_list_pages_link_list_source\ContextualFilterFieldMapper;
use Drupal\oe_list_pages_link_list_source\ContextualFiltersConfigurationBuilder;
use Drupal\oe_list_pages_link_list_source\Exception\InapplicableContextualFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Link source plugin that integrates with List Pages.
 *
 * @LinkSource(
 *   id = "list_pages",
 *   label = @Translation("List pages"),
 *   description = @Translation("Source plugin that links to internal entities queried using the list pages.")
 * )
 */
class ListPageLinkSource extends LinkSourcePluginBase implements ContainerFactoryPluginInterface {

  use DependencySerializationTrait;
  use FacetManipulationTrait;

  /**
   * The list page subform configuration factory.
   *
   * @var \Drupal\oe_list_pages\Form\ListPageConfigurationSubformFactory
   */
  protected $configurationSubformFactory;

  /**
   * The list execution manager.
   *
   * @var \Drupal\oe_list_pages\ListExecutionManagerInterface
   */
  protected $listExecutionManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The contextual filters form builder.
   *
   * @var \Drupal\oe_list_pages_link_list_source\ContextualFiltersConfigurationBuilder
   */
  protected $contextualFiltersBuilder;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The list source factory.
   *
   * @var \Drupal\oe_list_pages\ListSourceFactoryInterface
   */
  protected $listSourceFactory;

  /**
   * Plugin manager for the multiselect filter fields.
   *
   * @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginManager
   */
  protected $multiselectPluginManager;

  /**
   * The contextual filters field mappger.
   *
   * @var \Drupal\oe_list_pages_link_list_source\ContextualFilterFieldMapper
   */
  protected $contextualFieldMapper;

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ListPageConfigurationSubformFactory $configurationSubformFactory, ListExecutionManagerInterface $listExecutionManager, EventDispatcherInterface $eventDispatcher, EntityTypeManagerInterface $entityTypeManager, EntityRepositoryInterface $entityRepository, ContextualFiltersConfigurationBuilder $contextualFiltersBuilder, RouteMatchInterface $routeMatch, ListSourceFactoryInterface $listSourceFactory, MultiselectFilterFieldPluginManager $multiselectPluginManager, ContextualFilterFieldMapper $contextualFieldMapper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configurationSubformFactory = $configurationSubformFactory;
    $this->listExecutionManager = $listExecutionManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityRepository = $entityRepository;
    $this->contextualFiltersBuilder = $contextualFiltersBuilder;
    $this->routeMatch = $routeMatch;
    $this->listSourceFactory = $listSourceFactory;
    $this->multiselectPluginManager = $multiselectPluginManager;
    $this->contextualFieldMapper = $contextualFieldMapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('oe_list_pages.list_page_configuration_subform_factory'),
      $container->get('oe_list_pages.execution_manager'),
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('oe_list_pages_link_list_source.contextual_filters_builder'),
      $container->get('current_route_match'),
      $container->get('oe_list_pages.list_source.factory'),
      $container->get('plugin.manager.multiselect_filter_field'),
      $container->get('oe_list_pages_link_list_source.contextual_filters_field_mapper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'entity_type' => '',
      'bundle' => '',
      'exposed_filters' => [],
      'exposed_filters_overridden' => FALSE,
      'contextual_filters' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getLinks(int $limit = NULL, int $offset = 0): LinkCollectionInterface {
    $links = new LinkCollection();
    $cache = new CacheableMetadata();
    try {
      $configuration = $this->initializeConfiguration($cache);
    }
    catch (InapplicableContextualFilter $exception) {
      // If at least one of the contextual filters does not apply, we need to
      // return an empty list. This is because the operator between each filter
      // is AND.
      $links->addCacheableDependency($cache);
      return $links;
    }

    $limit = is_null($limit) ? 0 : $limit;
    $configuration->setLimit($limit);
    $list_execution = $this->listExecutionManager->executeList($configuration);
    if (!$list_execution) {
      $links->addCacheableDependency($cache);
      return $links;
    }
    $results = $list_execution->getResults();
    $query = $list_execution->getQuery();
    $cache->addCacheableDependency($query);
    $cache->addCacheTags(['search_api_list:' . $query->getIndex()->id()]);
    $cache->addCacheTags($this->entityTypeManager->getDefinition($configuration->getEntityType())->getListCacheTags());
    $cache->addCacheContexts($this->entityTypeManager->getDefinition($configuration->getEntityType())->getListCacheContexts());

    if (!$results->getResultCount()) {
      $links->addCacheableDependency($cache);
      return $links;
    }

    foreach ($results->getResultItems() as $item) {
      $entity = $item->getOriginalObject()->getEntity();
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $this->entityRepository->getTranslationFromContext($entity);
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $event = new EntityValueResolverEvent($entity);
      $this->eventDispatcher->dispatch(EntityValueResolverEvent::NAME, $event);
      $cache->addCacheableDependency($entity);
      $links[] = $event->getLink();
    }

    $links->addCacheableDependency($cache);
    return $links;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['list_page_configuration'] = [];
    $form['list_page_configuration']['#parents'] = array_merge($form['#parents'], ['list_page_configuration']);
    $form['list_page_configuration']['#parents_limit_validation_errors'] = [
      array_merge(array_slice($form['list_page_configuration']['#parents'], 0, 3), ['plugin']),
    ];
    $form['list_page_configuration']['#tree'] = TRUE;
    $subform_state = SubformState::createForSubform($form['list_page_configuration'], $form, $form_state);
    $configuration = new ListPageConfiguration($this->configuration);
    $configuration->setDefaultFilterValuesAllowed(TRUE);
    $subform = $this->configurationSubformFactory->getForm($configuration);
    $form['list_page_configuration'] = $subform->buildForm($form['list_page_configuration'], $subform_state);

    if (isset($form['list_page_configuration']['wrapper']['exposed_filters'])) {
      // We don't need the form to expose filters because in link lists we don't
      // use exposed facets.
      $form['list_page_configuration']['wrapper']['exposed_filters_override']['#access'] = FALSE;
      $form['list_page_configuration']['wrapper']['exposed_filters']['#access'] = FALSE;
    }

    $list_source = $form_state->get('list_source');
    if (!$list_source instanceof ListSourceInterface) {
      return $form;
    }

    $parents = $form['#parents'] ?? [];
    $form['list_page_configuration']['wrapper']['contextual_filters'] = [
      '#parents' => array_merge($parents, [
        'list_page_configuration',
        'wrapper',
        'contextual_filters',
      ]),
      '#tree' => TRUE,
    ];

    $subform_state = SubformState::createForSubform($form['list_page_configuration']['wrapper']['contextual_filters'], $form, $form_state);
    $form['list_page_configuration']['wrapper']['contextual_filters'] = $this->contextualFiltersBuilder->buildContextualFilters($form['list_page_configuration']['wrapper']['contextual_filters'], $subform_state, $list_source, $this->configuration);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $element = $form['list_page_configuration'];
    $subform_state = SubformState::createForSubform($element, $form, $form_state);
    $configuration = new ListPageConfiguration($this->configuration);
    $subform = $this->configurationSubformFactory->getForm($configuration);
    $subform->submitForm($element, $subform_state);
    $configuration = $subform->getConfiguration();
    $this->configuration = $configuration->toArray();
    $contextual_filters = array_filter($form_state->getValue([
      'list_page_configuration',
      'wrapper',
      'contextual_filters',
      'current_filters',
    ], []));

    $this->configuration['contextual_filters'] = $contextual_filters;
  }

  /**
   * Prepares the configuration object for this link list.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   The cacheable metadata.
   *
   * @return \Drupal\oe_list_pages\ListPageConfiguration
   *   The configuration.
   */
  protected function initializeConfiguration(CacheableMetadata $cache): ListPageConfiguration {
    $configuration = new ListPageConfiguration($this->configuration);
    $cache->addCacheContexts(['route']);

    // Add the contextual filters.
    $contextual_filters = $this->configuration['contextual_filters'];
    $entity = $this->getCurrentEntityFromRoute();
    if (!$entity instanceof ContentEntityInterface) {
      if (!empty($contextual_filters)) {
        // If we have contextual filters but don't have an entity to check for
        // the corresponding fields, we cannot have results.
        throw new InapplicableContextualFilter();
      }

      // Otherwise, the configuration stays untouched.
      return $configuration;
    }

    $cache->addCacheableDependency($entity);

    $default_filter_values = $configuration->getDefaultFiltersValues();
    $list_source = $this->listSourceFactory->get($configuration->getEntityType(), $configuration->getBundle());

    foreach ($contextual_filters as $contextual_filter) {
      $facet = $this->contextualFiltersBuilder->getFacetById($list_source, $contextual_filter->getFacetId());
      $definition = $this->getFacetFieldDefinition($facet, $list_source);
      $field_name = $definition->getName();
      // Map the field correctly.
      $field_name = $this->contextualFieldMapper->getCorrespondingFieldName($field_name, $entity, $cache);
      if (!$field_name) {
        // If the field doesn't exist on the current entity, we need to not
        // show any results.
        throw new InapplicableContextualFilter();
      }

      $field = $entity->get($field_name);
      $values = $this->extractValuesFromField($field, $facet, $list_source);
      if (empty($values)) {
        // If the contextual filter does not have a value, we again cannot
        // show any results.
        throw new InapplicableContextualFilter();
      }

      $contextual_filter->setValues($values);
      $default_filter_values[ContextualFiltersConfigurationBuilder::generateFilterId($contextual_filter->getFacetId(), array_keys($default_filter_values))] = $contextual_filter;
    }

    $configuration->setDefaultFilterValues($default_filter_values);

    return $configuration;
  }

  /**
   * Get content entity from route.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The content entity.
   */
  protected function getCurrentEntityFromRoute() :?ContentEntityInterface {
    $route_name = $this->routeMatch->getRouteName();
    $parts = explode('.', $route_name);
    if (count($parts) !== 3 || $parts[0] !== 'entity') {
      return NULL;
    }

    $entity_type = $parts[1];
    $entity = $this->routeMatch->getParameter($entity_type);

    // In case the entity parameter is not resolved (e.g.: revisions route.
    if (!$entity instanceof ContentEntityInterface) {
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity);
    }
    return $entity;
  }

  /**
   * Extracts the field values.
   *
   * Determines what type of field we are dealing with and delegates to the
   * correct multiselect filter field plugin to handle the value extraction.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items list.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   *
   * @return array
   *   The values.
   */
  protected function extractValuesFromField(FieldItemListInterface $items, FacetInterface $facet, ListSourceInterface $list_source) {
    $field_definition = $items->getFieldDefinition();
    $id = $this->multiselectPluginManager->getPluginIdByFieldType($field_definition->getType());
    if (!$id) {
      return [];
    }

    $config = [
      'facet' => $facet,
      'preset_filter' => [],
      'list_source' => $list_source,
    ];

    /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
    $plugin = $this->multiselectPluginManager->createInstance($id, $config);

    return $plugin->getFieldValues($items);
  }

}
