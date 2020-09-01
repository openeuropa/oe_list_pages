<?php

namespace Drupal\Tests\oe_list_pages\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetSource\FacetSourcePluginManager;
use Drupal\facets\UrlProcessor\UrlProcessorInterface;
use Drupal\facets\Widget\WidgetPluginManager;
use Drupal\oe_list_pages\Plugin\facets\processor\DateProcessorHandler;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for date processor.
 *
 * @group facets
 */
class ListPagesDateProcessorHandlerTest extends UnitTestCase {

  /**
   * Tests preQuery method.
   */
  public function testPreQuery() {
    $facet = new Facet(['id' => 'facets_facet'], 'facets_facet');
    $this->createContainer();
    $processor = new DateProcessorHandler(['facet' => $facet], 'date_processor_handler', []);

    $processor->getProcessor()->expects($this->exactly(2))
      ->method('getActiveFilters')
      ->willReturnOnConsecutiveCalls(['facets_facet' => ['gt|2020-08-16']], ['facets_facet' => ['bt|2020-08-16|2020-08-20']]);

    $facet->setWidget('oe_list_pages_date', ['type' => 'oe_list_pages_date']);
    $processor->preQuery($facet);
    $this->assertEquals(['gt', '2020-08-16'], $facet->getActiveItems());

    // Structure the data.
    $structured = DateProcessorHandler::structureActiveItems($facet);
    $this->assertEquals([
      'operator' => 'gt',
      'first' => '2020-08-16',
    ], $structured);

    $processor->preQuery($facet);
    $this->assertEquals(['bt', '2020-08-16', '2020-08-20'], $facet->getActiveItems());

    // Structure the data.
    $structured = DateProcessorHandler::structureActiveItems($facet);
    $this->assertEquals([
      'operator' => 'bt',
      'first' => '2020-08-16',
      'second' => '2020-08-20',
    ], $structured);
  }

  /**
   * Sets up a container.
   */
  protected function createContainer() {
    $url_processor = $this->getMockBuilder(UrlProcessorInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $manager = $this->getMockBuilder(FacetSourcePluginManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $manager->expects($this->exactly(1))
      ->method('createInstance')
      ->willReturn($url_processor);

    $storage = $this->createMock(EntityStorageInterface::class);
    $em = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $em->expects($this->exactly(1))
      ->method('getStorage')
      ->willReturn($storage);
    $widget_manager = $this->prophesize(WidgetPluginManager::class);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $em);
    $container->set('plugin.manager.facets.url_processor', $manager);
    $container->set('plugin.manager.facets.widget', $widget_manager->reveal());
    \Drupal::setContainer($container);
  }

}
