<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_link_list_source;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\facets\FacetInterface;
use Drupal\oe_list_pages\FacetManipulationTrait;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceFactoryInterface;
use Drupal\oe_list_pages\ListSourceInterface;
use Drupal\oe_list_pages\MultiselectFilterFieldPluginManager;
use Drupal\oe_list_pages_link_list_source\Exception\InapplicableContextualFilter;

/**
 * Processes the contextual filter values from the current context.
 */
class ContextualFilterValuesProcessor {

  use FacetManipulationTrait;

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
   * The contextual filters configuration builder.
   *
   * @var \Drupal\oe_list_pages_link_list_source\ContextualFiltersConfigurationBuilder
   */
  protected $configurationBuilder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The contextual filters field mapper.
   *
   * @var \Drupal\oe_list_pages_link_list_source\ContextualFilterFieldMapper
   */
  protected $contextualFilterFieldMapper;

  /**
   * The multiselect filter field plugin manager.
   *
   * @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface|\Drupal\oe_list_pages\MultiselectFilterFieldPluginManager
   */
  protected $multiselectFilterFieldPluginManager;

  /**
   * ContextualFilterValuesProcessor constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\oe_list_pages\ListSourceFactoryInterface $listSourceFactory
   *   The list source factory.
   * @param \Drupal\oe_list_pages_link_list_source\ContextualFiltersConfigurationBuilder $configurationBuilder
   *   The contextual filters configuration builder.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\oe_list_pages_link_list_source\ContextualFilterFieldMapper $contextualFilterFieldMapper
   *   The contextual filters field mapper.
   * @param \Drupal\oe_list_pages\MultiselectFilterFieldPluginManager $multiselectFilterFieldPluginManager
   *   The multiselect filter field plugin manager.
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(RouteMatchInterface $routeMatch, ListSourceFactoryInterface $listSourceFactory, ContextualFiltersConfigurationBuilder $configurationBuilder, EntityTypeManagerInterface $entityTypeManager, ContextualFilterFieldMapper $contextualFilterFieldMapper, MultiselectFilterFieldPluginManager $multiselectFilterFieldPluginManager) {
    $this->routeMatch = $routeMatch;
    $this->listSourceFactory = $listSourceFactory;
    $this->configurationBuilder = $configurationBuilder;
    $this->entityTypeManager = $entityTypeManager;
    $this->contextualFilterFieldMapper = $contextualFilterFieldMapper;
    $this->multiselectFilterFieldPluginManager = $multiselectFilterFieldPluginManager;
  }

  /**
   * Processes the raw configuration into a configuration object.
   *
   * Looking at the current entity from context, it tries to transform values
   * from it into preset filter values on the configuration.
   *
   * @param array $raw_configuration
   *   The raw configuration.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   The cache metadata.
   *
   * @return \Drupal\oe_list_pages\ListPageConfiguration
   *   The configuration object ready for the query execution.
   */
  public function processConfiguration(array $raw_configuration, CacheableMetadata $cache): ListPageConfiguration {
    $configuration = new ListPageConfiguration($raw_configuration);
    $cache->addCacheContexts(['route']);

    // Add the contextual filters.
    /** @var \Drupal\oe_list_pages_link_list_source\ContextualPresetFilter[] $contextual_filters */
    $contextual_filters = $raw_configuration['contextual_filters'];
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

    // If the link list is configured to exclude the current entity, pass a
    // special default filter value that will be read by a query subscriber.
    // see
    // Drupal\oe_list_pages_link_list_source\EventSubscriber\QuerySubscriber()
    if (isset($raw_configuration['exclude_self']) && (bool) $raw_configuration['exclude_self']) {
      $extra = $configuration->getExtra();
      $extra['exclude_self_data'] = [
        'id' => $entity->id(),
        'entity_type' => $entity->getEntityTypeId(),
        'entity_bundle' => $entity->bundle(),
      ];
      $configuration->setExtra($extra);
    }

    $cache->addCacheableDependency($entity);

    $default_filter_values = $configuration->getDefaultFiltersValues();
    $list_source = $this->listSourceFactory->get($configuration->getEntityType(), $configuration->getBundle());

    foreach ($contextual_filters as $contextual_filter) {
      $values = $this->getValuesForContextualFilter($contextual_filter, $entity, $list_source, $cache);
      $default_filter_values[ContextualFiltersConfigurationBuilder::generateFilterId($contextual_filter->getFacetId(), array_keys($default_filter_values))] = new ListPresetFilter($contextual_filter->getFacetId(), $values, $contextual_filter->getOperator());
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

    if (empty($route_name)) {
      return NULL;
    }

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
    $id = $this->multiselectFilterFieldPluginManager->getPluginIdByFieldType($field_definition->getType());
    if (!$id) {
      return [];
    }

    $config = [
      'facet' => $facet,
      'preset_filter' => [],
      'list_source' => $list_source,
    ];

    /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
    $plugin = $this->multiselectFilterFieldPluginManager->createInstance($id, $config);

    return $plugin->getFieldValues($items);
  }

  /**
   * Returns the contextual filter values from the current entity.
   *
   * @param \Drupal\oe_list_pages_link_list_source\ContextualPresetFilter $contextual_filter
   *   The contextual filter definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The current entity.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   The cacheable metadata.
   *
   * @return array
   *   The filter values.
   */
  protected function getValuesForContextualFilter(ContextualPresetFilter $contextual_filter, ContentEntityInterface $entity, ListSourceInterface $list_source, CacheableMetadata $cache): array {
    $facet = $this->configurationBuilder->getFacetById($list_source, $contextual_filter->getFacetId());
    $processor = ContextualFiltersHelper::getContextualAwareSearchApiProcessor($list_source, $facet);

    // First, determine where the filters need to look for the values.
    if ($contextual_filter->getFilterSource() === ContextualPresetFilter::FILTER_SOURCE_ENTITY_ID && !$processor) {
      // If the current entity ID is the source, we just have to return it.
      return [$entity->id()];
    }

    // Otherwise, load the facet and check the field definition.
    $definition = $this->getFacetFieldDefinition($facet, $list_source);
    if ($definition) {
      $field_name = $definition->getName();
      // Map the field correctly.
      $field_name = $this->contextualFilterFieldMapper->getCorrespondingFieldName($field_name, $entity, $cache);
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

      return $values;
    }

    // If there is no field definition, it may be a custom Search API field
    // processor that may be contextually aware.
    if (!$processor) {
      throw new InapplicableContextualFilter();
    }

    return $processor->getContextualValues($entity, $contextual_filter->getFilterSource());
  }

}
