<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Import;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ImportState
 *
 * Defines explicit import states and validates allowable state transitions.
 */
final class ImportState
{
    public const UPLOADED                = 'uploaded';
    public const SCANNING                = 'scanning';
    public const SCANNED                 = 'scanned';
    public const PREVIEW_READY           = 'preview_ready';
    public const QUEUED                  = 'queued';
    public const IMPORTING               = 'importing';
    public const COMPLETED               = 'completed';
    public const COMPLETED_WITH_WARNINGS = 'completed_with_warnings';
    public const FAILED                  = 'failed';
    public const ROLLING_BACK            = 'rolling_back';
    public const ROLLED_BACK             = 'rolled_back';
    public const CANCELLED               = 'cancelled';

    /**
     * Map of valid forward transitions.
     */
    private const ALLOWED_TRANSITIONS = [
        self::UPLOADED => [
            self::SCANNING,
            self::FAILED,
            self::CANCELLED,
        ],
        self::SCANNING => [
            self::SCANNED,
            self::PREVIEW_READY,
            self::FAILED,
            self::CANCELLED,
        ],
        self::SCANNED => [
            self::PREVIEW_READY,
            self::QUEUED,
            self::FAILED,
            self::CANCELLED,
        ],
        self::PREVIEW_READY => [
            self::QUEUED,
            self::IMPORTING,
            self::CANCELLED,
            self::FAILED,
        ],
        self::QUEUED => [
            self::IMPORTING,
            self::CANCELLED,
            self::FAILED,
        ],
        self::IMPORTING => [
            self::IMPORTING, // self-transition for batches
            self::COMPLETED,
            self::COMPLETED_WITH_WARNINGS,
            self::FAILED,
            self::ROLLING_BACK,
        ],
        self::COMPLETED => [
            self::ROLLING_BACK,
            self::QUEUED, // re-import
        ],
        self::COMPLETED_WITH_WARNINGS => [
            self::ROLLING_BACK,
            self::QUEUED, // re-import
        ],
        self::FAILED => [
            self::QUEUED, // retry
            self::ROLLING_BACK,
        ],
        self::ROLLING_BACK => [
            self::ROLLED_BACK,
            self::FAILED,
        ],
        self::ROLLED_BACK => [
            self::QUEUED, // re-import allowed after rollback
        ],
        self::CANCELLED => [],
    ];

    /**
     * Validate if a transition from currentState to nextState is allowed.
     */
    public static function canTransition(string $currentState, string $nextState): bool
    {
        if ($currentState === $nextState) {
            return true;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$currentState] ?? [];
        return in_array($nextState, $allowed, true);
    }
}
