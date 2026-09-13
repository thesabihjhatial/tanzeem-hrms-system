<?php

// Tanzeem HRMS System Chart Manager developed and maintained by Sabih

namespace App\Utilities;

class ChartManager
{

    private const CENTER_X = 200;

    private const CENTER_Y = 120;

    private const LABEL_GAP = 10;

    private const LEADER_LENGTH = 22;

    private const RADIUS = 70;

    private const SATURATION_LIGHTNESS = '65%, 45%';

    public const VIEWBOX_HEIGHT = 240;

    public const VIEWBOX_WIDTH = 400;

    /**
     * Turns already-aggregated {label, count, percent} segments into
     * everything an SVG pie-with-leader-lines needs to render: the fill
     * shape (a wedge path, or a full circle for the 100% case, which a
     * single arc command can't express), and a leader line + label
     * position pointing out from the wedge's midpoint angle.
     *
     * @param array<int, array{label: string, count: int, percent: float}> $segments
     * @return array<int, array<string, mixed>>
     */
    public static function pieWithLeaders(array $segments): array
    {

        $cumulativePercent = 0.0;
        $isSingleSegment = count($segments) === 1;
        $result = [];

        foreach ($segments as $index => $segment) {

            $startAngle = $cumulativePercent * 3.6 - 90;
            $cumulativePercent += $segment['percent'];
            $endAngle = $cumulativePercent * 3.6 - 90;
            $midAngle = ($startAngle + $endAngle) / 2;

            $result[] = [
                'label' => $segment['label'],
                'count' => $segment['count'],
                'color' => self::spectrumColor($index, count($segments)),
                'is_full_circle' => $isSingleSegment,
                'path' => self::slicePath($startAngle, $endAngle),
                'leader' => self::leaderLine($midAngle),
            ];

        }

        return $result;

    }

    /**
     * Evenly spaces hues around the full color wheel starting at red
     * (hue 0), so 3 segments land exactly on red/green/blue and any
     * further segments continue around the same spectrum rather than
     * repeating a fixed swatch list.
     */
    private static function spectrumColor(int $index, int $total): string
    {

        $hue = (int) round(360 * $index / $total);

        return "hsl({$hue}, " . self::SATURATION_LIGHTNESS . ')';

    }

    /** @return array{x: float, y: float} */
    private static function point(float $angleDegrees, float $radius): array
    {

        $angleRadians = deg2rad($angleDegrees);

        return [
            'x' => round(self::CENTER_X + $radius * cos($angleRadians), 2),
            'y' => round(self::CENTER_Y + $radius * sin($angleRadians), 2),
        ];

    }

    private static function slicePath(float $startAngle, float $endAngle): string
    {

        $start = self::point($startAngle, self::RADIUS);
        $end = self::point($endAngle, self::RADIUS);
        $largeArcFlag = ($endAngle - $startAngle) > 180 ? 1 : 0;

        return sprintf(
            'M%d,%d L%s,%s A%d,%d 0 %d,1 %s,%s Z',
            self::CENTER_X,
            self::CENTER_Y,
            $start['x'],
            $start['y'],
            self::RADIUS,
            self::RADIUS,
            $largeArcFlag,
            $end['x'],
            $end['y'],
        );

    }

    /** @return array{x1: float, y1: float, x2: float, y2: float, x3: float, y3: float, label_x: float, label_y: float, anchor: string} */
    private static function leaderLine(float $midAngle): array
    {

        $inner = self::point($midAngle, self::RADIUS + 2);
        $elbow = self::point($midAngle, self::RADIUS + self::LEADER_LENGTH);
        $direction = cos(deg2rad($midAngle)) >= 0 ? 1 : -1;
        $outerX = $elbow['x'] + $direction * self::LABEL_GAP;

        return [
            'x1' => $inner['x'],
            'y1' => $inner['y'],
            'x2' => $elbow['x'],
            'y2' => $elbow['y'],
            'x3' => $outerX,
            'y3' => $elbow['y'],
            'label_x' => $outerX + $direction * 4,
            'label_y' => $elbow['y'],
            'anchor' => $direction >= 0 ? 'start' : 'end',
        ];

    }

}
