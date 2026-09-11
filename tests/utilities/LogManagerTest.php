<?php

namespace App\Tests\Utilities;

use App\Utilities\DatabaseManager;
use App\Utilities\LogManager;
use PHPUnit\Framework\TestCase;

class LogManagerTest extends TestCase
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

    public function test_error_writes_a_row_to_the_logs_table(): void
    {
        LogManager::error('TestSource', 'Something broke', ['detail' => 'value']);

        $rows = DatabaseManager::select('SELECT * FROM logs');

        $this->assertCount(1, $rows);
        $this->assertSame('error', $rows[0]['level']);
        $this->assertSame('TestSource', $rows[0]['source']);
        $this->assertSame('Something broke', $rows[0]['message']);
        $this->assertSame(['detail' => 'value'], json_decode($rows[0]['context'], true));
    }

    public function test_info_and_warning_write_their_own_level(): void
    {
        LogManager::info('Source', 'info message');
        LogManager::warning('Source', 'warning message');

        $rows = DatabaseManager::select('SELECT level FROM logs ORDER BY id');

        $this->assertSame(['info', 'warning'], array_column($rows, 'level'));
    }

    public function test_log_without_context_stores_null(): void
    {
        LogManager::info('Source', 'no context here');

        $rows = DatabaseManager::select('SELECT context FROM logs');

        $this->assertNull($rows[0]['context']);
    }

    public function test_falls_back_to_a_file_when_the_logs_table_is_unavailable(): void
    {
        DatabaseManager::reset();
        DatabaseManager::connect(['driver' => 'sqlite', 'database' => ':memory:']);
        // No logs table created this time — the insert will fail.

        $logFile = __DIR__ . '/../../storage/logs/error.log';
        clearstatcache(true, $logFile);
        $before = is_file($logFile) ? filesize($logFile) : 0;

        LogManager::error('Source', 'db is unavailable');

        clearstatcache(true, $logFile);
        $this->assertGreaterThan($before, filesize($logFile));

        $contents = file_get_contents($logFile);
        $this->assertStringContainsString('db is unavailable', $contents);
    }
}
