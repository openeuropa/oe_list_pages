<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\search_api\Item\ItemInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Default list execution manager implementation.
 */
class ListExecutionManager implements ListExecutionManagerInterface {

  /**
   * The list source factory.
   *
   * @var \Drupal\oe_list_pages\ListSourceFactoryInterface
   */
  protected $listSourceFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The custom sort processor.
   *
   * @var \Drupal\oe_list_pages\CustomSortProcessor
   */
  protected $customSortProcessor;

  /**
   * The already executed lists, keyed by entity UUID.
   *
   * @var \Drupal\oe_list_pages\ListExecutionResults[]
   */
  protected $executedLists = [];

  /**
   * The sort options resolver.
   *
   * @var \Drupal\oe_list_pages\ListPageSortOptionsResolver
   */
  protected $sortOptionsResolver;

  /**
   * ListManager constructor.
   *
   * @param \Drupal\oe_list_pages\ListSourceFactoryInterface $listSourceFactory
   *   The list source factory.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\oe_list_pages\ListPageSortOptionsResolver $sortOptionsResolver
   *   The sort options resolver.
   * @param \Drupal\oe_list_pages\CustomSortProcessor|null $customSortProcessor
   *   The custom sort processor.
   */
  public function __construct(ListSourceFactoryInterface $listSourceFactory, EntityTypeManager $entityTypeManager, RequestStack $requestStack, LanguageManagerInterface $languageManager, ListPageSortOptionsResolver $sortOptionsResolver, ?CustomSortProcessor $customSortProcessor = NULL) {
    $this->listSourceFactory = $listSourceFactory;
    $this->entityTypeManager = $entityTypeManager;
    $this->requestStack = $requestStack;
    $this->languageManager = $languageManager;
    $this->sortOptionsResolver = $sortOptionsResolver;
    $this->customSortProcessor = $customSortProcessor ?? new CustomSortProcessor();
  }

