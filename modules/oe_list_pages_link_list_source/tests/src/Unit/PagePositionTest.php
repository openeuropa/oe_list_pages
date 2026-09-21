<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages_link_list_source\Unit;

use Drupal\oe_list_pages_link_list_source\PagePosition;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the PagePosition class.
 */
class PagePositionTest extends UnitTestCase {

  /**
   * Tests the ::fromRange() method with unlimited (null) length.
   */
  public function testFromRangeLengthNull(): void {
    $this->assertEquals(
      new PagePosition(0, NULL, 0),
      PagePosition::fromRange(0, NULL),
    );
    $this->assertEquals(
      new PagePosition(0, NULL, 1),
      PagePosition::fromRange(1, NULL),
    );
    $this->assertEquals(
      new PagePosition(0, NULL, 3),
      PagePosition::fromRange(3, NULL),
    );
  }

  /**
   * Tests the ::fromRange() method with non-null length.
   */
  public function testFromRange(): void {
    $this->assertEquals(
      new PagePosition(0, 3, 0),
      PagePosition::fromRange(0, 3),
    );
    $this->assertEquals(
      new PagePosition(2, 5, 0),
      PagePosition::fromRange(10, 5),
    );
    $this->assertEquals(
      new PagePosition(0, 4, 1),
      PagePosition::fromRange(1, 3),
    );
    $this->assertEquals(
      new PagePosition(2, 5, 1),
      PagePosition::fromRange(11, 3),
    );
  }

}
