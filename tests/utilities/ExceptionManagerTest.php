<?php

namespace App\Tests\Utilities;

use App\Utilities\DatabaseManager;
use App\Utilities\ExceptionManager;
use PHPUnit\Framework\TestCase;

class ExceptionManagerTest extends TestCase
{
    protected function setUp(): void
    {
        DatabaseManager::reset();
        $pdo = DatabaseManager::connect(['driver' => 'sqlite', 'database' => ':memory:']);
        $pdo->exec(<<<SQL
            CREATE TABLE logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                level TEXT NOT NULL,
                source TEXT NOT NULL,
                message TEXT NOT NULL,
                context TEXT,
                created_at TEXT
            )
            SQL);
    }

    public function test_debug_mode_includes_the_exception_message(): void
    {
        $response = ExceptionManager::handle(new \RuntimeException('boom'), true);

        $this->assertSame(500, $response->status);
        $this->assertStringContainsString('boom', $response->body);
    }

    public function test_production_mode_hides_the_exception_message(): void
    {
        $response = ExceptionManager::handle(new \RuntimeException('sensitive detail'), false);

        $this->assertSame(500, $response->status);
        $this->assertStringNotContainsString('sensitive detail', $response->body);
    }

    public function test_handling_an_exception_logs_it(): void
    {
        ExceptionManager::handle(new \RuntimeException('logged error'), false);

        $rows = DatabaseManager::select('SELECT * FROM logs');

        $this->assertCount(1, $rows);
        $this->assertSame('error', $rows[0]['level']);
        $this->assertSame('logged error', $rows[0]['message']);
        $this->assertSame('RuntimeException', $rows[0]['source']);
    }
}
