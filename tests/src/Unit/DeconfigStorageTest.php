<?php

namespace Drupal\Tests\deconfig\Unit;

use Drupal\Core\Config\StorageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\deconfig\DeconfigStorage;
use Drupal\deconfig\Exception\FoundHiddenConfigurationError;

/**
 * Test the Deconfig class.
 */
class DeconfigStorageTest extends UnitTestCase {

  /**
   * Test reading of hidden configuration.
   *
   * @dataProvider readProvider
   */
  public function testReading($storageData, $activeData, $expected) {
    $storage = $this->prophesize(StorageInterface::class);
    $active = $this->prophesize(StorageInterface::class);

    $storage->read('test.key')->willReturn($storageData);

    $active->read('test.key')->willReturn($activeData);

    $deconfig = new DeconfigStorage($storage->reveal(), $active->reveal());
    $this->assertEquals($expected, $deconfig->read('test.key'));
  }

  /**
   * Data provider for testReading.
   */
  public function readProvider() {
    return [
      [
        ['test.config'],
        [],
        ['test.config'],
      ],
      [
        ['_deconfig' => 'Hidden'],
        ['_deconfig' => 'Hidden', 'hidden' => TRUE],
        ['_deconfig' => 'Hidden', 'hidden' => TRUE],
      ],
      [
        // Returned from storage.
        [
          '_deconfig' => ['sub' => ['another' => ['hidden' => 'this is hidden']]],
          'nothidden' => 'not hidden',
        ],
        // Returned from active storage.
        [
          '_deconfig' => ['sub' => ['another' => ['hidden' => 'this is hidden']]],
          'nothidden' => 'should not happen',
          'sub' => [
            'another' => [
              'hidden' => TRUE,
            ],
          ],
        ],
        // Expected result.
        [
          '_deconfig' => ['sub' => ['another' => ['hidden' => 'this is hidden']]],
          'nothidden' => 'not hidden',
          'sub' => [
            'another' => [
              'hidden' => TRUE,
            ],
          ],
        ],
      ],
      // Hiding sub-keys of non-array items shouldn't munge the parent key.
      [
        [
          '_deconfig' => ['sub' => ['key' => 'hidden']],
          'sub' => 'banana',
        ],
        [
          '_deconfig' => ['sub' => ['key' => 'hidden']],
          'sub' => 'banana',
        ],
        [
          '_deconfig' => ['sub' => ['key' => 'hidden']],
          'sub' => 'banana',
        ],
      ],
    ];
  }

  /**
   * Test writing of configuration.
   *
   * @dataProvider writeProvider
   */
  public function testWriting($writtenData, $expected) {
    $storage = $this->prophesize(StorageInterface::class);
    $active = $this->prophesize(StorageInterface::class);

    $storage->write('test.key', $expected)->willReturn(TRUE)->shouldBeCalled();

    $deconfig = new DeconfigStorage($storage->reveal(), $active->reveal());
    $this->assertTrue($deconfig->write('test.key', $writtenData));
  }

  /**
   * Data provider for testWriting.
   */
  public function writeProvider() {
    return [
      [
        ['simple data' => 'beta'],
        ['simple data' => 'beta'],
      ],
      [
        [
          '_deconfig' => ['key' => ['something' => 'hidden']],
          'key' => [
            'something' => 'should be hidden',
            'other' => 'not hidden',
          ],
          'and' => 'not hidden',
          'added' => 'here',
        ],
        [
          '_deconfig' => ['key' => ['something' => 'hidden']],
          'key' => [
            'other' => 'not hidden',
          ],
          'and' => 'not hidden',
          'added' => 'here',
        ],
      ],
    ];
  }

  /**
   * Test that hidden config entries in storage throws error.
   *
   * @dataProvider errorProvider
   */
  public function testErrorThrowing($storageData, $shouldThrow) {
    $storage = $this->prophesize(StorageInterface::class);
    $active = $this->prophesize(StorageInterface::class);

    $storage->read('test.key')->willReturn($storageData);

    $deconfig = new DeconfigStorage($storage->reveal(), $active->reveal());

    if ($shouldThrow) {
      $this->expectException(FoundHiddenConfigurationError::class);
      $deconfig->read('test.key');
    }
    else {
      $this->assertEquals($storageData, $deconfig->read('test.key'));
    }
  }

  /**
   * Data provider for testErrorThrowing.
   */
  public function errorProvider() {
    return [
      [
        [
          '_deconfig' => 'true',
          'something' => 'lala',
        ],
        TRUE,
      ],
      [
        [
          '_deconfig' => 'true',
        ],
        FALSE,
      ],
      [
        [
          '_deconfig' => ['sub' => ['key' => 'ignore this']],
          'something' => 'lala',
          'sub' => [
            'key' => 'banana',
          ],
        ],
        TRUE,
      ],
      [
        [
          '_deconfig' => ['sub' => ['key' => 'ignore this']],
          'something' => 'lala',
        ],
        FALSE,
      ],
    ];
  }

  /**
   * Test raw reading of hidden configuration.
   *
   * @dataProvider readRawProvider
   */
  public function testRawReading($storageData, $activeData, $expected) {
    $storage = $this->prophesize(StorageInterface::class);
    $active = $this->prophesize(StorageInterface::class);

    $storage->read('test.key')->willReturn($storageData);

    $active->read('test.key')->willReturn($activeData);

    $deconfig = new DeconfigStorage($storage->reveal(), $active->reveal());
    $this->assertEquals($expected, $deconfig->readRaw('test.key'));
  }

  /**
   * Data provider for testRawReading.
   */
  public function readRawProvider() {
    return [
      [
        ['test.config'],
        [],
        ['test.config'],
      ],
      [
        ['_deconfig' => 'Hidden', 'hidden' => FALSE],
        ['_deconfig' => 'Hidden', 'hidden' => TRUE],
        ['_deconfig' => 'Hidden', 'hidden' => TRUE],
      ],
      [
        // Returned from storage. Raw reading shouldn't care that hidden items
        // are in the storage.
        [
          '_deconfig' => ['sub' => ['another' => ['hidden' => 'this is hidden']]],
          'sub' => [
            'another' => [
              'hidden' => TRUE,
            ],
          ],
          'nothidden' => 'not hidden',
        ],
        // Returned from active storage.
        [
          '_deconfig' => ['sub' => ['another' => ['hidden' => 'this is hidden']]],
          'nothidden' => 'should not happen',
          'sub' => [
            'another' => [
              'hidden' => TRUE,
            ],
          ],
        ],
        // Expected result.
        [
          '_deconfig' => ['sub' => ['another' => ['hidden' => 'this is hidden']]],
          'nothidden' => 'not hidden',
          'sub' => [
            'another' => [
              'hidden' => TRUE,
            ],
          ],
        ],
      ],
    ];
  }

}
