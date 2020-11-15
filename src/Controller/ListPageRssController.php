<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Controller;

use DateTimeInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\emr\Entity\EntityMetaInterface;
use Drupal\node\NodeInterface;
use Drupal\oe_list_pages\ListBuilderInterface;
use Drupal\oe_list_pages\ListExecutionManagerInterface;
use Drupal\oe_list_pages\ListPageRssAlterEvent;
use Drupal\oe_list_pages\ListPageEvents;
use Drupal\oe_list_pages\ListPageRssItemAlterEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the List pages RSS feed routes.
 */
class ListPageRssController extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The list execution manager.
   *
   * @var \Drupal\oe_list_pages\ListExecutionManagerInterface
   */
  protected $listExecutionManager;

  /**
   * The list builder.
   *
   * @var \Drupal\oe_list_pages\ListBuilderInterface
   */
  protected $listBuilder;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * ListPageRssController constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\oe_list_pages\ListBuilderInterface $list_builder
   *   The list builder.
   * @param \Drupal\oe_list_pages\ListExecutionManagerInterface $list_execution_manager
   *   The list execution manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The event dispatcher.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, DateFormatterInterface $date_formatter, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, ListBuilderInterface $list_builder, ListExecutionManagerInterface $list_execution_manager, RendererInterface $renderer, ThemeManagerInterface $theme_manager) {
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->listExecutionManager = $list_execution_manager;
    $this->listBuilder = $list_builder;
    $this->renderer = $renderer;
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ListPageRssController {
    return new static(
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('oe_list_pages.builder'),
      $container->get('oe_list_pages.execution_manager'),
      $container->get('renderer'),
      $container->get('theme.manager')
    );
  }

  /**
   * Render the RSS feed of a list page result.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The list page node.
   *
   * @return \Drupal\Core\Cache\CacheableResponse
   *   The RSS feed response.
   */
  public function build(NodeInterface $node): CacheableResponse {
    // Build the render array for the RSS feed.
    $cache_metadata = CacheableMetadata::createFromObject($node);
    $default_title = $this->getChannelTitle($node, $cache_metadata);
    $default_link = $node->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE);
    $build = [
      '#theme' => 'oe_list_pages_rss',
      '#title' => $default_title,
      '#link' => $default_link->getGeneratedUrl(),
      '#description' => $this->getChannelDescription($node, $cache_metadata),
      '#language' => $node->language()->getId(),
      '#copyright' => $this->getChannelCopyright(),
      '#image' => $this->getChannelImage($cache_metadata),
      '#channel_elements' => [],
      '#items' => $this->getItemList($node, $cache_metadata),
    ];
    $cache_metadata->addCacheableDependency($default_link);
    $cache_metadata->applyTo($build);

    // Dispatch event to allow modules to alter the build before being rendered.
    $event = new ListPageRssAlterEvent($build, $node);
    $this->eventDispatcher->dispatch(ListPageEvents::ALTER_RSS_BUILD, $event);
    $build = $event->getBuild();

    // Create the response and add the xml type header.
    $response = new CacheableResponse('', 200);
    $response->headers->set('Content-Type', 'application/rss+xml; charset=utf-8');

    // Render the list and add it to the response.
    $output = (string) $this->renderer->renderRoot($build);
    if (empty($output)) {
      throw new NotFoundHttpException();
    }
    $response->setContent($output);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

  /**
   * Returns a list of items to be rendered.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node containing the list.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The current cache metadata.
   *
   * @return array
   *   Array of items to be rendered.
   */
  protected function getItemList(NodeInterface $node, CacheableMetadata $cache_metadata): array {
    $execution_result = $this->listExecutionManager->executeList($node);
    $query = $execution_result->getQuery();
    $results = $execution_result->getResults();
    $cache_metadata->addCacheableDependency($query);
    $cache_metadata->addCacheableDependency($results);
    $cache_metadata->addCacheTags(['search_api_list:' . $query->getIndex()->id()]);
    $result_items = [];
    foreach ($results->getResultItems() as $item) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $item->getOriginalObject()->getEntity();
      $cache_metadata->addCacheableDependency($entity);
      $creation_date = $entity->get('changed')->value;
      $result_item = [
        '#theme' => 'oe_list_pages_rss_item',
        '#title' => $entity->label(),
        '#link' => $entity->toUrl('canonical', ['absolute' => TRUE]),
        '#guid' => $entity->toUrl('canonical', ['absolute' => TRUE]),
        '#description' => '',
        '#item_elements' => [
          [
            '#type' => 'html_tag',
            '#tag' => 'pubDate',
            '#value' => $this->dateFormatter->format($creation_date, 'custom', DateTimeInterface::RFC822),
          ],
        ],
      ];

      // Dispatch event to allow to alter the item build before being rendered.
      $event = new ListPageRssItemAlterEvent($result_item, $entity);
      $this->eventDispatcher->dispatch(ListPageEvents::ALTER_RSS_ITEM_BUILD, $event);

      $result_items[] = $event->getBuild();
    }
    return $result_items;
  }

  /**
   * Asserts whether we can access the RSS route for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being checked.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(NodeInterface $node): AccessResultInterface {
    $entity_meta = $node->get('emr_entity_metas')->getEntityMeta('oe_list_page');
    if ($entity_meta instanceof EntityMetaInterface && !$entity_meta->isNew()) {
      return AccessResult::allowed()->addCacheableDependency($node);
    }

    return AccessResult::forbidden($this->t('Node type does not have List Page meta configured.'))->addCacheableDependency($node);
  }

  /**
   * Get the channel title.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being rendered.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The current cache metadata.
   *
   * @return string
   *   The channel title.
   */
  protected function getChannelTitle(NodeInterface $node, CacheableMetadata $cache_metadata): string {
    $site_config = $this->configFactory->get('system.site');
    $cache_metadata->addCacheableDependency($site_config);
    $site_name = $site_config->get('name');
    return $site_name . ' | ' . $node->label();
  }

  /**
   * Get the channel image array.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The current cache metadata.
   *
   * @return array
   *   The image information array.
   */
  protected function getChannelImage(CacheableMetadata $cache_metadata): array {
    $title = $this->t('@title logo', ['@title' => 'European Commission']);
    // Get the logo location.
    $theme = $this->themeManager->getActiveTheme();
    $site_url = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString(TRUE);
    $cache_metadata->addCacheableDependency($site_url);

    return [
      'url' => file_create_url($theme->getLogo()),
      'title' => $title,
      'link' => $site_url->getGeneratedUrl(),
    ];
  }

  /**
   * Get the channel description.
   *
   * The default channel description shows a complete list of selected
   * filter labels and values.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being rendered.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The current cache metadata.
   *
   * @return string
   *   The default description.
   */
  protected function getChannelDescription(NodeInterface $node, CacheableMetadata $cache_metadata): string {
    $selected_filter_information = $this->listBuilder->buildSelectedFilters($node);
    $filter_cache = CacheableMetadata::createFromRenderArray($selected_filter_information);
    $cache_metadata->addCacheableDependency($filter_cache);
    // Extract the selected filter information.
    $selected_filters = [];
    foreach (Element::children($selected_filter_information) as $child) {
      $filter_information = $selected_filter_information[$child];
      $filter_values = [];
      if (!isset($filter_information['#items'])) {
        continue;
      }
      foreach ($filter_information['#items'] as $filter_value_information) {
        $filter_values[] = $filter_value_information['label'];
      }
      $selected_filters[] = $filter_information['#label'] . ': ' . implode(', ', $filter_values);
    }
    return implode(' | ', $selected_filters);
  }

  /**
   * Get the channel copyright value.
   *
   * @return \Drupal\Component\Render\FormattableMarkup
   *   The default copyright value.
   */
  protected function getChannelCopyright(): FormattableMarkup {
    return new FormattableMarkup('© @copyright_name, 1995-@enddate', ['@copyright_name' => $this->t('European Union'), '@enddate' => date('Y')]);
  }

}
