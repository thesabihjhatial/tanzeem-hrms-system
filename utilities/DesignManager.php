<?php

// Tanzeem HRMS System Design Manager developed and maintained by Sabih

namespace App\Utilities;

class DesignManager
{

    public const COLORS = [
        'ink' => '#1E2A38',
        'ink-soft' => '#4B5A6A',
        'paper' => '#EEECE1',
        'surface' => '#FBFAF6',
        'line' => '#D8D2C2',
        'marigold' => '#C68A1E',
        'marigold-soft' => '#F3E3C3',
        'teal' => '#2F6B5E',
        'teal-soft' => '#DEEAE6',
        'brick' => '#A6402E',
        'brick-soft' => '#F3DFDA',
    ];

    public const FONT = "'IBM Plex Sans', -apple-system, sans-serif";

    public const RADIUS = '4px';

    public const SPACING = [
        'sp-1' => '4px',
        'sp-2' => '8px',
        'sp-3' => '12px',
        'sp-4' => '16px',
        'sp-5' => '24px',
        'sp-6' => '32px',
        'sp-7' => '48px',
    ];

    public const STATUSES = [
        'paid' => 'paid',
        'pending' => 'pending',
        'overdue' => 'overdue',
    ];

    public static function statusBadgeClass(string $status): string
    {

        $status = self::STATUSES[$status] ?? 'pending';

        return "badge badge-{$status}";

    }

    public static function tokensCss(): string
    {

        $lines = [":root {"];

        foreach (self::COLORS as $name => $hex) {

            $lines[] = "    --{$name}: {$hex};";

        }

        foreach (self::SPACING as $name => $value) {

            $lines[] = "    --{$name}: {$value};";

        }

        $lines[] = "    --font: " . self::FONT . ';';
        $lines[] = '    --radius: ' . self::RADIUS . ';';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);

    }

    public static function generate(string $templatesPath): void
    {

        file_put_contents($templatesPath . '/tokens.css', self::tokensCss());

    }

}
