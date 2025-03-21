<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Value object to store the list page configuration.
 */
class ListPageConfiguration {

  /**
   * The entity type.
   *
   * phpcs:disable
   *
   * @var string
   */
  protected $entity_type;

  /**
   * The bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The exposed filters.
   *
   * @var array
   */
  protected $exposed_filters = [];

  /**
   * Whether the exposed filters are overridden.
   *
   * @var bool
   */
  protected $exposed_filters_overridden = FALSE;

  /**
   * Whether this configuration supports default filter values.
   *
   * Default filter values are preset values on the query which cannot be
   * removed.
   *
   * @var bool
   */
  protected $default_filter_values_allowed = FALSE;

  /**
   * The default filter values.
   *
   * @var array
   */
  protected $default_filter_values = [];

  /**
   * The limit for the query.
   *
   * @var int|null
   * phpcs:enable
   */
  protected $limit = NULL;

  /**
   * The page of the query.
   *
   * @var int|null
   */
  protected $page = NULL;

  /**
   * The sorting type for the query.
   *
   * The sort should an array with two values keyed 'name' and 'direction'.
   *
   * @var array
   */
  protected $sort = [];

  // phpcs:disable Drupal.NamingConventions.ValidVariableName.LowerCamelName
  /**
   * Whether the sort is exposed to the frontend.
   *
   * @var bool
   */
  protected $exposed_sort = FALSE;

  /**
   * A map of configuration values specific to various subsystems.
   *
   * @var array
   */
  protected $extra = [];

  /**
   * An optional list source with which this configuration works.
   *
   * This is used in special cases in which the default list source factory
   * is not used and a different list source is therefore needed.
   *
   * @var \Drupal\oe_list_pages\ListSourceInterface|null
   */
  protected $listSource = NULL;

  /**
   * The languages used in the list.
   *
   * @var array
   */
  protected $languages = [];

  /**
   * ListPageConfiguration constructor.
   *
   * @param array $configuration
   *   The array of configuration.
   */
  public function __construct(array $configuration) {
    foreach ($configuration as $key => $value) {
      if (property_exists($this, $key)) {
        $this->{$key} = $value;
      }
      else {
        $this->extra[$key] = $value;
      }
    }
  }

  /**
   * Creates an instance from an entity that has a list page entity meta.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\oe_list_pages\ListPageConfiguration
   *   The configuration.
   */
  public static function fromEntity(ContentEntityInterface $entity): ListPageConfiguration {
    /** @var \Drupal\emr\Entity\EntityMetaInterface $entity_meta */
    $entity_meta = $entity->get('emr_entity_metas')->getEntityMeta('oe_list_page');
    /** @var \Drupal\oe_list_pages\ListPageWrapper $wrapper */
    $wrapper = $entity_meta->getWrapper();
    $wrapper_configuration = $wrapper->getConfiguration();
    $configuration = [
      'entity_type' => $wrapper->getSourceEntityType(),
      'bundle' => $wrapper->getSourceEntityBundle(),
      'exposed_filters' => $wrapper_configuration['exposed_filters'] ?? [],
      'default_filter_values' => $wrapper_configuration['preset_filters'] ?? [],
      'exposed_filters_overridden' => isset($wrapper_configuration['override_exposed_filters']) ? (bool) $wrapper_configuration['override_exposed_filters'] : FALSE,
      'limit' => $wrapper_configuration['limit'] ?? NULL,
      'page' => $wrapper_configuration['page'] ?? NULL,
      'sort' => $wrapper_configuration['sort'] ?? [],
      'exposed_sort' => $wrapper_configuration['exposed_sort'] ?? FALSE,
    ];

    $exclude = [
      'preset_filters',
      'override_exposed_filters',
    ];
    foreach ($wrapper_configuration as $key => $values) {
      if (!isset($configuration[$key]) && !in_array($key, $exclude)) {
        $configuration[$key] = $values;
      }
    }

    return new static($configuration);
  }

  /**
   * Returns the entity type.
   *
   * @return string
   *   The entity type.
   */
  public function getEntityType(): ?string {
    return $this->entity_type;
  }

  /**
   * Sets the entity type.
   *
   * @param string $entity_type
   *   The entity type.
   */
  public function setEntityType(?string $entity_type = NULL): void {
    $this->entity_type = $entity_type;
  }

  /**
   * Returns the bundle.
   *
   * @return string
   *   The bundle.
   */
  public function getBundle(): ?string {
    return $this->bundle;
  }

  /**
   * Sets the bundle.
   *
   * @param string $bundle
   *   The bundle.
   */
  public function setBundle(?string $bundle = NULL): void {
    $this->bundle = $bundle;
  }

  /**
   * Returns the exposed filters.
   *
   * @return array
   *   The exposed filters.
   */
  public function getExposedFilters(): array {
    return $this->exposed_filters;
  }

  /**
   * Sets the exposed filters.
   *
   * @param array $exposed_filters
   *   The exposed filters.
   */
  public function setExposedFilters(array $exposed_filters): void {
    $this->exposed_filters = $exposed_filters;
  }

