<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event thrown in order to alter an RSS item build before it's rendered.
 */
class ListPageRssItemAlterEvent extends Event {

  /**
   * The render array for an RSS list item.
   *
   * @var array
   */
  protected $build;

  /**
   * The entity being rendered as an RSS item.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * Constructs the object.
   *
   * @param array $build
   *   The render array for the list page RSS list.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being rendered.
   */
  public function __construct(array $build, ContentEntityInterface $entity) {
    $this->build = $build;
    $this->entity = $entity;
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
   * Returns the entity item.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity being processed.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

}
