<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * List sources are associated with a facet source.
 *
 * They contain all required fields to allow to execute searches on bundles
 * that have associated filterable lists.
 */
class ListSource implements ListSourceInterface {

  /**
   * The search id for the list source.
   *
   * @var string
   */
  protected $searchId;

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
   * The associated search api index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The list of available filters.
   *
   * @var array
   */
  protected $filters;

  /**
   * The bundle entity key used to reference the entity bundle.
   *
   * @var string
   */
  protected $bundleKey;

  /**
   * ListSource constructor.
   *
   * @param string $search_id
   *   The id.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $bundle_key
   *   The bundle key.
   * @param \Drupal\search_api\IndexInterface $index
   *   The search api index.
   * @param array $filters
   *   The filters.
   */
  public function __construct(string $search_id, string $entity_type, string $bundle, string $bundle_key, IndexInterface $index, array $filters = []) {
    $this->searchId = $search_id;
    $this->entityType = $entity_type;
    $this->bundle = $bundle;
    $this->bundleKey = $bundle_key;
    $this->index = $index;
    $this->filters = $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableFilters(): array {
    return $this->filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle(): string {
    return $this->bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(): string {
    return $this->entityType;
  }

  /**
   * {@inheritdoc}
   */
  public function getSearchId(): string {
    return $this->searchId;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleKey(): string {
    return $this->bundleKey;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex(): IndexInterface {
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery(array $options = []): QueryInterface {
    $resolver = new OptionsResolver();
    $resolver->setDefaults([
      'limit' => 10,
      'page' => 0,
      'language' => NULL,
      'sort' => [],
      'ignored_filters' => [],
      'preset_filters' => [],
      'extra' => [],
    ]);

    $resolver->setAllowedTypes('limit', 'int');
    $resolver->setAllowedTypes('page', 'int');
    $resolver->setAllowedTypes('language', ['string', 'null']);
    $resolver->setAllowedTypes('sort', 'array');
    $resolver->setAllowedTypes('ignored_filters', 'array');
    $resolver->setAllowedTypes('preset_filters', 'array');
    $resolver->setAllowedTypes('extra', 'array');

    $resolved_options = $resolver->resolve($options);

    $query = $this->index->query([
      'offset' => ($resolved_options['limit'] * $resolved_options['page']),
    ]);

    if ($resolved_options['limit']) {
      $query->setOption('limit', $resolved_options['limit']);
    }

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->getIndex();
    $fields = $index->getFields();

    // Handle multilingual language fallback.
    if ($resolved_options['language'] && in_array('language_with_fallback', array_keys($fields))) {
      $query->addCondition('language_with_fallback', $resolved_options['language']);
    }

    $query_options = new ListQueryOptions($resolved_options['ignored_filters'], $resolved_options['preset_filters'], $resolved_options['extra']);
    $query->setOption('oe_list_page_query_options', $query_options);
    $query->setSearchId($this->getSearchId());

    // Limit search to bundle.
    if (in_array($this->getBundleKey(), array_keys($fields))) {
      $query->addCondition($this->getBundleKey(), $this->getBundle());
    }
    $query->addCondition('search_api_datasource', 'entity:' . $this->getEntityType());

    foreach ($resolved_options['sort'] as $name => $direction) {
      $query->sort($name, $direction);
    }

    return $query;
  }

}
