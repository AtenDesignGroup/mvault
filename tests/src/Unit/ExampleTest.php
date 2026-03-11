<?php

declare(strict_types=1);

namespace Drupal\Tests\mvault\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Define an example test.
 */
class ExampleTest extends UnitTestCase {


  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
  }

  /**
   * Test that the example test works.
   */
  public function testIsValid(): void {
    /** @phpstan-ignore-next-line */
    $this->assertTrue(true);
  }
}
