<?php
declare(strict_types=1);

function landing_append_ndjson_record(string $path, array $record): void
{
    landing_ensure_directory(dirname($path));

    $encoded = json_encode($record, JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode landing hook record.');
    }

    $line = $encoded . PHP_EOL;
    $handle = fopen($path, 'ab');
    if ($handle === false) {
        throw new RuntimeException('Unable to open landing hook queue.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock landing hook queue.');
        }

        $bytesToWrite = strlen($line);
        $bytesWritten = 0;
        while ($bytesWritten < $bytesToWrite) {
            $chunk = fwrite($handle, substr($line, $bytesWritten));
            if ($chunk === false || $chunk === 0) {
                throw new RuntimeException('Unable to fully write landing hook queue.');
            }
            $bytesWritten += $chunk;
        }

        if (!fflush($handle)) {
            throw new RuntimeException('Unable to flush landing hook queue.');
        }

        if (function_exists('fsync') && !fsync($handle)) {
            throw new RuntimeException('Unable to sync landing hook queue.');
        }

        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

function landing_log_payment_event(string $event, array $context = []): void
{
    $global = landing_global_config();
    landing_append_ndjson_record($global['payment_event_log_file'], [
        'timestamp' => gmdate(DATE_ATOM),
        'event' => $event,
        'context' => $context,
    ]);
}

function landing_enqueue_email_hook(string $event, array $payload): void
{
    $global = landing_global_config();
    landing_append_ndjson_record($global['email_hook_queue_file'], [
        'queued_at' => gmdate(DATE_ATOM),
        'event' => $event,
        'payload' => $payload,
    ]);
}

function landing_enqueue_qbo_hook(string $event, array $payload): void
{
    $global = landing_global_config();
    landing_append_ndjson_record($global['qbo_hook_queue_file'], [
        'queued_at' => gmdate(DATE_ATOM),
        'event' => $event,
        'payload' => $payload,
    ]);
}

function landing_enqueue_crm_payment_hook(string $event, array $payload): void
{
    $global = landing_global_config();
    landing_append_ndjson_record($global['crm_payment_hook_queue_file'], [
        'queued_at' => gmdate(DATE_ATOM),
        'event' => $event,
        'payload' => $payload,
    ]);
}
