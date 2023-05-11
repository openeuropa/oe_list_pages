<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_link_list_source\Plugin\LinkSource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\oe_link_lists\Event\EntityValueResolverEvent;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkSourcePluginBase;
use Drupal\oe_list_pages\FacetManipulationTrait;
use Drupal\oe_list_pages\Form\ListPageConfigurationSubformFactory;
use Drupal\oe_list_pages\ListExecutionManagerInterface;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages\ListSourceInterface;
use Drupal\oe_list_pages_link_list_source\ContextualFiltersConfigurationBuilder;
use Drupal\oe_list_pages_link_list_source\ContextualFilterValuesProcessor;
use Drupal\oe_list_pages_link_list_source\Exception\InapplicableContextualFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Link source plugin that integrates with List Pages.
 *
 * @LinkSource(
 *   id = "list_pages",
 *   label = @Translation("List pages"),
 *   description = @Translation("Source plugin that links to internal entities queried using the list pages."),
 *   bundles = { "dynamic" }
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
   * The contextual filters value processor.
   *
   * @var \Drupal\oe_list_pages_link_list_source\ContextualFilterValuesProcessor
   */
  protected $contextualFilterValuesProcessor;

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ListPageConfigurationSubformFactory $configurationSubformFactory, ListExecutionManagerInterface $listExecutionManager, EventDispatcherInterface $eventDispatcher, EntityTypeManagerInterface $entityTypeManager, EntityRepositoryInterface $entityRepository, ContextualFiltersConfigurationBuilder $contextualFiltersBuilder, ContextualFilterValuesProcessor $contextualFilterValuesProcessor) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configurationSubformFactory = $configurationSubformFactory;
    $this->listExecutionManager = $listExecutionManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityRepository = $entityRepository;
    $this->contextualFiltersBuilder = $contextualFiltersBuilder;
    $this->contextualFilterValuesProcessor = $contextualFilterValuesProcessor;
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
      $container->get('oe_list_pages_link_list_source.contextual_filters_values_processor')
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
      $configuration = $this->contextualFilterValuesProcessor->processConfiguration($this->configuration, $cache);
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
    $cache->addCacheTags([$configuration->getEntityType() . '_list:' . $configuration->getBundle()]);

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
      $this->eventDispatcher->dispatch($event, EntityValueResolverEvent::NAME);
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
    $configuration = $this->createListPageConfiguration();
    $configuration->setDefaultFilterValuesAllowed(TRUE);
    $subform = $this->configurationSubformFactory->getForm($configuration);
    $form['list_page_configuration'] = $subform->buildForm($form['list_page_configuration'], $subform_state);

    if (isset($form['list_page_configuration']['wrapper']['exposed_filters'])) {
      // We don't need the form to expose filters because in link lists we don't
      // use exposed facets.
      $form['list_page_configuration']['wrapper']['exposed_filters_override']['#access'] = FALSE;
      $form['list_page_configuration']['wrapper']['exposed_filters']['#access'] = FALSE;
    }

    if (isset($form['list_page_configuration']['wrapper']['exposed_sort'])) {
      // We don't need the form to expose the sort as it doesn't apply to
      // link lists.
      $form['list_page_configuration']['wrapper']['exposed_sort']['#access'] = FALSE;
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
    $form['list_page_configuration']['wrapper']['exclude_self'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude the current entity'),
      '#description' => $this->t('Excludes from the results the current entity if found.'),
      '#default_value' => $this->configuration['exclude_self'] ?? 0,
      // If we don't have the ID field in the index, we cannot.
      '#access' => !is_null($list_source->getIndex()->getField('list_page_link_source_id')),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $element = $form['list_page_configuration'];
    $subform_state = SubformState::createForSubform($element, $form, $form_state);
    $configuration = $this->createListPageConfiguration();
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
    $this->configuration['exclude_self'] = $form_state->getValue([
      'list_page_configuration',
      'wrapper',
      'exclude_self',
    ], 0);
  }

  /**
   * Creates the list page configuration from the raw config values.
   *
   * @return \Drupal\oe_list_pages\ListPageConfiguration
   *   The configuration.
   */
  protected function createListPageConfiguration(): ListPageConfiguration {
    return new ListPageConfiguration($this->configuration);
  }

}
