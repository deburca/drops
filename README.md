# DROPS — Drupal Remote Operations and Pipeline System

A PHP command-line tool for deploying Drupal websites between environments. Built on [Symfony Console](https://symfony.com/doc/current/components/console.html), distributed as a Composer package, and designed to align naturally with the Drupal developer ecosystem.

DROPS models a deployment as a two-phase operation:

1. **Export** — run on the source environment to produce a portable deployment package (`.tar.gz`)
2. **Import** — run on the target environment to apply the deployment package

The package can be transferred by any means (scp, SFTP, S3, shared NFS, etc.).

## Requirements

| Requirement | Version / Details |
|---|---|
| PHP | 8.2 or higher |
| PHP extensions | `json`, `phar`, `zlib` |
| System tools | `rsync` (file transfer steps), `gzip` (database dump compression) |
| SSH | SSH agent or key file on the machine running DROPS (for remote environments) |

## Installation

### Option 1: Global Composer install (recommended for Drupal developers)

```bash
composer global require drops/drops
```

This adds `drops` to `~/.composer/vendor/bin/drops`. Ensure that directory is on your `$PATH`:

```bash
# Add to ~/.zshrc or ~/.bashrc:
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

### Option 2: Clone and symlink

```bash
git clone https://github.com/deburca/drops.git
cd drops
composer install
ln -s "$(pwd)/bin/drops" /usr/local/bin/drops
```

### Option 3: Project-local install

```bash
composer require drops/drops
./vendor/bin/drops --version
```

### Verify installation

```bash
drops --version
# DROPS — Drupal Remote Operations and Pipeline System 1.0.0
```

## Configuration

All configuration lives in a single directory (default: `~/.drops/`, override with `--config-dir`). Configuration files are written in YAML.

### Directory layout

```
~/.drops/
├── environments/
│   ├── production.yml
│   ├── staging.yml
│   └── local-dev.yml
├── applications/
│   ├── acme-corp.yml
│   └── staff-intranet.yml
└── steps.php              # Optional: register custom steps
```

Create the directory structure:

```bash
mkdir -p ~/.drops/environments ~/.drops/applications
```

### Environment configuration

Each environment describes *how* to reach a machine and *where* the Drupal site lives.

**SSH environment:**

```yaml
# ~/.drops/environments/production.yml
id: production
label: "Production Server"

access:
  type: ssh
  host: prod.example.com
  port: 22
  user: deploy
  identity_file: ~/.ssh/id_ed25519   # Optional; omit to use SSH agent

paths:
  webroot: /var/www/drupal/web
  drush: /var/www/drupal/vendor/bin/drush
  php: /usr/bin/php8.2
  temp: /tmp/drops
  private_files: /var/private-files/drupal   # Optional; Drupal's file_private_path

env_vars:
  APP_ENV: production
```

**Local environment:**

```yaml
# ~/.drops/environments/local-dev.yml
id: local-dev
label: "Local Development"

access:
  type: local

paths:
  webroot: /home/alice/projects/acme/web
  drush: /home/alice/projects/acme/vendor/bin/drush
```

**Multi-site environment:**

For Drupal multi-site installs where multiple sites share a single codebase, add a `uri` field. This tells DROPS which site to target — Drush commands will include `--uri`, and file operations will use `sites/<uri>/` instead of `sites/default/`.

Create one environment config per site:

```yaml
# ~/.drops/environments/production-site-a.yml
id: production-site-a
label: "Production — Site A"

access:
  type: ssh
  host: prod.example.com
  user: deploy

paths:
  webroot: /var/www/drupal/web
  drush: /var/www/drupal/vendor/bin/drush

uri: site-a.example.com
```

```yaml
# ~/.drops/environments/production-site-b.yml
id: production-site-b
label: "Production — Site B"

access:
  type: ssh
  host: prod.example.com
  user: deploy

paths:
  webroot: /var/www/drupal/web
  drush: /var/www/drupal/vendor/bin/drush

uri: site-b.example.com
```

When `uri` is omitted, DROPS behaves as a standard single-site install (`sites/default/`).

**DDEV environment:**

For DDEV projects, set `access.exec` to route all commands through the container. Use container paths for `webroot` and `drush`:

```yaml
# ~/.drops/environments/ddev-local.yml
id: ddev-local
label: "Local DDEV"

access:
  type: local
  exec: ddev exec -p my-project     # All commands run inside the DDEV web container

paths:
  webroot: /var/www/html/web         # Container path (DDEV mounts project to /var/www/html)
  drush: drush                       # Drush is on PATH inside the container
  temp: /tmp/drops                   # Host path — package staging stays on the host
```

**Lando environment:**

```yaml
# ~/.drops/environments/lando-local.yml
id: lando-local
label: "Local Lando"

access:
  type: local
  exec: lando ssh -c                 # All commands run inside the Lando appserver

paths:
  webroot: /app/web                  # Container path (Lando mounts project to /app)
  drush: drush
  temp: /tmp/drops
```

The `access.exec` option wraps all executed commands (Drush, database, hooks) with the given prefix so they run inside the container. File operations (upload/download) stay on the host since the project filesystem is volume-mounted.

### Application configuration

Each application defines a Drupal site and which deployment steps it requires.

**Full example (all steps enabled):**

```yaml
# ~/.drops/applications/acme-corp.yml
id: acme-corp
label: "ACME Corp Website"

steps:
  pre_hooks: true
  maintenance_on: true
  database_export: true
  files_export: true
  config_export: true
  config_import: true
  files_import: true
  database_import: true
  database_update: true
  cache_rebuild: true
  maintenance_off: true
  post_hooks: true

step_config:
  database_export:
    skip_data_tables:
      - cache
      - cache_*
      - watchdog
      - sessions

  config_export:
    sync_dir: ../config/sync

  config_import:
    sync_dir: ../config/sync

  files_export:
    directories:
      - files/public
    exclude:
      - "*.log"
      - ".DS_Store"
      - "styles/"

  files_import:
    directories:
      - files/public
    delete_removed: false

  pre_hooks:
    export_scripts:
      - hooks/pre-export.sh
    import_scripts:
      - hooks/pre-import.sh

  post_hooks:
    export_scripts:
      - hooks/post-export.sh
    import_scripts:
      - hooks/post-import.sh

import_options:
  create_rollback_package: true
  rollback_package_dir: /var/backups/drops/
```

**Minimal example (cache and database updates only):**

```yaml
# ~/.drops/applications/staff-intranet.yml
id: staff-intranet
label: "Staff Intranet"

steps:
  pre_hooks: false
  maintenance_on: true
  database_export: false
  files_export: false
  config_export: false
  config_import: false
  files_import: false
  database_import: false
  database_update: true
  cache_rebuild: true
  maintenance_off: true
  post_hooks: false
```

## Usage

### Verify connectivity

```bash
drops ping --env=production
```

### Validate configuration

```bash
drops validate --all
drops validate --app=acme-corp --env=production
```

### Export a deployment package

```bash
drops export \
  --app=acme-corp \
  --env=production \
  --output=./packages/acme-$(date +%Y%m%d-%H%M%S).tar.gz
```

### Import a deployment package

```bash
drops import \
  --app=acme-corp \
  --env=staging \
  --package=./packages/acme-20250503-141500.tar.gz
```

### Dry run

```bash
drops import \
  --app=acme-corp \
  --env=production \
  --package=./deploy.tar.gz \
  --dry-run
```

### Override steps at runtime

```bash
# Run only specific steps
drops import --app=acme-corp --env=staging \
  --package=./deploy.tar.gz \
  --steps=database_update,cache_rebuild

# Skip specific steps
drops export --app=acme-corp --env=production \
  --output=./deploy.tar.gz \
  --skip-steps=files_export
```

### List resources

```bash
drops list:environments
drops list:applications
```

## Global options

```
--config-dir=PATH    Path to config directory (default: ~/.drops)
--dry-run            Print what would happen without executing
--continue-on-error  Continue running steps after a failure
--no-ansi            Disable terminal colour output
--help               Show help for a command
--version            Show DROPS version
```

## Built-in deployment steps

| Step | Phase | Description |
|---|---|---|
| `pre_hooks` | Both | Run user-defined scripts before all other steps |
| `maintenance_on` | Import | Enable Drupal maintenance mode |
| `database_export` | Export | Dump the source database to the package |
| `files_export` | Export | Archive file assets from the source |
| `config_export` | Export | Run `drush config:export` and capture output |
| `config_import` | Import | Run `drush config:import` from the package |
| `files_import` | Import | Restore file assets from the package |
| `database_import` | Import | Restore the database dump to the target |
| `database_update` | Import | Run `drush updatedb` on the target |
| `cache_rebuild` | Import | Run `drush cache:rebuild` on the target |
| `maintenance_off` | Import | Disable Drupal maintenance mode |
| `post_hooks` | Both | Run user-defined scripts after all other steps |

## Custom steps

Custom steps extend DROPS with site-specific deployment logic. A custom step is a PHP class implementing `Drops\Step\StepInterface` with four methods:

| Method | Returns | Purpose |
|---|---|---|
| `getId()` | `string` | Unique identifier used in application config |
| `getLabel()` | `string` | Human-readable name shown in progress output |
| `getPhase()` | `Phase` | When the step runs: `Phase::EXPORT`, `Phase::IMPORT`, or `Phase::BOTH` |
| `run(DeployContext $context)` | `StepResult` | Execute the step; return success, failure, or skipped |

The `DeployContext` passed to `run()` gives access to:

- `$context->environment` — execute commands on the target via `execute()`, `upload()`, `download()`
- `$context->appConfig` / `$context->envConfig` — full application and environment configuration
- `$context->drushCommand('...')` — build a Drush command with the correct path
- `$context->getStepConfig('step_id')` — read step-specific config from the application YAML
- `$context->output` — Symfony Console output for logging
- `$context->dryRun` — whether this is a dry run

### Example: Cache warming step

This step runs after import to warm Drupal's caches by requesting key URLs:

```php
<?php
// src/Steps/WarmCacheStep.php

declare(strict_types=1);

namespace MyCompany\Drops\Steps;

use Drops\Pipeline\DeployContext;
use Drops\Pipeline\StepResult;
use Drops\Step\Phase;
use Drops\Step\StepInterface;

final class WarmCacheStep implements StepInterface
{
    public function getId(): string
    {
        return 'warm_cache';
    }

    public function getLabel(): string
    {
        return 'Warm caches';
    }

    public function getPhase(): Phase
    {
        return Phase::IMPORT;
    }

    public function run(DeployContext $context): StepResult
    {
        if ($context->dryRun) {
            return StepResult::skipped('Dry run');
        }

        $config = $context->getStepConfig('warm_cache');
        $urls = $config['urls'] ?? ['/'];
        $concurrency = $config['concurrency'] ?? 3;
        $log = [];

        // Use Drush to get the site's base URL
        $result = $context->environment->execute(
            $context->drushCommand('browse --no-browser')
        );

        if (!$result->isSuccessful()) {
            return StepResult::failed('Could not determine site URL', [$result->getErrorOutput()]);
        }

        $baseUrl = rtrim(trim($result->getOutput()), '/');
        $log[] = sprintf('Base URL: %s', $baseUrl);
        $log[] = sprintf('Warming %d URLs (concurrency: %d)...', count($urls), $concurrency);

        // Build a curl command that requests all URLs
        $curlArgs = [];
        foreach ($urls as $url) {
            $fullUrl = $baseUrl . '/' . ltrim($url, '/');
            $curlArgs[] = sprintf('-o /dev/null -s -w "%%{http_code} %s\n" %s',
                $fullUrl,
                escapeshellarg($fullUrl),
            );
        }

        $command = sprintf(
            'curl --parallel --parallel-max %d %s',
            $concurrency,
            implode(' ', $curlArgs),
        );

        $curlResult = $context->environment->execute($command);
        if ($curlResult->getOutput() !== '') {
            $log[] = $curlResult->getOutput();
        }

        $log[] = 'Cache warming complete';

        return StepResult::success($log);
    }
}
```

Application config for this step:

```yaml
# In your application YAML
steps:
  warm_cache: true

step_config:
  warm_cache:
    concurrency: 5
    urls:
      - /
      - /about
      - /products
      - /contact
      - /sitemap.xml
```

### Example: Slack notification step

This step sends a Slack message after import completes, reporting the application, environment, and deployment timestamp:

```php
<?php
// src/Steps/NotifySlackStep.php

declare(strict_types=1);

namespace MyCompany\Drops\Steps;

use Drops\Pipeline\DeployContext;
use Drops\Pipeline\StepResult;
use Drops\Step\Phase;
use Drops\Step\StepInterface;

final class NotifySlackStep implements StepInterface
{
    public function getId(): string
    {
        return 'notify_slack';
    }

    public function getLabel(): string
    {
        return 'Notify Slack';
    }

    public function getPhase(): Phase
    {
        return Phase::IMPORT;
    }

    public function run(DeployContext $context): StepResult
    {
        if ($context->dryRun) {
            return StepResult::skipped('Dry run');
        }

        $config = $context->getStepConfig('notify_slack');
        $webhookEnvVar = $config['webhook_env_var'] ?? 'SLACK_WEBHOOK_URL';
        $channel = $config['channel'] ?? null;

        // Read the webhook URL from an environment variable (never from config files)
        $webhookUrl = $context->envConfig->envVars[$webhookEnvVar] ?? null;

        if ($webhookUrl === null) {
            return StepResult::skipped(
                sprintf('No %s set in environment config env_vars', $webhookEnvVar)
            );
        }

        $appLabel = $context->appConfig->label ?? $context->appConfig->id;
        $envLabel = $context->envConfig->label ?? $context->envConfig->id;
        $timestamp = date('Y-m-d H:i:s T');

        $payload = [
            'text' => sprintf(
                "✅ *%s* deployed to *%s*\n_%s_",
                $appLabel,
                $envLabel,
                $timestamp,
            ),
        ];

        if ($channel !== null) {
            $payload['channel'] = $channel;
        }

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $command = sprintf(
            'curl -s -X POST %s -H %s -d %s',
            escapeshellarg($webhookUrl),
            escapeshellarg('Content-type: application/json'),
            escapeshellarg($jsonPayload),
        );

        $result = $context->environment->execute($command);

        if (!$result->isSuccessful()) {
            // Notification failure shouldn't break the deployment
            $context->output->writeln(
                sprintf('<comment>Slack notification failed: %s</comment>', $result->getErrorOutput())
            );
            return StepResult::success(['Slack notification failed (non-fatal)']);
        }

        return StepResult::success([sprintf('Slack notification sent to %s', $envLabel)]);
    }
}
```

Application and environment config for this step:

```yaml
# In your application YAML
steps:
  notify_slack: true

step_config:
  notify_slack:
    webhook_env_var: SLACK_WEBHOOK_URL   # Name of the env_var holding the URL
    channel: "#deployments"              # Optional: override default channel
```

```yaml
# In your environment YAML — the webhook URL is kept here, not in app config
env_vars:
  APP_ENV: production
  SLACK_WEBHOOK_URL: https://hooks.slack.com/services/T00000/B00000/XXXXXXXX
```

### Registering custom steps

Place your step classes anywhere autoloadable and register them in `~/.drops/steps.php`:

```php
<?php
// ~/.drops/steps.php
return [
    MyCompany\Drops\Steps\WarmCacheStep::class,
    MyCompany\Drops\Steps\NotifySlackStep::class,
];
```

Custom steps are referenced in application configs by their ID (the string returned by `getId()`), exactly like built-in steps. They run after all built-in steps in their phase.

## Hook scripts

Hook scripts receive these environment variables:

| Variable | Description |
|---|---|
| `DROPS_APP_ID` | Application ID |
| `DROPS_ENV_ID` | Environment ID |
| `DROPS_PHASE` | `export` or `import` |
| `DROPS_WEBROOT` | Absolute path to the Drupal webroot |
| `DROPS_PACKAGE_DIR` | Path to the deployment package directory |
| `DROPS_DRUSH` | Path to the Drush executable |
| `DROPS_URI` | Drupal site URI (only set for multi-site environments) |

Any `env_vars` from the environment config are also exported. Scripts must exit `0` to indicate success.

## Development

```bash
git clone https://github.com/deburca/drops.git
cd drops
composer install

# Run tests
vendor/bin/phpunit

# Static analysis (PHPStan level 8)
vendor/bin/phpstan analyse
```

## License

MIT
