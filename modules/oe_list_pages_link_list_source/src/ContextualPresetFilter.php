<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_link_list_source;

use Drupal\oe_list_pages\ListPresetFilter;

/**
 * Contextual filters specific preset filters.
 */
class ContextualPresetFilter extends ListPresetFilter {

  /**
   * Gets the filter values from the current entity fields.
   */
  const FILTER_SOURCE_FIELD_VALUES = 'field_values';

  /**
   * The filter value is the current entity ID.
   */
  const FILTER_SOURCE_ENTITY_ID = 'entity_id';

  /**
   * {@inheritdoc}
   */
  public function __construct(string $facet_id, array $values, string $operator = self::OR_OPERATOR) {
    parent::__construct($facet_id, $values, $operator);
    // By default, if unspecified, the contextual filters will use the field
    // values as the value source.
    $this->setFilterSource(static::FILTER_SOURCE_FIELD_VALUES);
  }

  /**
   * The source if the contextual filter values.
   *
   * This indicated where the values for the filters will be found.
   *
   * @var string
   */
  protected $filterSource;

  /**
   * Returns the filter source.
   *
   * @return string
   *   The filter source.
   */
  public function getFilterSource(): string {
    return $this->filterSource;
  }

  /**
   * Sets the filter source.
   *
   * @param string $filter_source
   *   The filter source.
   */
  public function setFilterSource(string $filter_source): void {
    $this->filterSource = $filter_source;
  }

  /**
   * Returns the available filter sources.
   *
   * @return array
   *   The filter sources.
   */
  public static function getFilterSources(): array {
    return [
      static::FILTER_SOURCE_FIELD_VALUES => t('Field values'),
      static::FILTER_SOURCE_ENTITY_ID => t('Entity ID'),
    ];
  }

}
