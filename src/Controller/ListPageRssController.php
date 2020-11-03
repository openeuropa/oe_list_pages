<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\emr\Entity\EntityMetaInterface;
use Drupal\node\NodeInterface;
use Drupal\oe_list_pages\ListBuilderInterface;
use Drupal\oe_list_pages\ListPageRssAlterEvent;
use Drupal\oe_list_pages\ListPageEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\oe_list_pages\ListBuilderInterface $list_builder
   *   The list builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The event dispatcher.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, ListBuilderInterface $list_builder, RendererInterface $renderer, ThemeManagerInterface $theme_manager) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
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
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('oe_list_pages.builder'),
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
   * @return string
   *   The RSS feed response.
   */
  public function build(NodeInterface $node): Response {
    // Build the render array for the RSS feed.
    $cache_metadata = CacheableMetadata::createFromObject($node);
    $default_title = $this->getChannelTitle($node, $cache_metadata);
    $default_link = $node->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE);
    $default_image = [
      'url' => $this->getDefaultImageUrl(),
      'title' => $default_title,
      'link' => $default_link->getGeneratedUrl(),
    ];
    $build = [
      '#theme' => 'oe_list_pages_rss',
      '#title' => $default_title,
      '#link' => $default_link->getGeneratedUrl(),
      '#description' => $this->getDefaultDescription($node, $cache_metadata),
      '#language' => $node->language()->getId(),
      '#copyright' => $this->getCopyright(),
      '#image' => $default_image,
      '#channel_elements' => [],
      '#items' => $this->getItemList($node),
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
   *
   * @return array
   *   Array of items to be rendered.
   */
  protected function getItemList(NodeInterface $node): array {
    return [];
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
    $entity_meta_list = $node->get('emr_entity_metas')->getValue();
    foreach ($entity_meta_list as $entity_meta) {
      $entity_meta = reset($entity_meta);
      if ($entity_meta instanceof EntityMetaInterface && $entity_meta->bundle() === 'oe_list_page') {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden($this->t('Node type does not have List Page meta configured.'));
  }

  /**
   * Get the RSS channel title.
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
   * Gets the default image url.
   *
   * @return string
   *   The default image url.
   */
  protected function getDefaultImageUrl(): string {
    // Get the favicon location.
    $theme = $this->themeManager->getActiveTheme();
    if (file_exists($favicon = $theme->getPath() . '/favicon.ico')) {
      return file_create_url($favicon);
    }
    else {
      return file_create_url('core/misc/favicon.ico');
    }
  }

  /**
   * Gets the default description.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being rendered.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The current cache metadata.
   *
   * @return string
   *   The default description.
   */
  protected function getDefaultDescription(NodeInterface $node, CacheableMetadata &$cache_metadata): string {
    $selected_filter_information = $this->listBuilder->buildSelectedFilters($node);
    $filter_cache = CacheableMetadata::createFromRenderArray($selected_filter_information);
    $cache_metadata = $cache_metadata->merge($filter_cache);
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
      $selected_filters[] = $filter_information['#label'] . ': ' . implode(' - ', $filter_values);
    }
    return implode(', ', $selected_filters);
  }

  /**
   * Gets the default copyright value.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The default copyright value.
   */
  protected function getCopyright(): TranslatableMarkup {
    return $this->t('© European Union, @startdate-@enddate', ['@startdate' => '1995', '@enddate' => date('Y')]);
  }

}
