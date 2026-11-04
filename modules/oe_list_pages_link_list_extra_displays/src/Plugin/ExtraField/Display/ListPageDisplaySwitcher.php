<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_link_list_extra_displays\Plugin\ExtraField\Display;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages_link_list_extra_displays\DisplaySwitcherBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extra field displaying the tabs to switch between the list page displays.
 *
 * @ExtraFieldDisplay(
 *   id = "oe_list_page_display_switcher",
 *   label = @Translation("List page display switcher"),
 *   deriver = "Drupal\oe_list_pages\Plugin\ExtraField\Derivative\ListPageDeriver",
 * )
 */
class ListPageDisplaySwitcher extends ExtraFieldDisplayFormattedBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The display switcher builder.
   *
   * @var \Drupal\oe_list_pages_link_list_extra_displays\DisplaySwitcherBuilder
   */
  protected $displaySwitcherBuilder;

  /**
   * Constructs a new ListPageDisplaySwitcher.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\oe_list_pages_link_list_extra_displays\DisplaySwitcherBuilder $displaySwitcherBuilder
   *   The display switcher builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DisplaySwitcherBuilder $displaySwitcherBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->displaySwitcherBuilder = $displaySwitcherBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('oe_list_pages_link_list_extra_displays.display_switcher_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('List page display switcher');
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {
    $build = $this->displaySwitcherBuilder->build(ListPageConfiguration::fromEntity($entity));
    $cache = CacheableMetadata::createFromRenderArray($build);
    $cache->addCacheableDependency($entity);
    $cache->applyTo($build);

    // Without extra displays, the build only contains the cache metadata.
    return isset($build['#type']) ? [$build] : $build;
  }

}
