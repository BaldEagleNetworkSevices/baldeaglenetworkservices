<?php
declare(strict_types=1);

function landing_state_store_path(string $type): string
{
    $global = landing_global_config();
    return match ($type) {
        'intake' => $global['state_file'],
        'payments' => $global['payment_state_file'],
        'report_tokens' => $global['report_token_store'],
        default => throw new InvalidArgumentException('Unknown landing state store: ' . $type),
    };
}

function landing_with_locked_json_store(string $type, callable $callback): mixed
{
    $path = landing_state_store_path($type);
    landing_ensure_directory(dirname($path));

    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open landing state store: ' . $path);
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock landing state store.');
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        if ($contents === false) {
            throw new RuntimeException('Unable to read landing state store.');
        }

        $trimmed = trim($contents);
        if ($trimmed === '') {
            $state = [];
        } else {
            $state = json_decode($trimmed, true);
            if (!is_array($state) || json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Landing state store contains invalid JSON.');
            }
        }

        $result = $callback($state);

        $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Unable to encode landing state store.');
        }

        rewind($handle);
        if (!ftruncate($handle, 0)) {
            throw new RuntimeException('Unable to truncate landing state store.');
        }

        $bytesToWrite = strlen($encoded);
        $bytesWritten = 0;
        while ($bytesWritten < $bytesToWrite) {
            $chunk = fwrite($handle, substr($encoded, $bytesWritten));
            if ($chunk === false || $chunk === 0) {
                throw new RuntimeException('Unable to fully write landing state store.');
            }
            $bytesWritten += $chunk;
        }

        if (!fflush($handle)) {
            throw new RuntimeException('Unable to flush landing state store.');
        }

        if (function_exists('fsync') && !fsync($handle)) {
            throw new RuntimeException('Unable to sync landing state store.');
        }

        flock($handle, LOCK_UN);

        return $result;
    } finally {
        fclose($handle);
    }
}
