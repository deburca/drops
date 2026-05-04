<?php

declare(strict_types=1);

namespace Drops\Step;

use Drops\Pipeline\DeployContext;
use Drops\Pipeline\StepResult;

final class PostHooksStep implements StepInterface
{
    public function getId(): string
    {
        return 'post_hooks';
    }

    public function getLabel(): string
    {
        return 'Post-deployment hooks';
    }

    public function getPhase(): Phase
    {
        return Phase::BOTH;
    }

    public function run(DeployContext $context): StepResult
    {
        $config = $context->getStepConfig('post_hooks');
        $phase = $context->packageBuilder !== null ? 'export' : 'import';
        $scriptsKey = $phase . '_scripts';
        $scripts = $config[$scriptsKey] ?? [];

        if (empty($scripts)) {
            return StepResult::skipped('No post-hook scripts configured for ' . $phase);
        }

        $log = [];
        $hookEnvVars = [
            'DROPS_APP_ID' => $context->appConfig->id,
            'DROPS_ENV_ID' => $context->envConfig->id,
            'DROPS_PHASE' => $phase,
            'DROPS_WEBROOT' => $context->envConfig->webroot,
            'DROPS_DRUSH' => $context->envConfig->getDrushPath(),
        ];

        if ($context->envConfig->uri !== null) {
            $hookEnvVars['DROPS_URI'] = $context->envConfig->uri;
        }

        if ($context->packageBuilder !== null) {
            $hookEnvVars['DROPS_PACKAGE_DIR'] = $context->packageBuilder->getPackageDir();
        } elseif ($context->packageReader !== null) {
            $hookEnvVars['DROPS_PACKAGE_DIR'] = $context->packageReader->getExtractedDir();
        }

        foreach ($scripts as $script) {
            $log[] = sprintf('Running: %s', $script);
            $result = $context->environment->execute('bash ' . escapeshellarg($script), $hookEnvVars);

            if (!$result->isSuccessful()) {
                return StepResult::failed(
                    sprintf('Post-hook script failed: %s (exit code %d)', $script, $result->exitCode),
                    array_merge($log, [$result->getErrorOutput()]),
                );
            }

            if ($result->getOutput() !== '') {
                $log[] = $result->getOutput();
            }
        }

        return StepResult::success($log);
    }
}
