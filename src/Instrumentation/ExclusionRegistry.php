<?php
declare(strict_types=1);

namespace OtelInstrumentation\Instrumentation;

/**
 * Registry of Controller/action pairs whose OTel instrumentation should be skipped.
 *
 * When a Controller::invokeAction matches a registered rule, ControllerInstrumentation
 * skips span creation and increments an internal depth counter. While the counter is
 * non-zero, TableInstrumentation and CustomInstrumentation also skip span creation,
 * so child Table operations and custom hooks invoked under the excluded action are
 * suppressed too.
 *
 * State is process-local (static). zend_observer hooks themselves cannot be removed
 * at runtime, so reset() only clears registry state — the hook callbacks stay
 * installed and consult this registry at every invocation.
 */
final class ExclusionRegistry
{
    /** @var array<string, array<string, true>> map of FQCN => map of action => true (action may be '*') */
    private static array $rules = [];

    private static int $depth = 0;

    /**
     * Register exclusion entries from Configure.
     *
     * Each entry must be ['controller' => FQCN, 'action' => string].
     * The '*' wildcard is allowed only on action; using it on controller throws.
     *
     * @param array<int, array{controller: string, action: string}> $entries
     */
    public static function register(array $entries): void
    {
        foreach ($entries as $i => $entry) {
            if (!is_array($entry) || !isset($entry['controller']) || !isset($entry['action'])) {
                throw new \InvalidArgumentException(
                    sprintf('OtelInstrumentation.exclude[%d] must have "controller" and "action" keys.', $i)
                );
            }

            $controller = $entry['controller'];
            $action = $entry['action'];

            if (!is_string($controller) || $controller === '' || $controller === '*') {
                throw new \InvalidArgumentException(
                    sprintf('OtelInstrumentation.exclude[%d].controller must be a non-empty FQCN ("*" is not allowed).', $i)
                );
            }
            if (!is_string($action) || $action === '') {
                throw new \InvalidArgumentException(
                    sprintf('OtelInstrumentation.exclude[%d].action must be a non-empty string.', $i)
                );
            }

            self::$rules[$controller][$action] = true;
        }
    }

    public static function isExcluded(string $controllerClass, string $action): bool
    {
        if (!isset(self::$rules[$controllerClass])) {
            return false;
        }

        return isset(self::$rules[$controllerClass][$action])
            || isset(self::$rules[$controllerClass]['*']);
    }

    /**
     * Mark entry into an excluded action. Returns true if the call is excluded
     * (caller should skip span creation).
     */
    public static function enter(string $controllerClass, string $action): bool
    {
        if (!self::isExcluded($controllerClass, $action)) {
            return false;
        }

        self::$depth++;

        return true;
    }

    /**
     * Mark exit from an excluded action. Safe to call when depth is 0.
     */
    public static function leave(): void
    {
        if (self::$depth > 0) {
            self::$depth--;
        }
    }

    public static function isCurrentlyExcluded(): bool
    {
        return self::$depth > 0;
    }

    /**
     * Reset internal state (for testing).
     *
     * Does NOT remove hook callbacks installed via \OpenTelemetry\Instrumentation\hook() —
     * zend_observer hooks cannot be uninstalled at runtime. The callbacks remain but will
     * see an empty rule set after this call.
     */
    public static function reset(): void
    {
        self::$rules = [];
        self::$depth = 0;
    }
}
