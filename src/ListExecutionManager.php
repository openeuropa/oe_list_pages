<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Language\LanguageManagerInterface;
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
   * The already executed lists, keyed by entity UUID.
   *
   * @var \Drupal\oe_list_pages\ListExecutionResults[]
   */
  protected $executedLists = [];

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
   */
  public function __construct(ListSourceFactoryInterface $listSourceFactory, EntityTypeManager $entityTypeManager, RequestStack $requestStack, LanguageManagerInterface $languageManager) {
    $this->listSourceFactory = $listSourceFactory;
    $this->entityTypeManager = $entityTypeManager;
    $this->requestStack = $requestStack;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public function executeList($entity): ?ListExecutionResults {
    if (!empty($this->executedLists[$entity->uuid()])) {
      return $this->executedLists[$entity->uuid()];
    }

    /** @var \Drupal\emr\Entity\EntityMetaInterface $entity_meta */
    $entity_meta = $entity->get('emr_entity_metas')->getEntityMeta('oe_list_page');
    /** @var \Drupal\oe_list_pages\ListPageWrapper $wrapper */
    $wrapper = $entity_meta->getWrapper();

    // The number of items to show on the page.
    $limit = $wrapper->getConfiguration()['items_per_page'] ?? 10;

    $list_source = $this->listSourceFactory->get($wrapper->getSourceEntityType(), $wrapper->getSourceEntityBundle());
    if (!$list_source) {
      $this->executedLists[$entity->uuid()] = NULL;
      return NULL;
    }

    // Determine the query options and execute it.
    $bundle_entity_type = $this->entityTypeManager->getDefinition($wrapper->getSourceEntityType())->getBundleEntityType();
    $storage = $this->entityTypeManager->getStorage($bundle_entity_type);
    $bundle = $storage->load($wrapper->getSourceEntityBundle());
    $sort = $bundle->getThirdPartySetting('oe_list_pages', 'default_sort', []);
    $current_page = (int) $this->requestStack->getCurrentRequest()->get('page', 0);
    $sort = $sort ? [$sort['name'] => $sort['direction']] : [];
    $language = $this->languageManager->getCurrentLanguage()->getId();
    $options = [
      'limit' => $limit,
      'page' => $current_page,
      'language' => $language,
      'sort' => $sort,
    ];
    $query = $list_source->getQuery($options);
    $result = $query->execute();
    $list_execution = new ListExecutionResults($query, $result, $list_source, $wrapper);

    $this->executedLists[$entity->uuid()] = $list_execution;

    return $list_execution;
  }

}
