<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_link_list_source\Plugin\LinkSource;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\oe_link_lists\Event\EntityValueResolverEvent;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkSourcePluginBase;
use Drupal\oe_list_pages\Form\ListPageConfigurationSubformFactory;
use Drupal\oe_list_pages\ListExecutionManagerInterface;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListPresetFiltersBuilder;
use Drupal\oe_list_pages\ListSourceFactoryInterface;
use Drupal\oe_list_pages\ListSourceInterface;
use Drupal\oe_list_pages\Plugin\facets\widget\MultiselectWidget;
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
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ListPageLinkSource extends LinkSourcePluginBase implements ContainerFactoryPluginInterface {

  use DependencySerializationTrait;

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
   * The facets manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetsManager;

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ListPageConfigurationSubformFactory $configurationSubformFactory, ListExecutionManagerInterface $listExecutionManager, EventDispatcherInterface $eventDispatcher, EntityTypeManagerInterface $entityTypeManager, EntityRepositoryInterface $entityRepository, DefaultFacetManager $facetManager, ListSourceFactoryInterface $listSourceFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configurationSubformFactory = $configurationSubformFactory;
    $this->listExecutionManager = $listExecutionManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityRepository = $entityRepository;
    $this->facetsManager = $facetManager;
    $this->listSourceFactory = $listSourceFactory;
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
      $container->get('facets.manager'),
      $container->get('oe_list_pages.list_source.factory'),
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
    ] + parent::defaultConfiguration();
  }

  /**
   * Get original field name from facet config.
   *
   * @param \Drupal\facets\Entity\FacetInterface $facet
   *   The facet.
   *
   * @return string|null
   *   The field name if found.
   */
  protected function getFieldName(FacetInterface $facet) : ?string {
    $field = $facet->getFacetSource()->getIndex()->getField($facet->getFieldIdentifier());
    $field_name = $field->getOriginalFieldIdentifier();
    $property_path = $field->getPropertyPath();
    $parts = explode(':', $property_path);
    if (count($parts) > 1) {
      $field_name = $parts[0];
    }

    return $field_name;
  }

  /**
   * Get a facet by id.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $listSource
   *   The list source.
   * @param string $id
   *   The facet id.
   *
   * @return \Drupal\facets\FacetInterface|null
   *   The facet if found.
   */
  public function getFacetById(ListSourceInterface $listSource, string $id): ?FacetInterface {
    $facets = $this->facetsManager->getFacetsByFacetSourceId($listSource->getSearchId());
    foreach ($facets as $facet) {
      if ($id === $facet->id()) {
        return $facet;
      }
    }

    return NULL;
  }

  /**
   * Get content entity from route.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The content entity.
   */
  protected function getCurrentEntityFromRoute() :?ContentEntityInterface {
    $route_match = \Drupal::routeMatch();
    $route_name = $route_match->getRouteName();
    if (($route = $route_match->getRouteObject()) && ($parameters = $route->getOption('parameters'))) {
      foreach ($parameters as $name => $options) {
        $route_parts = explode(':', $options['type']);
        if (isset($options['type']) && $route_parts[0] === 'entity' && $route_name = 'entity:' . $route_parts[0] . ':canonical') {
          $entity = $route_match->getParameter($name);
          if ($entity instanceof ContentEntityInterface && $entity->hasLinkTemplate('canonical')) {
            return $entity;
          }

          return NULL;
        }
      }
    }
  }

  /**
   * Adds contextual filters to list configuration.
   *
   * @param array $sourceConfiguration
   *   The source configuration.
   * @param \Drupal\oe_list_pages\ListPageConfiguration $listPageConfiguration
   *   The list page configuration.
   */
  public function addContextualFilters(array $sourceConfiguration, ListPageConfiguration &$listPageConfiguration): void {
    $contextual_filters = $sourceConfiguration['contextual_filters'];
    /** @var \Drupal\Core\Entity\ContentEntityInterface $current_node */
    $current_entity = $this->getCurrentEntityFromRoute();
    if (empty($contextual_filters) || empty($current_entity)) {
      return;
    }
    $existing_filters = $listPageConfiguration->getDefaultFiltersValues();
    $list_source = $this->listSourceFactory->get($listPageConfiguration->getEntityType(), $listPageConfiguration->getBundle());

    foreach ($contextual_filters as $contextual_filter) {
      $facet = $this->getFacetById($list_source, $contextual_filter);
      $field_name = $this->getFieldName($facet);
      $field = $current_entity->get($field_name);
      $field_definition = $field->getFieldDefinition();
      $values = [];
      /* @todo create a plugin type MultiSelectFilterFieldPlugin */
      if (in_array(EntityReferenceFieldItemListInterface::class, class_implements($field_definition->getClass()))) {
        $field_value = $current_entity->get($field_name)->getValue();
        $values = array_map(function ($value) {
          return $value['target_id'];
        }, $field_value);
      }

      if (!empty($values)) {
        $existing_filters[ListPresetFiltersBuilder::generateFilterId($contextual_filter)] = new ListPresetFilter($contextual_filter, $values);
      }
    }

    $listPageConfiguration->setDefaultFilterValues($existing_filters);
  }

  /**
   * {@inheritdoc}
   */
  public function getLinks(int $limit = NULL, int $offset = 0): LinkCollectionInterface {
    $links = new LinkCollection();
    $configuration = new ListPageConfiguration($this->configuration);
    $limit = is_null($limit) ? 0 : $limit;
    $configuration->setLimit($limit);
    $cache = new CacheableMetadata();
    $this->addContextualFilters($this->configuration, $configuration);
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
   * Ajax request handler for adding a contextual filter.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function addContextualFilterAjax(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -4));
    return $element['wrapper']['contextual_filters'];
  }

  /**
   * Ajax request handler for removing a contextual filter.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function removeContextualFilterAjax(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -5));
    return $element['wrapper']['contextual_filters'];
  }

  /**
   * Set the current contextual filters on the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source the filter values belong to.
   * @param array $current_filter_values
   *   The filter values.
   */
  protected static function setListSourceContextualFilterValues(FormStateInterface $form_state, ListSourceInterface $list_source, array $current_filter_values): void {
    $storage = &$form_state->getStorage();
    NestedArray::setValue($storage, ['contextual_filters', $list_source->getSearchId()], $current_filter_values);
  }

  /**
   * Submit callback for removing a contextual filter.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function deleteContextualValueSubmit(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');
    $triggering_element = $form_state->getTriggeringElement();
    $filter_id = $triggering_element['#filter_id'];
    $current_filters = static::getListSourceContextualFilterValues($form_state, $form_state->getStorage()['list_source']);
    unset($current_filters[$filter_id]);
    static::setListSourceContextualFilterValues($form_state, $list_source, $current_filters);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit callback for adding a new default value for a filter.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addDefaultValueSubmit(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');

    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -1));
    $subform_state = SubformState::createForSubform($element, $form, $form_state);

    $current_filters = static::getListSourceContextualFilterValues($form_state, $form_state->getStorage()['list_source']);
    $current_filters[$subform_state->getValue('add_new')] = $subform_state->getValue('add_new');
    // Set the current filters on the form state so they can be used elsewhere.
    static::setListSourceContextualFilterValues($form_state, $list_source, $current_filters);

    $form_state->setRebuild(TRUE);
  }

  /**
   * Gets the current contextual filters from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source the contextual filters belong to.
   *
   * @return array
   *   The filter values.
   */
  protected static function getListSourceContextualFilterValues(FormStateInterface $form_state, ListSourceInterface $list_source): array {
    $storage = $form_state->getStorage();
    $current_filter_values = NestedArray::getValue($storage, ['contextual_filters', $list_source->getSearchId()]);
    return $current_filter_values ?? [];
  }

  /**
   * Checks if the contextual filter from the form state were emptied.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source the filter values belong to.
   *
   * @return bool
   *   Wheather contextual filters are empty
   */
  protected static function isContextualFilterValuesEmpty(FormStateInterface $form_state, ListSourceInterface $list_source): bool {
    $storage = $form_state->getStorage();
    $values = NestedArray::getValue($storage, ['contextual_filters', $list_source->getSearchId()]);
    // If we have an empty array, it means we removed all the values.
    return is_array($values) && empty($values) ?? FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
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
    $list_source = $form_state->getStorage()['list_source'];

    $facets = $this->facetsManager->getFacetsByFacetSourceId($list_source->getSearchId());
    foreach ($facets as $facet) {
      if (!empty($facet) && ($widget = $facet->getWidgetInstance()) && ($widget instanceof MultiselectWidget)) {
        $available_filters[$facet->id()] = $facet->label();
      }
    }

    $ajax_wrapper_id = 'list-page-contextual_filter_values-' . ($form['#parents'] ? '-' . implode('-', $form['#parents']) : '');

    $form['list_page_configuration']['wrapper']['contextual_filters'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => [
        'id' => $ajax_wrapper_id,
      ],
    ];

    $form['list_page_configuration']['wrapper']['contextual_filters']['label'] = [
      '#title' => $this->t('Contextual filter values'),
      '#type' => 'label',
    ];

    $contextual_filters = static::getListSourceContextualFilterValues($form_state, $list_source);
    if (!$contextual_filters && $list_source->getEntityType() === $configuration->getEntityType() && $list_source->getBundle() === $configuration->getBundle() && !static::isContextualFilterValuesEmpty($form_state, $list_source)) {
      $values = $this->configuration['contextual_filters'];
      static::setListSourceContextualFilterValues($form_state, $list_source, $values);
      $contextual_filters = static::getListSourceContextualFilterValues($form_state, $list_source);
    }

    $form['list_page_configuration']['wrapper']['contextual_filters']['summary'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Summary'),
    ];

    $header = [
      ['data' => $this->t('Filter')],
      ['data' => $this->t('Operator')],
      ['data' => $this->t('Operations')],
    ];

    $rows = [];
    foreach ($contextual_filters as $filter_id) {
      $form['list_page_configuration']['wrapper']['contextual_filters']['buttons'][$filter_id]['delete-' . $filter_id] = [
        '#type' => 'button',
        '#value' => $this->t('Delete'),
        '#name' => 'delete-' . $filter_id,
        '#filter_id' => $filter_id,
        '#facet_id' => $filter_id,
        '#op' => 'remove-contextual-value',
        '#limit_validation_errors' => [
          array_merge($form['#parents'], [
            'list_page_configuration',
            'wrapper',
            'contextual_filters',
            'buttons',
            $filter_id,
            'delete-' . $filter_id,
          ]),
        ],
        '#ajax' => [
          'callback' => [$this, 'removeContextualFilterAjax'],
          'wrapper' => $ajax_wrapper_id,
        ],
        '#executes_submit_callback' => TRUE,
        '#submit' => [[$this, 'deleteContextualValueSubmit']],
      ];

      $rows[] = [
        [
          'data' => $available_filters[$filter_id],
          'facet_id' => $filter_id,
        ],
        ['data' => ''],
        ['data' => ''],
      ];
    }

    $form['list_page_configuration']['wrapper']['contextual_filters']['summary']['table'] = [
      '#type' => 'table',
      '#title' => $this->t('Contextual values'),
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No default values set.'),
      '#attributes' => [
        'class' => ['contextual-filter-values-table'],
      ],
    ];

    $form['list_page_configuration']['wrapper']['contextual_filters']['summary']['add_new'] = [
      '#type' => 'select',
      '#title' => $this->t('Add contextual filters:'),
      '#default_value' => '',
      '#options' => ['' => $this->t('- None -')] + $available_filters,
      '#ajax' => [
        'callback' => [$this, 'addContextualFilterAjax'],
        'wrapper' => $ajax_wrapper_id,
      ],
      '#executes_submit_callback' => TRUE,
      '#submit' => [[$this, 'addDefaultValueSubmit']],
      '#limit_validation_errors' => [
        array_merge($form['#parents'], [
          'list_page_configuration',
          'wrapper',
          'contextual_filters',
          'summary',
          'add_new',
        ]),
      ],
    ];

    if (isset($form['list_page_configuration']['wrapper']['exposed_filters'])) {
      // We don't need the form to expose filters because in link lists we don't
      // use exposed facets.
      $form['list_page_configuration']['wrapper']['exposed_filters_override']['#access'] = FALSE;
      $form['list_page_configuration']['wrapper']['exposed_filters']['#access'] = FALSE;
    }
    $form['list_page_configuration']['wrapper']['contextual_filters']['#pre_render'][] = [get_class($this), 'preRenderOperationButtons'];

    return $form;
  }

  /**
   * Pre-render callback to move the operation buttons to table rows.
   *
   * This is needed for ajax to properly work in these buttons.
   *
   * @param array $form
   *   The form to alter.
   *
   * @return array
   *   The altered array.
   */
  public static function preRenderOperationButtons(array $form) {
    $rows =& $form['summary']['table']['#rows'];
    for ($i = 0; $i < count($rows); $i++) {
      $facet_id = $rows[$i][0]['facet_id'];
      $rows[$i][2]['data'] = [
        'delete-' . $facet_id => $form['buttons'][$facet_id]['delete-' . $facet_id],
      ];
    }
    unset($form['buttons']);
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
    $this->configuration['contextual_filters'] = static::getListSourceContextualFilterValues($form_state, $form_state->getStorage()['list_source']);
  }

}
