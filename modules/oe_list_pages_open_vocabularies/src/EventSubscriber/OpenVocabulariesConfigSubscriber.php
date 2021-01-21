<?php

namespace Drupal\oe_list_pages_open_vocabularies\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\oe_list_pages_open_vocabularies\SearchApiConfigurator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Create search api configuration when vocabularies associations are edited.
 */
class OpenVocabulariesConfigSubscriber implements EventSubscriberInterface {

  /**
   * The search api configurator.
   *
   * @var \Drupal\oe_list_pages_open_vocabularies\SearchApiConfigurator
   */
  protected $configurator;

  /**
   * Constructs an OpenVocabulariesConfigSubscriber object.
   */
  public function __construct(SearchApiConfigurator $configurator) {
    $this->configurator = $configurator;
  }

  /**
   * Creates a new field in search index and an associated facet.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The Event to process.
   */
  public function onSave(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    if (strpos($config->getName(), 'open_vocabularies') !== 0) {
      return;
    }

    $fields = $config->getRawData()['fields'];
    foreach ($fields as $field) {
      // @TODO: Missing deleting old associations if it is needed.
      $this->configurator->updateConfig($config->getRawData()['id'], $config->getRawData()['label'], $field);
    }
  }

  /**
   * Deletes the field in search index and an associated facet.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The Event to process.
   */
  public function onDelete(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    if (strpos($config->getName(), 'open_vocabularies') !== 0) {
      return;
    }

    $fields = $config->getOriginal()['fields'];
    foreach ($fields as $field) {
      $this->configurator->removeConfig($config->getOriginal()['id'], $field);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    $events[ConfigEvents::DELETE][] = ['onDelete'];
    return $events;
  }

}
