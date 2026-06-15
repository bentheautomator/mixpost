<?php

namespace Inovector\Mixpost;

use DateTimeInterface;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Inovector\Mixpost\Facades\Settings;

class Util
{
    public static function config(string $key, mixed $default = null)
    {
        return Config::get("mixpost.$key", $default);
    }

    public static function isMixpostRequest(Request $request): bool
    {
        $path = 'mixpost';

        return $request->is($path) ||
            $request->is("$path/*");
    }

    public static function convertTimeToUTC(string|DateTimeInterface|null $time = null, DateTimeZone|string|null $tz = null): Carbon
    {
        return Carbon::parse($time, $tz ?: Settings::get('timezone'))->utc();
    }

    public static function dateTimeFormat(Carbon $datetime, DateTimeZone|string|null $tz = null): string
    {
        $format = $datetime->year === now($tz)->year ? 'M j, '.self::timeFormat() : 'M j, Y, '.self::timeFormat();

        return $datetime->tz($tz ?: Settings::get('timezone'))->translatedFormat($format);
    }

    public static function timeFormat(): string
    {
        return Settings::get('time_format') == 24 ? 'H:i' : 'h:ia';
    }

    public static function removeHtmlTags($string): string
    {
        if (! $string) {
            return '';
        }

        $text = trim(strip_tags($string));

        return html_entity_decode($text);
    }

    public static function isPublicDomainUrl(string $url): bool
    {
        // Validate URL format
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parsedUrl = parse_url($url);

        if (empty($parsedUrl['host'])) {
            return false;
        }

        // Only http/https are allowed (blocks file://, gopher://, ftp://, etc.).
        $scheme = strtolower($parsedUrl['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        // Normalise the host (strip IPv6 brackets, lowercase).
        $host = strtolower(trim($parsedUrl['host'], '[]'));

        // Explicitly block well-known local hostnames and unspecified addresses.
        if (in_array($host, ['localhost', '0.0.0.0', '0', '::', '::1'], true)) {
            return false;
        }

        // Resolve the host to every IP it points at and reject if ANY of them is
        // private/reserved. This prevents SSRF via hostnames that resolve to internal
        // hosts or the cloud metadata endpoint (e.g. 169.254.169.254). Hosts that do
        // not resolve at all are allowed here — the subsequent connection simply fails,
        // and a hostname pointing at a private IP is still caught below.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = self::resolveHostIps($host);
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve a hostname to its IPv4 and IPv6 addresses.
     *
     * @return array<int, string>
     */
    protected static function resolveHostIps(string $host): array
    {
        $ips = [];

        $ipv4 = @gethostbynamel($host);

        if (is_array($ipv4)) {
            $ips = array_merge($ips, $ipv4);
        }

        $records = @dns_get_record($host, DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Determine whether an IP address is a routable, public address.
     * Private (RFC1918) and reserved ranges (loopback, link-local incl. the
     * 169.254.169.254 metadata address, etc.) are rejected.
     */
    protected static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    public static function getDatabaseDriver(?string $connection = null): string
    {
        $key = is_null($connection) ? Config::get('database.default') : $connection;

        return strtolower(Config::get('database.connections.'.$key.'.driver'));
    }

    public static function isMysqlDatabase(?string $connection = null): bool
    {
        return self::getDatabaseDriver($connection) === 'mysql';
    }

    public static function closeAndDeleteStreamResource(array $stream): void
    {
        if (is_resource($stream['stream'])) {
            fclose($stream['stream']);
        }

        if (isset($stream['temporaryDirectory'])) {
            $stream['temporaryDirectory']->delete();
        }
    }

    public static function performTaskWithDelay(callable $task, int $initialDelay = 15, int $maxDelay = 60, int $maxAttempts = 10)
    {
        $delay = $initialDelay;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $result = $task();

            if ($result !== null) {
                return $result;
            }

            sleep($delay);

            // Increase delay for the next iteration, maxing out at maxDelay
            $delay = min($delay * 2, $maxDelay);
            // Add a random jitter to the delay
            $delay += rand(-(int) ($delay * 0.1), (int) ($delay * 0.1));

            $attempt++;
        }

        return null;
    }

    public static function isFFmpegInstalled(): bool
    {
        $ffmpegPath = Util::config('ffmpeg_path');
        $ffprobePath = Util::config('ffprobe_path');

        return file_exists($ffmpegPath) &&
            file_exists($ffprobePath) &&
            str_ends_with($ffmpegPath, 'ffmpeg') &&
            str_ends_with($ffprobePath, 'ffprobe');
    }
}
