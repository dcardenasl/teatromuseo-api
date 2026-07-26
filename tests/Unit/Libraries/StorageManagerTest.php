<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\Storage\StorageManager;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * StorageManager Tests
 */
class StorageManagerTest extends CIUnitTestCase
{
    protected StorageManager $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = new StorageManager('local');
    }

    public function testGetDriverReturnsLocalByDefault(): void
    {
        $storage = new StorageManager();

        $this->assertEquals('local', $storage->getDriverName());
    }

    public function testGetDriverReturnsLocalWhenSpecified(): void
    {
        $storage = new StorageManager('local');

        $this->assertEquals('local', $storage->getDriverName());
    }

    public function testGetDriverThrowsExceptionForUnsupportedDriver(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Storage driver [invalid] is not supported');

        new StorageManager('invalid');
    }

    public function testPutDelegatesToDriver(): void
    {
        $driver = $this->createMock(\App\Libraries\Storage\StorageDriverInterface::class);
        $driver->expects($this->once())
            ->method('store')
            ->with('test.txt', 'content')
            ->willReturn(true);

        $storage = new class ($driver) extends StorageManager {
            public function __construct($driver)
            {
                $this->driver = $driver;
                $this->driverName = 'mock';
            }
        };

        $result = $storage->put('test.txt', 'content');

        $this->assertTrue($result);
    }

    public function testGetDelegatesToDriver(): void
    {
        $driver = $this->createMock(\App\Libraries\Storage\StorageDriverInterface::class);
        $driver->expects($this->once())
            ->method('retrieve')
            ->with('test.txt')
            ->willReturn('content');

        $storage = new class ($driver) extends StorageManager {
            public function __construct($driver)
            {
                $this->driver = $driver;
                $this->driverName = 'mock';
            }
        };

        $result = $storage->get('test.txt');

        $this->assertEquals('content', $result);
    }

    public function testDeleteDelegatesToDriver(): void
    {
        $driver = $this->createMock(\App\Libraries\Storage\StorageDriverInterface::class);
        $driver->expects($this->once())
            ->method('delete')
            ->with('test.txt')
            ->willReturn(true);

        $storage = new class ($driver) extends StorageManager {
            public function __construct($driver)
            {
                $this->driver = $driver;
                $this->driverName = 'mock';
            }
        };

        $result = $storage->delete('test.txt');

        $this->assertTrue($result);
    }

    public function testExistsDelegatesToDriver(): void
    {
        $driver = $this->createMock(\App\Libraries\Storage\StorageDriverInterface::class);
        $driver->expects($this->once())
            ->method('exists')
            ->with('test.txt')
            ->willReturn(true);

        $storage = new class ($driver) extends StorageManager {
            public function __construct($driver)
            {
                $this->driver = $driver;
                $this->driverName = 'mock';
            }
        };

        $result = $storage->exists('test.txt');

        $this->assertTrue($result);
    }

    public function testUrlDelegatesToDriver(): void
    {
        $driver = $this->createMock(\App\Libraries\Storage\StorageDriverInterface::class);
        $driver->expects($this->once())
            ->method('url')
            ->with('test.txt')
            ->willReturn('http://example.com/test.txt');

        $storage = new class ($driver) extends StorageManager {
            public function __construct($driver)
            {
                $this->driver = $driver;
                $this->driverName = 'mock';
            }
        };

        $result = $storage->url('test.txt');

        $this->assertEquals('http://example.com/test.txt', $result);
    }

    public function testSizeDelegatesToDriver(): void
    {
        $driver = $this->createMock(\App\Libraries\Storage\StorageDriverInterface::class);
        $driver->expects($this->once())
            ->method('size')
            ->with('test.txt')
            ->willReturn(1024);

        $storage = new class ($driver) extends StorageManager {
            public function __construct($driver)
            {
                $this->driver = $driver;
                $this->driverName = 'mock';
            }
        };

        $result = $storage->size('test.txt');

        $this->assertEquals(1024, $result);
    }

    public function testGetDriverReturnsDriverInstance(): void
    {
        $driver = $this->storage->getDriver();

        $this->assertInstanceOf(\App\Libraries\Storage\StorageDriverInterface::class, $driver);
    }
}
