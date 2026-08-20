<?php
/**
 * Shared helpers for the lkim.gov.my -> portal-lkim migration scripts.
 */

const SRC   = 'https://www.lkim.gov.my';
const UA    = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) portal-lkim-migration/1.0';
const BASE  = __DIR__;

function out(string $msg): void
{
    echo $msg . PHP_EOL;
}

/**
 * GET a URL, returning [body, headers, httpCode]. Retries transient failures.
 */
function http_get(string $url, int $tries = 4): array
{
    $headers = [];
    $attempt = 0;

    while ($attempt < $tries) {
        $attempt++;
        $headers = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => UA,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body !== false && $code >= 200 && $code < 300) {
            return [$body, $headers, $code];
        }

        // 4xx other than 429 will not get better by retrying.
        if ($code >= 400 && $code < 500 && $code !== 429) {
            return [false, $headers, $code];
        }

        usleep(400000 * $attempt);
    }

    return [false, $headers, $code ?? 0];
}

/**
 * Page through a WP REST collection, yielding each item.
 */
function wp_collection(string $type, array $fields = [], int $perPage = 100): Generator
{
    $page  = 1;
    $total = null;

    do {
        $url = SRC . '/wp-json/wp/v2/' . $type . '?per_page=' . $perPage . '&page=' . $page;
        if ($fields) {
            $url .= '&_fields=' . implode(',', $fields);
        }

        [$body, $headers, $code] = http_get($url);

        if ($body === false) {
            out("  ! $type page $page failed (HTTP $code)");
            break;
        }

        if ($total === null) {
            $total = (int) ($headers['x-wp-totalpages'] ?? 1);
            out("  $type: " . ($headers['x-wp-total'] ?? '?') . " items across $total pages");
        }

        $rows = json_decode($body, true);
        if (!is_array($rows)) {
            out("  ! $type page $page: unparseable JSON");
            break;
        }

        foreach ($rows as $row) {
            yield $row;
        }

        $page++;
        usleep(200000);
    } while ($page <= $total);
}

function jsonl_write(string $file, iterable $rows): int
{
    $fh = fopen($file, 'w');
    $n  = 0;
    foreach ($rows as $row) {
        fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        $n++;
    }
    fclose($fh);
    return $n;
}

/**
 * Write a CSV report. PHP 8.4 deprecates fputcsv() without an explicit $escape,
 * so it is pinned here once rather than at every call site.
 */
function csv_write(string $file, array $header, iterable $rows): int
{
    $fh = fopen($file, 'w');
    fputcsv($fh, $header, ',', '"', '\\');
    $n = 0;

    foreach ($rows as $row) {
        fputcsv($fh, $row, ',', '"', '\\');
        $n++;
    }

    fclose($fh);
    return $n;
}

function jsonl_read(string $file): Generator
{
    $fh = fopen($file, 'r');
    while (($line = fgets($fh)) !== false) {
        $line = trim($line);
        if ($line !== '') {
            yield json_decode($line, true);
        }
    }
    fclose($fh);
}
