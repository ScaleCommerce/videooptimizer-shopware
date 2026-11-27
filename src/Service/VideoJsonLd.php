<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

/**
 * schema.org VideoObject for a rendered video, as the JSON of a `<script type="application/ld+json">`.
 */
final class VideoJsonLd
{
    /**
     * @param array<string, mixed> $embed normalized embed (hls, mp4, poster, title, duration)
     * @param string $embedUrl the hosted player URL of the video, without query
     *
     * @return string|null JSON that is safe inside a script element, null without a title, media, poster or upload date
     */
    public static function build(array $embed, string $embedUrl, ?string $uploadDate): ?string
    {
        $title = is_string($embed['title'] ?? null) ? trim($embed['title']) : '';
        $contentUrl = $embed['hls'] ?? $embed['mp4'] ?? null;
        if ($title === '' || !is_string($contentUrl)) {
            return null;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $title,
            // The API has no separate description; the title is the fallback Google accepts.
            'description' => $title,
        ];
        // Search engines flag a VideoObject without thumbnail or upload date, so without both nothing is output.
        $poster = $embed['poster'] ?? null;
        $date = self::date($uploadDate);
        if (!is_string($poster) || $poster === '' || $date === null) {
            return null;
        }
        $data['thumbnailUrl'] = [$poster];
        $data['uploadDate'] = $date;
        $duration = self::duration($embed['duration'] ?? null);
        if ($duration !== null) {
            $data['duration'] = $duration;
        }
        $data['contentUrl'] = $contentUrl;
        $data['embedUrl'] = $embedUrl;

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? null : $json;
    }

    /**
     * ISO 8601 duration of a number of seconds, e.g. 65 -> PT1M5S.
     */
    public static function duration(mixed $seconds): ?string
    {
        if (!is_numeric($seconds) || (float) $seconds <= 0) {
            return null;
        }
        $total = (int) round((float) $seconds);
        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);
        $rest = $total % 60;

        return 'PT' . ($hours > 0 ? $hours . 'H' : '') . ($minutes > 0 ? $minutes . 'M' : '') . ($rest > 0 || ($hours === 0 && $minutes === 0) ? $rest . 'S' : '');
    }

    /**
     * The date as ISO 8601 with its own UTC offset kept (no shift of the day), null if unparseable.
     */
    private static function date(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (\Exception) {
            return null;
        }
    }
}