  /**
   * {@inheritdoc}
   */
  public function executeList(ListPageConfiguration $configuration): ?ListExecutionResults {
    if (!empty($this->executedLists[$configuration->getId()])) {
      return $this->executedLists[$configuration->getId()];
    }

    // The number of items to show on a page.
    $limit = $configuration->getLimit() ?? 10;
    $list_source = $configuration->getListSource() ?? $this->listSourceFactory->get($configuration->getEntityType(), $configuration->getBundle());
    if (!$list_source) {
      $this->executedLists[$configuration->getId()] = NULL;
      return NULL;
    }

    // Determine the query options and execute it.
    $current_page = is_null($configuration->getPage()) ? (int) $this->requestStack->getCurrentRequest()->get('page', 0) : $configuration->getPage();
    if ($current_page < 0) {
      $current_page = 0;
    }
    $language = !empty($configuration->getLanguages()) ? $configuration->getLanguages() : $this->languageManager->getCurrentLanguage()->getId();
    $preset_filters = $configuration->getDefaultFiltersValues();
    // Build the sort array. Priority:
    // 1. Runtime sort (from query params / user selection)
    // 2. List page configured default sort (multi-criteria)
    // 3. Bundle's default sort (fallback)
    $sort = [];
    $runtime_sort = $configuration->getSort();

    // Check to see if this sort is still allowed as we could have stored
    // configuration with a given sort that has been disabled.
    if ($runtime_sort && isset($configuration->getExtra()['context_entity'])) {
      $context_entity_info = $configuration->getExtra()['context_entity'];
      $context_entity = $this->entityTypeManager->getStorage($context_entity_info['entity_type'])->load($context_entity_info['entity_id']);

      $options = $this->sortOptionsResolver->getSortOptions($list_source, [
        ListPageSortOptionsResolver::SCOPE_SYSTEM,
        ListPageSortOptionsResolver::SCOPE_CONFIGURATION,
        ListPageSortOptionsResolver::SCOPE_USER,
      ], $context_entity);
      $sort_name = ListPageSortOptionsResolver::generateSortMachineName($runtime_sort);
      if (!isset($options[$sort_name])) {
        $runtime_sort = [];
      }
    }

    if ($runtime_sort) {
      $sort[$runtime_sort['name']] = $runtime_sort['direction'];
    }

    // Apply list page's configured default sort criteria.
    $default_sort_criteria = $configuration->getDefaultSort();
    if (!empty($default_sort_criteria)) {
      // Sort by weight to ensure correct order.
      uasort($default_sort_criteria, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
      foreach ($default_sort_criteria as $criterion) {
        if (!empty($criterion['name']) && !isset($sort[$criterion['name']])) {
          $sort[$criterion['name']] = $criterion['direction'] ?? 'ASC';
        }
      }
    }

    // Fallback to bundle's default sort if no sort configured.
    if (empty($sort)) {
      $bundle_sort = $this->getBundleDefaultSort($list_source);
      if ($bundle_sort) {
        $sort[$bundle_sort['name']] = $bundle_sort['direction'];
      }
    }

    // Get promotion settings.
    $promotion = $configuration->getPromotion();

    // Backward compatibility: old 'values' format to new 'rules' format.
    if (!empty($promotion['values']) && empty($promotion['rules'])) {
      $promotion['rules'] = [];
      foreach ($promotion['values'] as $pv) {
        $promotion['rules'][] = [
          'weight' => $pv['weight'] ?? 0,
          'conditions' => [
            [
              'field' => $pv['field'] ?? '',
              'operator' => '=',
              'value' => $pv['value'] ?? '',
            ],
          ],
        ];
      }
    }

    $has_promotion = !empty($promotion['enabled']) && !empty($promotion['rules']);

    // Track promoted entity IDs for marking in the UI.
    $promoted_entity_ids = [];

    // If promotion is enabled, we need a different approach:
    // 1. First fetch promoted items (for page 0 display and to know excludes)
    // 2. Then fetch regular items (excluding promoted ones)
    // 3. Combine them respecting the limit and pagination.
    if ($has_promotion) {
      $promotion_result = $this->executeListWithPromotion(
        $list_source,
        $limit,
        $current_page,
        $language,
        $sort,
        $preset_filters,
        $configuration->getExtra(),
        $promotion
      );
      $result = $promotion_result['result'];
      $promoted_entity_ids = $promotion_result['promoted_entity_ids'];
      $query = $list_source->getQuery([
        'limit' => $limit,
        'page' => $current_page,
        'language' => $language,
        'sort' => $sort,
        'preset_filters' => $preset_filters,
        'extra' => $configuration->getExtra(),
      ]);
    }
    else {
      $options = [
        'limit' => $limit,
        'page' => $current_page,
        'language' => $language,
        'sort' => $sort,
        'preset_filters' => $preset_filters,
        'extra' => $configuration->getExtra(),
      ];
      $query = $list_source->getQuery($options);
      $result = $query->execute();
    }

    $list_execution = new ListExecutionResults($query, $result, $list_source, $configuration, $promoted_entity_ids);

    $this->executedLists[$configuration->getId()] = $list_execution;

    return $list_execution;
  }

  /**
   * Executes a list query with promotion support.
   *
   * This method fetches promoted items first, then fills the remaining slots
   * with regular items, ensuring no duplicates and correct pagination.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param int $limit
   *   The number of items per page.
   * @param int $current_page
   *   The current page number (0-indexed).
   * @param string|array $language
   *   The language(s) to filter by.
   * @param array $sort
   *   The sort configuration.
   * @param array $preset_filters
   *   The preset filters.
   * @param array $extra
   *   Extra configuration.
   * @param array $promotion
   *   The promotion settings.
   *
   * @return array
   *   An array with keys:
   *   - 'result': The combined result set.
   *   - 'promoted_entity_ids': Array of promoted entity IDs.
   */
  protected function executeListWithPromotion(ListSourceInterface $list_source, int $limit, int $current_page, $language, array $sort, array $preset_filters, array $extra, array $promotion): array {
    $promoted_items = [];
    $promoted_entity_ids = [];

    // Step 1: Always fetch ALL promoted items first (we need to know the count
    // for pagination offset calculation, and their IDs to exclude them).
    // Each rule can have multiple conditions (combined with AND).
    $rules = $promotion['rules'] ?? [];

    foreach ($rules as $rule) {
      $conditions = $rule['conditions'] ?? [];
      if (empty($conditions)) {
        continue;
      }

      // Create a query to find items matching this promotion rule.
      // Fetch a reasonable maximum to get all promoted items.
      $promo_query = $list_source->getQuery([
        'limit' => 100,
        'page' => 0,
        'language' => $language,
        'sort' => $sort,
        'preset_filters' => $preset_filters,
        'extra' => $extra,
      ]);

      // Add all conditions for this rule (AND logic).
      foreach ($conditions as $condition) {
        if (empty($condition['field'])) {
          continue;
        }

        $field = $condition['field'];
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? '';

        // Handle special value "now" for date comparisons.
        if (strtolower($value) === 'now') {
          $value = date('Y-m-d\TH:i:s');
        }

        // Map operators to Search API operators.
        $promo_query->addCondition($field, $value, $operator);
      }

      $promo_result = $promo_query->execute();
      $promo_items_found = $promo_result->getResultItems();

      foreach ($promo_items_found as $item_id => $item) {
        // Extract entity ID to avoid duplicates.
        $entity_id = $this->extractEntityIdFromItem($item);
        if ($entity_id && !in_array($entity_id, $promoted_entity_ids)) {
          $promoted_items[$item_id] = $item;
          $promoted_entity_ids[] = $entity_id;
        }
      }
    }

    $total_promoted = count($promoted_items);

    // Step 2: Calculate pagination.
    // Promoted items come first, then regular items follow.
    // We need to determine which items to show based on the virtual position.
    //
    // Virtual list structure:
    // [0 ... total_promoted-1] = promoted items
    // [total_promoted ... total_items] = regular items
    //
    // For page N with limit L:
    // - Start position: N * L
    // - End position: (N + 1) * L - 1.
    $page_start = $current_page * $limit;
    $page_end = $page_start + $limit;

    $final_items = [];

    // Determine how many promoted items fall within this page's range.
    if ($page_start < $total_promoted) {
      // Some promoted items are on this page.
      $promo_start = $page_start;
      $promo_end = min($page_end, $total_promoted);
      $promo_count = $promo_end - $promo_start;

      // Get the slice of promoted items for this page.
      $promoted_slice = array_slice($promoted_items, $promo_start, $promo_count, TRUE);
      $final_items = $promoted_slice;
    }

    // Calculate how many regular items we need to fill the page.
    $promoted_on_page = count($final_items);
    $regular_needed = $limit - $promoted_on_page;

    if ($regular_needed > 0) {
      // Calculate offset for regular items.
      // Regular items start after all promoted items in the virtual list.
      // If we're on a page where promoted items end mid-page regular:
      // offset = 0.
      // If we're on a page without promoted items:
      // offset = page_start - total_promoted.
      $regular_offset = max(0, $page_start - $total_promoted);

      // Fetch regular items excluding promoted ones.
      $regular_items = $this->fetchRegularItemsExcludingPromoted(
        $list_source,
        $regular_needed,
        $regular_offset,
        $language,
        $sort,
        $preset_filters,
        $extra,
        $promoted_entity_ids
      );

      // Combine promoted + regular.
      $final_items = $final_items + $regular_items;
    }

    // Create a result set with the final items.
    // We need a base query to create a proper result set.
    $base_query = $list_source->getQuery([
      'limit' => $limit,
      'page' => $current_page,
      'language' => $language,
      'sort' => $sort,
      'preset_filters' => $preset_filters,
      'extra' => $extra,
    ]);
    $base_result = $base_query->execute();
    $base_result->setResultItems($final_items);

    return [
      'result' => $base_result,
      'promoted_entity_ids' => $promoted_entity_ids,
    ];
  }

  /**
   * Fetches regular items excluding promoted ones.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param int $needed
   *   Number of items needed.
   * @param int $offset
   *   The offset to start from (in regular items, not counting promoted).
   * @param string|array $language
   *   The language(s) to filter by.
   * @param array $sort
   *   The sort configuration.
   * @param array $preset_filters
   *   The preset filters.
   * @param array $extra
   *   Extra configuration.
   * @param array $promoted_entity_ids
   *   Entity IDs to exclude.
   *
   * @return array
   *   Array of Search API items.
   */
  protected function fetchRegularItemsExcludingPromoted(ListSourceInterface $list_source, int $needed, int $offset, $language, array $sort, array $preset_filters, array $extra, array $promoted_entity_ids): array {
    $result_items = [];
    $skipped = 0;
    $page = 0;
    // Fetch extra to account for exclusions.
    $fetch_limit = $needed + count($promoted_entity_ids) + 10;

    while (count($result_items) < $needed) {
      $query = $list_source->getQuery([
        'limit' => $fetch_limit,
        'page' => $page,
        'language' => $language,
        'sort' => $sort,
        'preset_filters' => $preset_filters,
        'extra' => $extra,
      ]);

      $result = $query->execute();
      $items = $result->getResultItems();

      if (empty($items)) {
        // No more items available.
        break;
      }

      foreach ($items as $item_id => $item) {
        $entity_id = $this->extractEntityIdFromItem($item);

        // Skip promoted items.
        if ($entity_id && in_array($entity_id, $promoted_entity_ids)) {
          continue;
        }

        // Handle offset: skip items until we reach the desired offset.
        if ($skipped < $offset) {
          $skipped++;
          continue;
        }

        $result_items[$item_id] = $item;

        if (count($result_items) >= $needed) {
          break 2;
        }
      }

      $page++;

      // Safety limit to prevent infinite loops.
      if ($page > 100) {
        break;
      }
    }

    return $result_items;
  }

  /**
   * Extracts the entity ID from a Search API item.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The search result item.
   *
   * @return string|null
   *   The entity ID or NULL if not extractable.
   */
  protected function extractEntityIdFromItem(ItemInterface $item): ?string {
    try {
      $original_object = $item->getOriginalObject();
      if ($original_object) {
        $entity = $original_object->getValue();
        if ($entity instanceof EntityInterface) {
          return $entity->getEntityTypeId() . ':' . $entity->id();
        }
      }
    }
    catch (\Exception $e) {
      // Fallback: try to parse the item ID.
    }

    // Fallback: parse item ID (format: entity:node/123:en).
    $item_id = $item->getId();
    if (preg_match('/entity:(\w+)\/(\d+)/', $item_id, $matches)) {
      return $matches[1] . ':' . $matches[2];
    }

    return NULL;
  }

  /**
   * Get the default sort configuration from the bundle.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The selected list source.
   *
   * @return array
   *   The sort information.
   */
  protected function getBundleDefaultSort(ListSourceInterface $list_source): array {
    $bundle_entity_type = $this->entityTypeManager->getDefinition($list_source->getEntityType())->getBundleEntityType();
    if (!$bundle_entity_type) {
      // We can have entity types that have no bundles.
      return [];
    }
    $storage = $this->entityTypeManager->getStorage($bundle_entity_type);
    $bundle = $storage->load($list_source->getBundle());
    return $bundle->getThirdPartySetting('oe_list_pages', 'default_sort', []);
  }

}
