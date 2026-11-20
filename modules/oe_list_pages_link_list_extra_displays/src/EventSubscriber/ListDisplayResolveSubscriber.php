<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_link_list_extra_displays\EventSubscriber;

use Drupal\oe_list_pages_link_list_displays\Event\ListDisplayResolveEvent;
use Drupal\oe_list_pages_link_list_extra_displays\ExtraDisplaysHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Switches the list page display to the extra display requested in the URL.
 */
class ListDisplayResolveSubscriber implements EventSubscriberInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new ListDisplayResolveSubscriber.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(RequestStack $requestStack) {
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ListDisplayResolveEvent::NAME => 'onDisplayResolve',
    ];
  }

  /**
   * Sets the extra display requested in the URL, if any.
   *
   * @param \Drupal\oe_list_pages_link_list_displays\Event\ListDisplayResolveEvent $event
   *   The event.
   */
  public function onDisplayResolve(ListDisplayResolveEvent $event): void {
    $display = ExtraDisplaysHelper::getActiveExtraDisplay($event->getConfiguration()->getExtra(), $this->requestStack->getCurrentRequest());
    if ($display) {
      $event->setDisplayConfiguration($display);
    }
  }

}
