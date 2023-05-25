<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\node\NodeInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event thrown in order to alter the RSS build before its rendered.
 */
class ListPageRssAlterEvent extends Event {

  /**
   * The render array for the list page RSS list.
   *
   * @var array
   */
  protected $build;

  /**
   * The node being rendered.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Constructs the object.
   *
   * @param array $build
   *   The render array for the list page RSS list.
   * @param \Drupal\node\NodeInterface $node
   *   The node being rendered.
   */
  public function __construct(array $build, NodeInterface $node) {
    $this->build = $build;
    $this->node = $node;
  }

  /**
   * Returns the build.
   *
   * @return array
   *   The current build.
   */
  public function getBuild(): array {
    return $this->build;
  }

  /**
   * Sets the build.
   *
   * @param array $build
   *   The modified build.
   */
  public function setBuild(array $build): void {
    $this->build = $build;
  }

  /**
   * Returns the list page.
   *
   * @return \Drupal\node\NodeInterface
   *   The list page being processed.
   */
  public function getNode(): NodeInterface {
    return $this->node;
  }

}