  /**
   * Returns whether the exposed filters are overridden.
   *
   * @return bool
   *   TRUE if overridden, FALSE if not.
   */
  public function isExposedFiltersOverridden(): bool {
    return (bool) $this->exposed_filters_overridden;
  }

  /**
   * Sets whether the exposed filters are overridden.
   *
   * @param bool $exposed_filters_overridden
   *   TRUE if overridden, FALSE if not.
   */
  public function setExposedFiltersOverridden(bool $exposed_filters_overridden): void {
    $this->exposed_filters_overridden = $exposed_filters_overridden;
  }

  /**
   * Returns whether default filter values are allowed.
   *
   * @return bool
   *   Whether default filter values are allowed.
   */
  public function areDefaultFilterValuesAllowed(): bool {
    return $this->default_filter_values_allowed;
  }

  /**
   * Sets whether default filter values are allowed.
   *
   * @param bool $allowed
   *   Whether default filter values are allowed.
   */
  public function setDefaultFilterValuesAllowed(bool $allowed): void {
    $this->default_filter_values_allowed = $allowed;
  }

  /**
   * Returns the default filter values.
   *
   * @return \Drupal\oe_list_pages\ListPresetFilter[]
   *   The default filter values.
   */
  public function getDefaultFiltersValues(): array {
    return $this->default_filter_values;
  }

  /**
   * Sets the default filter values.
   *
   * @param array $default_filter_values
   *   The default filter values.
   */
  public function setDefaultFilterValues(array $default_filter_values): void {
    $this->default_filter_values = $default_filter_values;
  }

  /**
   * Returns the limit.
   *
   * @return int
   *   The limit.
   */
  public function getLimit(): ?int {
    return $this->limit;
  }

  /**
   * Sets the limit.
   *
   * @param int $limit
   *   The limit.
   */
  public function setLimit(?int $limit = NULL): void {
    $this->limit = $limit;
  }

  /**
   * Returns the page.
   *
   * @return int
   *   The page.
   */
  public function getPage(): ?int {
    return $this->page;
  }

  /**
   * Sets the page.
   *
   * @param int $page
   *   The page.
   */
  public function setPage(?int $page = NULL): void {
    $this->page = $page;
  }

  /**
   * Returns the sort.
   *
   * @return array
   *   The sort.
   */
  public function getSort(): array {
    return $this->sort;
  }

  /**
   * Sets the sort.
   *
   * @param array $sort
   *   The sort.
   */
  public function setSort(array $sort): void {
    $this->sort = $sort;
  }

  /**
   * Returns whether the sort is exposed.
   *
   * @return bool
   *   If the sort is exposed.
   */
  public function isExposedSort(): bool {
    return $this->exposed_sort;
  }

  /**
   * Sets the exposed sort.
   *
   * @param bool $exposed_sort
   *   The exposed sort.
   */
  public function setExposedSort(bool $exposed_sort): void {
    $this->exposed_sort = $exposed_sort;
  }

  /**
   * Returns the extra configuration.
   *
   * @return array
   *   The extra configuration.
   */
  public function getExtra(): array {
    return $this->extra;
  }

  /**
   * Sets the extra configuration.
   *
   * @param array $extra
   *   The extra configuration.
   */
  public function setExtra(array $extra): void {
    $this->extra = $extra;
  }

  /**
   * Returns an ID for the configuration.
   *
   * Calculates a hash based on the configuration values so it can be used
   * for static caching results based on this configuration.
   *
   * @return string
   *   The ID.
   */
  public function getId(): string {
    return Crypt::hashBase64(serialize($this->toArray()));
  }

  /**
   * Returns all the configuration values.
   *
   * @return array
   *   The configuration values.
   */
  public function toArray(): array {
    return [
      'entity_type' => $this->getEntityType(),
      'bundle' => $this->getBundle(),
      'exposed_filters_overridden' => $this->isExposedFiltersOverridden(),
      'exposed_filters' => $this->getExposedFilters(),
      'default_filter_values' => $this->getDefaultFiltersValues(),
      'limit' => $this->getLimit(),
      'page' => $this->getPage(),
      'sort' => $this->getSort(),
      'extra' => $this->getExtra(),
    ];
  }

  /**
   * Returns the optional list source.
   *
   * @return \Drupal\oe_list_pages\ListSourceInterface|null
   *   The list source if set.
   */
  public function getListSource(): ?ListSourceInterface {
    return $this->listSource;
  }

  /**
   * Sets the optional list source.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   */
  public function setListSource(ListSourceInterface $list_source): void {
    $this->listSource = $list_source;
  }

  /**
   * Returns the languages.
   *
   * @return array
   *   The languages.
   */
  public function getLanguages(): array {
    return $this->languages;
  }

  /**
   * Sets the language.
   *
   * @param array $languages
   *   The languages.
   */
  public function setLanguages(array $languages): void {
    $this->languages = $languages;
  }

}
