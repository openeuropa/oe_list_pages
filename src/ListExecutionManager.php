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
      // Use the EXECUTED base query so cache tags accumulated during
      // execution (facets, preset filters, etc.) are carried downstream.
      $query = $promotion_result['query'];
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
   * Promoted items (matching the promotion rules) are surfaced first in the
   * virtual list; regular items (everything matching preset filters minus
   * promoted) follow. The current page is then sliced out of that virtual
   * list.
   *
   * @return array
   *   An array with keys:
   *   - 'result': The combined result set.
   *   - 'query': The executed base query (carries cache tags).
   *   - 'promoted_entity_ids': Array of promoted entity IDs.
   */
  protected function executeListWithPromotion(ListSourceInterface $list_source, int $limit, int $current_page, $language, array $sort, array $preset_filters, array $extra, array $promotion): array {
    // Step 1: Fetch every promoted item once.
    //
    // We need their total count to compute pagination offsets, and their IDs
    // to exclude them from the regular query so an item never appears twice.
    // Each rule can have multiple conditions combined with AND; items
    // matching several rules are deduplicated by entity ID.
    $promoted_items = [];
    $promoted_entity_ids = [];
    foreach ($promotion['rules'] ?? [] as $rule) {
      $conditions = $rule['conditions'] ?? [];
      if (empty($conditions)) {
        continue;
      }
      $rule_items = $this->fetchAllPromotedForRule(
        $list_source, $language, $sort, $preset_filters, $extra, $conditions
      );
      foreach ($rule_items as $item_id => $item) {
        // Dedupe by entity ID: an entity may match multiple rules but must
        // appear only once in the promoted section.
        $entity_id = $this->extractEntityIdFromItem($item);
        if ($entity_id && !in_array($entity_id, $promoted_entity_ids, TRUE)) {
          $promoted_items[$item_id] = $item;
          $promoted_entity_ids[] = $entity_id;
        }
      }
    }
    $total_promoted = count($promoted_items);

    // Step 2: Execute the base query.
    //
    // It matches ALL items (promoted + regular) against preset filters,
    // language and bundle. We use it for two things:
    //  - its total result count, which drives the pager;
    //  - its cache tags, accumulated during execute() by QuerySubscriber
    //    (facets, preset filters, index) — this is why we keep the EXECUTED
    //    query and return it to the caller, rather than re-creating a fresh
    //    unexecuted one that would have no facet tags.
    // Its item list is overwritten just below with our computed final set.
    $base_query = $list_source->getQuery([
      'limit' => $limit,
      'page' => $current_page,
      'language' => $language,
      'sort' => $sort,
      'preset_filters' => $preset_filters,
      'extra' => $extra,
    ]);
    $base_result = $base_query->execute();

    // Step 3: Compute the page slice from a virtual list.
    //
    // Virtual list structure:
    //   [0 ... total_promoted-1]        = promoted items in sort order
    //   [total_promoted ... total-1]    = regular items in sort order
    // For page N with limit L:
    //   - Start position: N * L
    //   - End position:   (N + 1) * L - 1
    $page_start = $current_page * $limit;
    $page_end = $page_start + $limit;

    $final_items = [];

    // How many promoted items fall within this page's range (possibly 0).
    if ($page_start < $total_promoted) {
      $promo_count = min($page_end, $total_promoted) - $page_start;
      $final_items = array_slice($promoted_items, $page_start, $promo_count, TRUE);
    }

    // Step 4: Top up with regular items if the page is not full yet.
    $regular_needed = $limit - count($final_items);
    if ($regular_needed > 0) {
      // Regular items start after promoted ones in the virtual list. When
      // promoted items end mid-page the offset is 0 (no regular has been
      // shown yet); otherwise it's the number of regulars already displayed
      // on previous pages.
      $regular_offset = max(0, $page_start - $total_promoted);
      $final_items += $this->fetchRegularItemsExcludingPromoted(
        $list_source,
        $regular_needed,
        $regular_offset,
        $language,
        $sort,
        $preset_filters,
        $extra,
        array_keys($promoted_items)
      );
    }

    // Swap the base query's items for our ordered promoted+regular set.
    // The result's total count and cache tags stay intact.
    $base_result->setResultItems($final_items);

    return [
      'result' => $base_result,
      'query' => $base_query,
      'promoted_entity_ids' => $promoted_entity_ids,
    ];
  }

  /**
   * Fetches every item matching one promotion rule's conditions.
   *
   * Auto-paginates based on the result's reported total count so no item
   * is silently dropped regardless of how many match the rule. This is a
   * correctness requirement: the caller needs every promoted item ID to
   * exclude them from the regular query; truncating would leak promoted
   * items into the regular section and desynchronise pagination.
   *
   * The per-iteration batch size below is an internal optimisation — it
   * controls queries-per-call vs. memory-per-response, not the feature's
   * capacity. Larger = fewer Solr round-trips; smaller = smaller result
   * objects in PHP memory. The feature works correctly for any batch
   * size greater than zero.
   */
  protected function fetchAllPromotedForRule(ListSourceInterface $list_source, $language, array $sort, array $preset_filters, array $extra, array $conditions): array {
    // Typical promotion rules match a handful of items, so a moderate
    // batch fits the common case in a single round-trip while keeping
    // per-response memory bounded on outlier deployments.
    $batch_size = 100;
    $items = [];
    $page = 0;
    $total = NULL;

    do {
      // Build a query matching this rule's conditions (AND logic) plus the
      // base list constraints (preset filters, language, bundle, sort).
      $query = $list_source->getQuery([
        'limit' => $batch_size,
        'page' => $page,
        'language' => $language,
        'sort' => $sort,
        'preset_filters' => $preset_filters,
        'extra' => $extra,
      ]);
      foreach ($conditions as $condition) {
        if (empty($condition['field'])) {
          continue;
        }
        $value = $condition['value'] ?? '';
        // Special value "now" lets rules target e.g. "upcoming" items via
        // a date comparison resolved at query time.
        if (is_string($value) && strtolower($value) === 'now') {
          $value = date('Y-m-d\TH:i:s');
        }
        $query->addCondition($condition['field'], $value, $condition['operator'] ?? '=');
      }
      $result = $query->execute();
      $page_items = $result->getResultItems();
      if (empty($page_items)) {
        break;
      }
      $items += $page_items;
      // Pick up the authoritative total on the first round-trip; it drives
      // the loop termination without relying on a hard-coded cap.
      if ($total === NULL) {
        $total = $result->getResultCount();
      }
      $page++;
    } while (count($items) < $total);

    return $items;
  }

  /**
   * Fetches regular items excluding promoted ones.
   *
   * Uses a Search API `NOT IN` condition on `search_api_id` so Solr
   * handles the exclusion natively. This avoids the earlier offset-based
   * PHP iteration, which relied on Solr returning promoted and regular
   * items in identical order across separate queries — an assumption that
   * breaks when the sort field has ties or missing values.
   *
   * If `NOT IN` on `search_api_id` is unsupported by the backend the
   * exception is caught and we fall back to fetching and skipping in PHP.
   */
  protected function fetchRegularItemsExcludingPromoted(ListSourceInterface $list_source, int $needed, int $offset, $language, array $sort, array $preset_filters, array $extra, array $promoted_item_ids): array {
    if ($needed <= 0) {
      return [];
    }

    // Primary path: exclude promoted items via a single Search API
    // condition. Solr filters them out natively so the items returned are
    // already the "regular" subset in sort order — no PHP-side skipping
    // needed, and no dependency on two separate queries returning items
    // in identical positions.
    //
    // We cannot request an arbitrary offset directly because
    // ListSource::getQuery derives the query offset from `limit * page`.
    // Workaround: fetch (offset + needed) items from page 0 then slice
    // in PHP. For typical pages this stays small (< 50).
    $query = $list_source->getQuery([
      'limit' => $offset + $needed,
      'page' => 0,
      'language' => $language,
      'sort' => $sort,
      'preset_filters' => $preset_filters,
      'extra' => $extra,
    ]);
    if (!empty($promoted_item_ids)) {
      try {
        // `search_api_id` is the internal item identifier
        // (e.g. "entity:node/123:en") and is always available for filtering
        // on Solr-backed indexes.
        $query->addCondition('search_api_id', $promoted_item_ids, 'NOT IN');
        $items = $query->execute()->getResultItems();
        return array_slice($items, $offset, $needed, TRUE);
      }
      catch (\Exception $e) {
        // The backend may not support NOT IN on search_api_id (e.g.
        // database backend). Fall through to the PHP-skip strategy below.
      }
    }
    else {
      // No promoted items: a plain paginated query is enough.
      return array_slice($query->execute()->getResultItems(), $offset, $needed, TRUE);
    }

    // Fallback path: fetch enough items to cover offset + needed + every
    // promoted item we might encounter, then skip them manually. This is
    // the tight worst case (all promoted items in the first batch). If it
    // turns out to be insufficient — e.g. the total result is spread
    // across several Solr pages — the while loop pages through naturally
    // until we have enough, the index is exhausted, or the safety cap
    // kicks in.
    $result_items = [];
    $seen_regular = 0;
    $page = 0;
    $fetch_limit = $offset + $needed + count($promoted_item_ids);
    while (count($result_items) < $needed) {
      $page_query = $list_source->getQuery([
        'limit' => $fetch_limit,
        'page' => $page,
        'language' => $language,
        'sort' => $sort,
        'preset_filters' => $preset_filters,
        'extra' => $extra,
      ]);
      $page_items = $page_query->execute()->getResultItems();
      if (empty($page_items)) {
        // No more items in the index.
        break;
      }
      foreach ($page_items as $item_id => $item) {
        // Skip promoted items — they're shown in the promoted section.
        if (in_array($item_id, $promoted_item_ids, TRUE)) {
          continue;
        }
        // Skip regular items that belong to earlier pages.
        if ($seen_regular < $offset) {
          $seen_regular++;
          continue;
        }
        $result_items[$item_id] = $item;
        if (count($result_items) >= $needed) {
          break 2;
        }
      }
      $page++;
      // Safety cap against unbounded loops on degenerate backends.
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
