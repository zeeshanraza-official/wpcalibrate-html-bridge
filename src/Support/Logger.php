<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Logger
 *
 * Provides safe, structured, namespaced logging with level filtering and secret scrubbing.
 */
final class Logger
{
    public const LEVEL_DEBUG   = 100;
    public const LEVEL_INFO    = 200;
    public const LEVEL_NOTICE  = 250;
    public const LEVEL_WARNING = 300;
    public const LEVEL_ERROR   = 400;
    public const LEVEL_CRITICAL= 500;

    /**
     * Keys whose values must never be logged.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'pass',
        'secret',
        'key',
        'license_key',
        'license',
        'token',
        'auth',
        'authorization',
        'cookie',
        'nonce',
    ];

    /**
     * Log a message with context at a given level.
     *
     * @param int                  $level   Logging level.
     * @param string               $message Message to log.
     * @param array<string, mixed> $context Additional structured context.
     */
    public static function log(int $level, string $message, array $context = []): void
    {
        $minLevel = self::getMinLogLevel();
        if ($level < $minLevel) {
            return;
        }

        $sanitizedContext = self::sanitizeContext($context);
        $levelName = self::getLevelName($level);
        $timestamp = gmdate('Y-m-d H:i:s');

        $formatted = sprintf(
            '[WPCalibrate HTML Bridge] [%s] [%s] %s %s',
            $timestamp,
            $levelName,
            $message,
            !empty($sanitizedContext) ? wp_json_encode($sanitizedContext, JSON_UNESCAPED_SLASHES) : ''
        );

        // Always respect WP_DEBUG_LOG if active or fallback to error_log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(trim($formatted));
        }

        /**
         * Action fired after an event is logged.
         *
         * @param int                  $level            Log level integer.
         * @param string               $message          Log message.
         * @param array<string, mixed> $sanitizedContext Context with secrets scrubbed.
         */
        do_action('wpcalibrate_html_bridge_logged', $level, $message, $sanitizedContext);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Determine minimum log level based on WP_DEBUG and plugin settings.
     */
    private static function getMinLogLevel(): int
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return self::LEVEL_DEBUG;
        }

        $settings = get_option('wpcalibrate_html_bridge_settings', []);
        if (is_array($settings) && !empty($settings['enable_debug_logging'])) {
            return self::LEVEL_DEBUG;
        }

        return self::LEVEL_INFO;
    }

    /**
     * Map numeric level to string representation.
     */
    private static function getLevelName(int $level): string
    {
        return match ($level) {
            self::LEVEL_DEBUG    => 'DEBUG',
            self::LEVEL_INFO     => 'INFO',
            self::LEVEL_NOTICE   => 'NOTICE',
            self::LEVEL_WARNING  => 'WARNING',
            self::LEVEL_ERROR    => 'ERROR',
            self::LEVEL_CRITICAL => 'CRITICAL',
            default              => 'UNKNOWN',
        };
    }

    /**
     * Recursively scrubs sensitive data and prevents huge text payloads in logs.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private static function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $lowerKey = strtolower((string) $key);

            // Redact known sensitive fields
            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $sanitized[$key] = '[REDACTED]';
                    continue 2;
                }
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeContext($value);
            } elseif (is_string($value)) {
                // Prevent entire uploaded HTML or base64 files from filling logs
                if (strlen($value) > 500) {
                    $sanitized[$key] = substr($value, 0, 497) . '... (truncated)';
                } else {
                    $sanitized[$key] = $value;
                }
            } elseif (is_scalar($value) || null === $value) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = gettype($value);
            }
        }

        return $sanitized;
    }
}
