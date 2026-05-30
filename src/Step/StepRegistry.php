<?php

declare(strict_types=1);

namespace Drops\Step;

final class StepRegistry
{
    /**
     * Ordered list of built-in step IDs matching the design document's execution order.
     */
    private const STEP_ORDER = [
        'pre_hooks',
        'maintenance_on',
        'database_export',
        'files_export',
        'config_export',
        'database_import',
        'config_import',
        'files_import',
        'database_update',
        'cache_rebuild',
        'maintenance_off',
        'post_hooks',
    ];

    /**
     * Step order for fresh installs: database first so Drupal can bootstrap,
     * then config/files/updates. Maintenance mode is omitted (no running site).
     */
    private const STEP_ORDER_FRESH = [
        'pre_hooks',
        'database_import',
        'config_import',
        'files_import',
        'database_update',
        'cache_rebuild',
        'post_hooks',
    ];

    /** @var array<string, StepInterface> */
    private array $steps = [];

    public function __construct()
    {
        $this->registerBuiltinSteps();
    }

    /**
     * Register a custom step.
     */
    public function register(StepInterface $step): void
    {
        $this->steps[$step->getId()] = $step;
    }

    /**
     * Get a step by ID.
     */
    public function get(string $id): ?StepInterface
    {
        return $this->steps[$id] ?? null;
    }

    /**
     * Get all steps that apply to the export phase, in execution order.
     *
     * @return StepInterface[]
     */
    public function getExportSteps(): array
    {
        return $this->filterByPhase(fn(Phase $p) => $p->appliesToExport());
    }

    /**
     * Get all steps that apply to the import phase, in execution order.
     *
     * @return StepInterface[]
     */
    public function getImportSteps(): array
    {
        return $this->filterByPhase(fn(Phase $p) => $p->appliesToImport());
    }

    /**
     * Get import steps reordered for a fresh/empty Drupal install.
     *
     * Database import runs first so Drupal can bootstrap, then config
     * import, files, updates, and cache rebuild. Maintenance mode steps
     * are excluded since there is no running site to protect.
     *
     * @return StepInterface[]
     */
    public function getImportStepsForFreshInstall(): array
    {
        return $this->filterByPhase(
            fn(Phase $p) => $p->appliesToImport(),
            self::STEP_ORDER_FRESH,
        );
    }

    /**
     * Get all registered step IDs.
     *
     * @return string[]
     */
    public function getStepIds(): array
    {
        return array_keys($this->steps);
    }

    /**
     * Load custom steps from a steps.php file.
     */
    public function loadCustomSteps(string $stepsFile): void
    {
        if (!file_exists($stepsFile)) {
            return;
        }

        $classes = require $stepsFile;
        if (!is_array($classes)) {
            return;
        }

        foreach ($classes as $class) {
            if (is_string($class) && class_exists($class)) {
                $step = new $class();
                if ($step instanceof StepInterface) {
                    $this->register($step);
                }
            }
        }
    }

    private function registerBuiltinSteps(): void
    {
        $builtins = [
            new PreHooksStep(),
            new MaintenanceOnStep(),
            new DatabaseExportStep(),
            new FilesExportStep(),
            new ConfigExportStep(),
            new ConfigImportStep(),
            new FilesImportStep(),
            new DatabaseImportStep(),
            new DatabaseUpdateStep(),
            new CacheRebuildStep(),
            new MaintenanceOffStep(),
            new PostHooksStep(),
        ];

        foreach ($builtins as $step) {
            $this->steps[$step->getId()] = $step;
        }
    }

    /**
     * @param callable(Phase): bool $filter
     * @param string[]|null $order Step order to use (defaults to STEP_ORDER)
     * @return StepInterface[]
     */
    private function filterByPhase(callable $filter, ?array $order = null): array
    {
        $order ??= self::STEP_ORDER;
        $result = [];

        // Maintain the defined order for built-in steps
        foreach ($order as $id) {
            if (isset($this->steps[$id]) && $filter($this->steps[$id]->getPhase())) {
                $result[] = $this->steps[$id];
            }
        }

        // Append any custom steps not in any built-in order
        foreach ($this->steps as $id => $step) {
            if (
                !in_array($id, self::STEP_ORDER, true)
                && !in_array($id, $order, true)
                && $filter($step->getPhase())
            ) {
                $result[] = $step;
            }
        }

        return $result;
    }
}
