<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

/**
 * Scene previews of the chapters element. The encoder samples ten thumbnails per video at
 * duration / 11 * (i + 1) seconds, so a scene's start time follows from its thumbnail index.
 */
final class Scenes
{
    public const THUMBNAILS = 10;
    public const DEFAULT_COUNT = 5;
    public const MIN_COUNT = 3;

    /**
     * Thumbnail indices for $count scenes, spread evenly over 0..9: round(k * 9 / (n - 1)).
     *
     * @return list<int>
     */
    public static function indices(int $count): array
    {
        $count = max(2, min(self::THUMBNAILS, $count));
        $indices = [];
        for ($k = 0; $k < $count; ++$k) {
            $indices[] = (int) round($k * (self::THUMBNAILS - 1) / ($count - 1));
        }

        return $indices;
    }

    /**
     * Start time in seconds of thumbnail $index.
     */
    public static function start(float $duration, int $index): int
    {
        return (int) floor($duration / (self::THUMBNAILS + 1) * ($index + 1));
    }

    /**
     * m:ss, or h:mm:ss from one hour on.
     */
    public static function formatTime(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $rest)
            : sprintf('%d:%02d', $minutes, $rest);
    }

    public static function count(mixed $value): int
    {
        return is_numeric($value) ? max(self::MIN_COUNT, min(self::THUMBNAILS, (int) $value)) : self::DEFAULT_COUNT;
    }

    /**
     * Scene titles, one per line (tags stripped, at most one per thumbnail, trailing empty lines dropped).
     *
     * @return list<string>
     */
    public static function titles(mixed $raw): array
    {
        $titles = [];
        foreach (preg_split('/\R/', is_string($raw) ? $raw : '') ?: [] as $line) {
            $titles[] = mb_substr(trim(strip_tags($line)), 0, 80);
        }
        while ($titles !== [] && end($titles) === '') {
            array_pop($titles);
        }

        return array_slice($titles, 0, self::THUMBNAILS);
    }

    /**
     * @param list<string> $titles titles by scene position; an empty one keeps the default label
     * @param callable(int): string $thumbnailUrl URL of a thumbnail index
     *
     * @return list<array{index: int, start: int, time: string, thumb: string, title: string}> `title` is empty for the default label
     */
    public static function build(float $duration, int $count, array $titles, callable $thumbnailUrl): array
    {
        if ($duration <= 0) {
            return [];
        }
        $scenes = [];
        foreach (self::indices($count) as $position => $index) {
            $start = self::start($duration, $index);
            $scenes[] = [
                'index' => $index,
                'start' => $start,
                'time' => self::formatTime($start),
                'thumb' => $thumbnailUrl($index),
                'title' => $titles[$position] ?? '',
            ];
        }

        return $scenes;
    }
}
