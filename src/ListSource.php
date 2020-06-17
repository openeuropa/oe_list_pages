<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Plugin\PluginBase;
use Drupal\search_api\Datasource\DatasourceInterface;

/**
 * List source object.
 */
class ListSource {

  /**
   * The id for the list source.
   *
   * @var string
   */
  protected $id;

  /**
   * The entity type.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The data source.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface
   */
  protected $dataSource;

  /**
   * ListSource constructor.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   */
  public function __construct(string $entity_type, string $bundle) {
    $this->entityType = $entity_type;
    $this->bundle = $bundle;
    $this->id = 'list_facet_source' . PluginBase::DERIVATIVE_SEPARATOR . $entity_type . PluginBase::DERIVATIVE_SEPARATOR . $bundle;

  }

  /**
   * Get available filters for the list source.
   *
   * @return array
   *   The filters.
   */
  public function getAvailableFilters(): array {
    $filters = [];
    $facets = \Drupal::service('facets.manager')->getFacetsByFacetSourceId($this->id());
    foreach ($facets as $facet) {
      $field_id = $facet->getFieldIdentifier();
      $filters[$field_id] = $facet->getFacetSource()->getIndex()->getField($field_id)->getLabel();
    }

    return $filters;
  }

  /**
   * Gets the bundle.
   *
   * @return string
   *   The bundle
   */
  public function getBundle(): string {
    return $this->bundle;
  }

  /**
   * Gets the entity type.
   *
   * @return string
   *   The entity type.
   */
  public function getEntityType(): string {
    return $this->entityType;
  }

  /**
   * Gets associated search api data source.
   *
   * @return \Drupal\search_api\Datasource\DatasourceInterface
   *   The data source.
   */
  public function getDataSource(): DatasourceInterface {
    return $this->dataSource;
  }

  /**
   * Sets search api data source.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface $dataSource
   *   The search api data source.
   */
  public function setDataSource(DatasourceInterface $dataSource): void {
    $this->dataSource = $dataSource;
  }

  /**
   * Get search id.
   *
   * @return string
   *   The search id.
   *
   * @SuppressWarnings(PHPMD.ShortMethodName)
   */
  public function id(): string {
    return $this->id;
  }

}
