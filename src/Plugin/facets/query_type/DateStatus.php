<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\query_type;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\facets\QueryType\QueryTypePluginBase;
use Drupal\facets\Result\Result;
use Drupal\oe_list_pages\TimeAwareProcessorInterface;
use Drupal\oe_time_caching\Cache\TimeBasedCacheTagGeneratorInterface;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides support for date status facets.
 *
 * @FacetsQueryType(
 *   id = "oe_list_pages_date_status_query_type",
 *   label = @Translation("Date status"),
 * )
 */
class DateStatus extends QueryTypePluginBase implements ContainerFactoryPluginInterface {

  /**
   * Defines the timezone that dates should be stored in.
   */
  const STORAGE_TIMEZONE = 'UTC';

  /**
   * Option for upcoming items.
   */
  const UPCOMING = 'upcoming';

  /**
   * Option for past items.
   */
  const PAST = 'past';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The system time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The time based cache generator.
   *
   * @var \Drupal\oe_time_caching\Cache\TimeBasedCacheTagGeneratorInterface
   */
  protected $timeBasedCacheTagGenerator;

  /**
   * Constructs the DateStatus plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The system time.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The system time.
   * @param \Drupal\oe_time_caching\Cache\TimeBasedCacheTagGeneratorInterface $time_based_cache_tag_generator
   *   The time based cache generator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time, TimeBasedCacheTagGeneratorInterface $time_based_cache_tag_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
    $this->timeBasedCacheTagGenerator = $time_based_cache_tag_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('datetime.time'),
      $container->get('oe_time_caching.time_based_cache_tag_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $query = $this->query;
    // Only alter the query when there's an actual query object to alter.
    if (empty($query)) {
      return;
    }
    $field_identifier = $this->facet->getFieldIdentifier();
    if ($query->getProcessingLevel() === QueryInterface::PROCESSING_FULL) {
      // Set the options for the actual query.
      $options = &$query->getOptions();
      $options['search_api_facets'][$field_identifier] = $this->getFacetOptions();
    }
    // Add the filter to the query if there are active values.
    $active_items = $this->facet->getActiveItems();
    $now = $this->getCurrentTime();
    if (count($active_items)) {
      $filter = $query->createConditionGroup('OR', ['facet:' . $field_identifier]);
      foreach ($active_items as $value) {
        if ($value === self::PAST) {
          $filter->addCondition($this->facet->getFieldIdentifier(), $now->getTimestamp(), "<=");
        }
        elseif ($value === self::UPCOMING) {
          $condition_group = $query->createConditionGroup('OR');
          $condition_group->addCondition($this->facet->getFieldIdentifier(), $now->getTimestamp(), ">");
          $condition_group->addCondition($this->facet->getFieldIdentifier(), NULL);
          $filter->addConditionGroup($condition_group);
        }
      }
      $query->addConditionGroup($filter);
      $this->addTimeCacheTags($query);
      $this->applySort($query, $active_items, $this->facet->getFieldIdentifier());
    }
  }

  /**
   * Gets current date time.
   *
   * @return \DateTimeZone
   *   The date time.
   */
  protected function getCurrentTime(): DrupalDateTime {
    $now = new DrupalDateTime();
    $current_time = $this->time->getCurrentTime();
    $now->setTimestamp($current_time);
    $now->setTimezone(new \DateTimeZone(self::STORAGE_TIMEZONE));
    return $now;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $now = $this->getCurrentTime();
    $count = $facet_results = [];
    $count[self::UPCOMING] = $count[self::PAST] = 0;
    if (!empty($this->results)) {
      foreach ($this->results as $result) {
        $result_filter = trim($result['filter'], '"');
        $now->getTimestamp() > $result_filter ? $count[self::UPCOMING]++ : $count[self::PAST]++;
      }
    }

    // Get default options for status.
    $default_options = [self::UPCOMING, self::PAST];
    foreach ($default_options as $option) {
      $item_count = $count[$option] ?? 0;
      $result = new Result($this->facet, $option, $option, $item_count);
      $facet_results[] = $result;
    }

    $this->facet->setResults($facet_results);
    return $this->facet;
  }

  /**
   * Changes the sort on the query depending the chosen filter.
   *
   * With this type of filter, the order of the items is dependent on the
   * chosen filter so we need to change the sort accordingly.
   *
   * When applying the sort, we have to override potentially existing sorting
   * so we don't use the ::sort() method on the query object.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   * @param array $active_items
   *   The active items.
   * @param string $field_name
   *   The field name to sort by.
   */
  protected function applySort(QueryInterface $query, array $active_items, string $field_name): void {
    $sorts = &$query->getSorts();
    if (count($active_items) > 1) {
      // In case we have both options selected, we sort DESC.
      $sorts[$field_name] = 'DESC';
      return;
    }

    $item = reset($active_items);
    // Past items should be sorted DESC whereas upcoming ones ASC.
    $sorts[$field_name] = $item === self::PAST ? 'DESC' : 'ASC';
  }

  /**
   * Adds time based cache tags to a query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   */
  protected function addTimeCacheTags(QueryInterface $query): void {
    $index = $this->query->getIndex();
    $field = $index->getField($this->facet->getFieldIdentifier());
    $property = $field->getPropertyPath();
    // Check whether the field is generated through a processor.
    foreach ($index->getProcessors() as $processor) {
      $generated_properties = $processor->getPropertyDefinitions();
      if (!$generated_properties) {
        continue;
      }
      if (!array_key_exists($property, $generated_properties)) {
        continue;
      }
      if ($processor instanceof TimeAwareProcessorInterface) {
        $processor->addTimeCacheTags($query, $property);
        return;
      }
    }
    [$field_id, $value_id] = explode(':', $property);
    $now = $this->getCurrentTime();

    $indexed_entity_types = $index->getEntityTypes();
    $possible_dates = [];
    foreach ($indexed_entity_types as $entity_type) {
      $storage = $this->entityTypeManager->getStorage($entity_type);
      $results = $storage->getQuery()
        ->condition($field_id . '.' . $value_id, $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), ">")
        ->sort($field_id . '.' . $value_id, 'ASC')
        ->execute();
      if (!empty($results)) {
        $next_entity = $this->entityTypeManager->getStorage('node')->load(reset($results));
        $next_date = new DrupalDateTime($next_entity->$field_id->{$value_id}, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
        $possible_dates[$next_entity->getPhpDateTime()->getTimestamp()] = $next_date->getPhpDateTime();
      }
    }
    ksort($possible_dates);
    $next_date = reset($possible_dates);
    $query->addCacheTags($this->timeBasedCacheTagGenerator->generateTags($next_date));
  }

}
