<?php

namespace App\Tests\Utilities;

use App\Utilities\FlashManager;
use PHPUnit\Framework\TestCase;

class FlashManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function test_consume_returns_added_messages(): void
    {
        FlashManager::add('Saved.', 'success');

        $messages = FlashManager::consume();

        $this->assertCount(1, $messages);
        $this->assertSame('Saved.', $messages[0]['message']);
        $this->assertSame('success', $messages[0]['type']);
    }

    public function test_consume_clears_messages_so_they_only_show_once(): void
    {
        FlashManager::add('Saved.', 'success');
        FlashManager::consume();

        $this->assertSame([], FlashManager::consume());
    }

    public function test_consume_returns_an_empty_array_when_nothing_was_added(): void
    {
        $this->assertSame([], FlashManager::consume());
    }
}
