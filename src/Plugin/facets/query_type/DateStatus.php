<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\query_type;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\QueryType\QueryTypePluginBase;
use Drupal\facets\Result\Result;
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
   * The system time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs the DateStatus plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The system time.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('datetime.time')
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
          $filter->addCondition($field_identifier, $this->prepareTimestamp($now), "<=");
        }
        elseif ($value === self::UPCOMING) {
          $condition_group = $query->createConditionGroup('OR');
          $condition_group->addCondition($field_identifier, $this->prepareTimestamp($now), ">");
          $condition_group->addCondition($field_identifier, NULL);
          $filter->addConditionGroup($condition_group);
        }
      }
      $query->addConditionGroup($filter);
      // Apply the sort using the field identifier of the current facet or the
      // overridden one that was configured on the corresponding processor.
      $sort_field_identifier = $field_identifier;
      if ($this->facet->get('default_status_sort_alter_field_identifier')) {
        $sort_field_identifier = $this->facet->get('default_status_sort_alter_field_identifier');
      }
      $this->applySort($query, $active_items, $sort_field_identifier);
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
        $this->prepareTimestamp($now) > $result_filter ? $count[self::UPCOMING]++ : $count[self::PAST]++;
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
   * Prepares the timestamp to be used in the query.
   */
  protected function prepareTimestamp(DrupalDateTime $date): int {
    return $date->getTimestamp();
  }

}
