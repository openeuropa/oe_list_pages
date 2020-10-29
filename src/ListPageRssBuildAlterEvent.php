<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event thrown in order to alter the RSS build before its rendered.
 */
class ListPageRssBuildAlterEvent extends Event {

  /**
   * The render array for the list page RSS list.
   *
   * @var array
   */
  protected $build;

  /**
   * The list page being rendered.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $listPage;

  /**
   * Constructs the object.
   *
   * @param array $build
   *   The render array for the list page RSS list.
   * @param \Drupal\node\NodeInterface $list_page
   *   The list page being rendered.
   */
  public function __construct(array $build, NodeInterface $list_page) {
    $this->build = $build;
    $this->listPage = $list_page;
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
  public function getListPage(): NodeInterface {
    return $this->listPage;
  }

}
