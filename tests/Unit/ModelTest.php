<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Model;

class ModelTest extends TestCase
{
    private string $tmpDir;
    private ConcreteModelStub $model;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/bankapp_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->model = new ConcreteModelStub($this->tmpDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*'));
        rmdir($this->tmpDir);
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->model->find(999));
    }

    public function testFindReturnsRecord(): void
    {
        $id = $this->model->create(['name' => 'Test Item', 'status' => 'active']);
        $item = $this->model->find($id);

        $this->assertNotNull($item);
        $this->assertSame('Test Item', $item['name']);
        $this->assertSame('active', $item['status']);
    }

    public function testFindAllReturnsEmptyArrayWhenNoRecords(): void
    {
        $this->assertSame([], $this->model->findAll());
    }

    public function testFindAllReturnsAllRecords(): void
    {
        $this->model->create(['name' => 'A', 'status' => 'active']);
        $this->model->create(['name' => 'B', 'status' => 'active']);
        $this->model->create(['name' => 'C', 'status' => 'inactive']);

        $all = $this->model->findAll();
        $this->assertCount(3, $all);
    }

    public function testFindAllOrdersCorrectly(): void
    {
        $this->model->create(['name' => 'B', 'status' => 'active']);
        $this->model->create(['name' => 'A', 'status' => 'active']);

        $asc = $this->model->findAll('name', 'ASC');
        $this->assertSame('A', $asc[0]['name']);
        $this->assertSame('B', $asc[1]['name']);

        $desc = $this->model->findAll('name', 'DESC');
        $this->assertSame('B', $desc[0]['name']);
        $this->assertSame('A', $desc[1]['name']);
    }

    public function testFindAllSanitizesDirection(): void
    {
        $this->model->create(['name' => 'A', 'status' => 'active']);
        $result = $this->model->findAll('name', 'INVALID');
        $this->assertCount(1, $result);
    }

    public function testFindByReturnsMatchingRecords(): void
    {
        $this->model->create(['name' => 'A', 'status' => 'active']);
        $this->model->create(['name' => 'B', 'status' => 'inactive']);
        $this->model->create(['name' => 'C', 'status' => 'active']);

        $active = $this->model->findBy(['status' => 'active']);
        $this->assertCount(2, $active);
    }

    public function testFindByReturnsEmptyWhenNoMatch(): void
    {
        $this->model->create(['name' => 'A', 'status' => 'active']);
        $result = $this->model->findBy(['status' => 'deleted']);
        $this->assertSame([], $result);
    }

    public function testFindOneByReturnsFirstMatch(): void
    {
        $this->model->create(['name' => 'Alice', 'status' => 'active']);
        $this->model->create(['name' => 'Bob', 'status' => 'active']);

        $result = $this->model->findOneBy(['name' => 'Alice']);
        $this->assertNotNull($result);
        $this->assertSame('Alice', $result['name']);
    }

    public function testFindOneByReturnsNullWhenNoMatch(): void
    {
        $result = $this->model->findOneBy(['name' => 'Nobody']);
        $this->assertNull($result);
    }

    public function testCreateReturnsId(): void
    {
        $id = $this->model->create(['name' => 'New', 'status' => 'active']);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateInsertsRecord(): void
    {
        $id = $this->model->create(['name' => 'Created', 'status' => 'pending']);
        $item = $this->model->find($id);

        $this->assertSame('Created', $item['name']);
        $this->assertSame('pending', $item['status']);
    }

    public function testCreateAutoIncrements(): void
    {
        $id1 = $this->model->create(['name' => 'First', 'status' => 'active']);
        $id2 = $this->model->create(['name' => 'Second', 'status' => 'active']);
        $this->assertSame($id1 + 1, $id2);
    }

    public function testUpdateModifiesRecord(): void
    {
        $id = $this->model->create(['name' => 'Original', 'status' => 'active']);
        $result = $this->model->update($id, ['name' => 'Updated']);

        $this->assertTrue($result);
        $item = $this->model->find($id);
        $this->assertSame('Updated', $item['name']);
        $this->assertSame('active', $item['status']);
    }

    public function testUpdateMultipleFields(): void
    {
        $id = $this->model->create(['name' => 'Original', 'status' => 'active']);
        $this->model->update($id, ['name' => 'New Name', 'status' => 'inactive']);

        $item = $this->model->find($id);
        $this->assertSame('New Name', $item['name']);
        $this->assertSame('inactive', $item['status']);
    }

    public function testDeleteRemovesRecord(): void
    {
        $id = $this->model->create(['name' => 'To Delete', 'status' => 'active']);
        $result = $this->model->delete($id);

        $this->assertTrue($result);
        $this->assertNull($this->model->find($id));
    }

    public function testDeleteNonExistentReturnsFalse(): void
    {
        $result = $this->model->delete(999);
        $this->assertFalse($result);
    }

    public function testCountAll(): void
    {
        $this->assertSame(0, $this->model->count());

        $this->model->create(['name' => 'A', 'status' => 'active']);
        $this->model->create(['name' => 'B', 'status' => 'active']);
        $this->assertSame(2, $this->model->count());
    }

    public function testCountWithCriteria(): void
    {
        $this->model->create(['name' => 'A', 'status' => 'active']);
        $this->model->create(['name' => 'B', 'status' => 'inactive']);
        $this->model->create(['name' => 'C', 'status' => 'active']);

        $this->assertSame(2, $this->model->count(['status' => 'active']));
        $this->assertSame(1, $this->model->count(['status' => 'inactive']));
        $this->assertSame(0, $this->model->count(['status' => 'deleted']));
    }

    public function testCreatedAtAndUpdatedAtAreSet(): void
    {
        $id = $this->model->create(['name' => 'Timestamped']);
        $item = $this->model->find($id);

        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('updated_at', $item);
    }

    public function testDataPersistedToJsonFile(): void
    {
        $this->model->create(['name' => 'Persistent']);
        $filePath = $this->tmpDir . '/items.json';
        $this->assertFileExists($filePath);

        $data = json_decode(file_get_contents($filePath), true);
        $this->assertCount(1, $data);
        $this->assertSame('Persistent', $data[0]['name']);
    }
}

class ConcreteModelStub extends Model
{
    protected string $file = 'items.json';

    public function __construct(string $dataDir)
    {
        parent::__construct($dataDir);
    }
}
