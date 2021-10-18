<?php

declare(strict_types = 1);

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
      'sort' => [],
    ];

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
  public function setEntityType(string $entity_type = NULL): void {
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
  public function setBundle(string $bundle = NULL): void {
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
  public function setLimit(int $limit = NULL): void {
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
  public function setPage(int $page = NULL): void {
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
    ];
  }

}
