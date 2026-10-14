<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Import;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ImportLock
 *
 * Implements atomic distributed locks to prevent duplicate concurrent batch processing.
 */
final class ImportLock
{
    private const LOCK_TTL = 120; // 2 minutes auto-expiry for stale workers

    /**
     * Acquire a processing lock for a specific import UUID.
     *
     * @param string $uuid
     * @return bool True if lock acquired, false if already locked.
     */
    public static function acquire(string $uuid): bool
    {
        $lockKey = 'wpcalibrate_lock_' . md5($uuid);
        $now = time();

        $existing = get_transient($lockKey);
        if ($existing !== false && (int) $existing > $now) {
            // Already locked by an active worker
            return false;
        }

        return set_transient($lockKey, $now + self::LOCK_TTL, self::LOCK_TTL);
    }

    /**
     * Refresh the lock heartbeat during long batch work.
     *
     * @param string $uuid
     */
    public static function refresh(string $uuid): void
    {
        $lockKey = 'wpcalibrate_lock_' . md5($uuid);
        set_transient($lockKey, time() + self::LOCK_TTL, self::LOCK_TTL);
    }

    /**
     * Release the lock.
     *
     * @param string $uuid
     */
    public static function release(string $uuid): void
    {
        $lockKey = 'wpcalibrate_lock_' . md5($uuid);
        delete_transient($lockKey);
    }
}
