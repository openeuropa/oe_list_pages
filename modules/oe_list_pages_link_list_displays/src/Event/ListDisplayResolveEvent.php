<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_link_list_displays\Event;

use Drupal\oe_list_pages\ListPageConfiguration;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event fired to resolve the display plugin used to render a list page.
 *
 * Subscribers can change the display plugin and its configuration that will
 * be used to render the list.
 */
class ListDisplayResolveEvent extends Event {

  /**
   * The event name.
   */
  const NAME = 'oe_list_pages_link_list_displays.display_resolve';

  /**
   * The list page configuration.
   *
   * @var \Drupal\oe_list_pages\ListPageConfiguration
   */
  protected $configuration;

  /**
   * The display configuration.
   *
   * Contains the "plugin" and "plugin_configuration" keys.
   *
   * @var array
   */
  protected $displayConfiguration;

  /**
   * Constructs a new ListDisplayResolveEvent.
   *
   * @param \Drupal\oe_list_pages\ListPageConfiguration $configuration
   *   The list page configuration.
   * @param array $display_configuration
   *   The display configuration.
   */
  public function __construct(ListPageConfiguration $configuration, array $display_configuration) {
    $this->configuration = $configuration;
    $this->displayConfiguration = $display_configuration;
  }

  /**
   * Returns the list page configuration.
   *
   * @return \Drupal\oe_list_pages\ListPageConfiguration
   *   The list page configuration.
   */
  public function getConfiguration(): ListPageConfiguration {
    return $this->configuration;
  }

  /**
   * Returns the display configuration.
   *
   * @return array
   *   The display configuration.
   */
  public function getDisplayConfiguration(): array {
    return $this->displayConfiguration;
  }

  /**
   * Sets the display configuration.
   *
   * @param array $display_configuration
   *   The display configuration.
   */
  public function setDisplayConfiguration(array $display_configuration): void {
    $this->displayConfiguration = $display_configuration;
  }

}
