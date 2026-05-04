# DROPS — Design & Scope Document

**Project:** DROPS — Drupal Remote Operations and Pipeline System  
**Document Version:** 1.1  
**Status:** Draft  
**Scope:** Architecture, configuration schema, CLI interface, project structure, and operational design

---

## 1. Overview

DROPS is a PHP command-line tool for deploying Drupal websites between environments. It is built on the Symfony Console component, distributed as a standalone Composer package, and designed to align naturally with the Drupal developer ecosystem.

The tool is organised around two core abstractions:

- **Environments** — where code and data live (local filesystem, remote SSH, etc.)
- **Applications** — what needs to happen to a Drupal site during a deployment (database updates, cache clears, config export/import, file syncing, etc.)

A deployment is modelled as a two-phase operation:

1. **Export phase** — run on the *source* environment to produce a portable deployment package
2. **Import phase** — run on the *target* environment to apply the deployment package

This two-phase design means DROPS does not need a persistent connection between source and target simultaneously. The package can be transferred by any means (scp, SFTP, shared NFS, S3, USB key, etc.).

---

## 2. Goals & Non-Goals

### Goals

- Decouple environment access strategies from application-level deployment logic
- Support any combination of local and remote environments
- Allow per-application configuration of which deployment steps are required
- Produce auditable, re-runnable deployment packages
- Be operable entirely from the command line, suitable for use in CI/CD pipelines
- Remain Drupal-version-agnostic (Drupal 8, 9, 10, 11)
- Be installable and familiar to any Drupal developer via Composer

### Non-Goals

- DROPS does not manage code deployments (git pull/push, Composer, etc.) — it assumes code is already in place on the target before the import phase
- DROPS does not manage web server or PHP configuration
- DROPS does not replace Drush — it orchestrates Drush commands
- DROPS does not provide a GUI

---

## 3. Core Concepts

### 3.1 Environment

An environment describes *how* to reach a machine and *where* the Drupal site lives on it. Environments are independent of any specific application.

**An environment has:**
- A unique identifier (e.g. `production`, `staging`, `local-dev`)
- An access strategy (local filesystem or SSH)
- A working directory (path to the Drupal webroot on that environment)
- Optionally, environment-specific overrides (PHP binary path, Drush path, temp directory)

### 3.2 Application

An application describes a specific Drupal site and what deployment operations it requires. An application is environment-agnostic — it defines *what* to do, not *where*.

**An application has:**
- A unique identifier (e.g. `acme-corp`, `staff-intranet`)
- A list of enabled deployment steps (database update, cache rebuild, config export/import, file sync, etc.)
- Step-specific configuration (e.g. config export directory, which file directories to sync)
- Optional pre- and post-deployment hook scripts

### 3.3 Deployment Package

A deployment package is a directory (typically archived as a `.tar.gz`) produced by the export phase. It contains structured data and metadata that the import phase consumes. The package is self-describing — it records which application and source environment produced it.

**A package contains:**
- `manifest.json` — metadata (application ID, source environment, timestamp, tool version, enabled steps)
- `database/` — database dump (if the `database` step is enabled)
- `config/` — exported Drupal configuration (if the `config` step is enabled)
- `files/` — public/private file assets (if the `files` step is enabled)
- `hooks/` — copies of any hook scripts defined in the application config

### 3.4 Deployment Step

A deployment step is a discrete unit of work that can be enabled or disabled per application. Steps run in a defined order on export and a corresponding order on import.

---

## 4. Built-in Deployment Steps

Steps are executed in the order listed. Each step declares whether it runs on export, import, or both.

| Step ID | Phase | Description |
|---|---|---|
| `pre_hooks` | Both | Run user-defined scripts before all other steps |
| `maintenance_on` | Import | Enable Drupal maintenance mode |
| `database_export` | Export | Dump the source database to the package |
| `files_export` | Export | Archive file assets from the source to the package |
| `config_export` | Export | Run `drush config:export` and capture output into the package |
| `config_import` | Import | Run `drush config:import` from the package config directory |
| `files_import` | Import | Restore file assets from the package to the target |
| `database_import` | Import | Restore the database dump to the target database |
| `database_update` | Import | Run `drush updatedb` on the target |
| `cache_rebuild` | Import | Run `drush cache:rebuild` on the target |
| `maintenance_off` | Import | Disable Drupal maintenance mode |
| `post_hooks` | Both | Run user-defined scripts after all other steps |

Each step is independently enabled/disabled in the application configuration. The tool enforces logical consistency: if `config_import` is enabled, `config_export` must also be enabled.

---

## 5. Configuration Schema

All configuration lives in a single directory (default: `~/.drops/` or a path specified with `--config-dir`). Configuration files are written in YAML.

### 5.1 Directory Layout

```
~/.drops/
├── environments/
│   ├── production.yml
│   ├── staging.yml
│   └── local-dev.yml
└── applications/
    ├── acme-corp.yml
    └── staff-intranet.yml
```

### 5.2 Environment Configuration

**File:** `environments/<environment-id>.yml`

```yaml
# environments/production.yml
id: production
label: "Production Server"

access:
  type: ssh                          # "local" or "ssh"
  host: prod.example.com
  port: 22                           # Optional; defaults to 22
  user: deploy
  identity_file: ~/.ssh/id_ed25519   # Optional; omit to use SSH agent

paths:
  webroot: /var/www/drupal/web
  drush: /var/www/drupal/vendor/bin/drush   # Optional; defaults to "drush" on PATH
  php: /usr/bin/php8.2                       # Optional; defaults to "php" on PATH
  temp: /tmp/drops                           # Optional; defaults to system temp

# Optional environment-level variables passed to all hook scripts
env_vars:
  APP_ENV: production
```

```yaml
# environments/local-dev.yml
id: local-dev
label: "Local Development"

access:
  type: local

paths:
  webroot: /home/alice/projects/acme/web
  drush: /home/alice/projects/acme/vendor/bin/drush

env_vars:
  APP_ENV: development
```

**Supported `access.type` values:**

| Value | Description |
|---|---|
| `local` | Direct filesystem access; no remote connection required |
| `ssh` | Connects via SSH; all commands are executed over the SSH session |

### 5.3 Application Configuration

**File:** `applications/<application-id>.yml`

```yaml
# applications/acme-corp.yml
id: acme-corp
label: "ACME Corp Website"

# Which steps are enabled for this application
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

# Step-specific configuration
step_config:

  database_export:
    # Tables to export structure-only (no data) — useful for cache/log tables
    skip_data_tables:
      - cache
      - cache_*            # Glob patterns supported
      - watchdog
      - sessions

  config_export:
    # Path relative to webroot where config lives (default: ../config/sync)
    sync_dir: ../config/sync

  config_import:
    sync_dir: ../config/sync

  files_export:
    # List of directories relative to webroot/sites/default/ to include
    directories:
      - files/public
    # Exclude patterns (rsync-style)
    exclude:
      - "*.log"
      - ".DS_Store"
      - "styles/"       # Derived image styles — regenerated automatically

  files_import:
    directories:
      - files/public
    # Whether to delete files on target that don't exist in the package
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

**Minimal application** — a site that only needs cache cleared and database updates:

```yaml
# applications/staff-intranet.yml
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

---

## 6. CLI Interface

DROPS is invoked as `drops` after installation. The CLI is built on **Symfony Console**, giving consistent argument parsing, help text, and output formatting.

### 6.1 Global Options

```
--config-dir=PATH    Path to config directory (default: ~/.drops)
--log-level=LEVEL    Verbosity: quiet|normal|verbose|debug (default: normal)
--dry-run            Print what would happen without executing anything
--no-ansi            Disable terminal colour output
--help               Show help for a command
--version            Show DROPS version
```

### 6.2 Commands

#### `drops export`

Run the export phase on a source environment. Produces a deployment package.

```
drops export
  --app=APP-ID           Application identifier (required)
  --env=ENV-ID           Environment identifier (required)
  --output=PATH          Path for the output package .tar.gz (required)
  [--label=TEXT]         Optional human-readable label for this deployment
  [--steps=STEP,STEP]    Override which steps run (comma-separated)
  [--skip-steps=STEP]    Skip specific steps without changing config
```

**Example:**

```bash
drops export \
  --app=acme-corp \
  --env=production \
  --output=./packages/acme-$(date +%Y%m%d-%H%M%S).tar.gz
```

#### `drops import`

Run the import phase on a target environment, consuming a deployment package.

```
drops import
  --app=APP-ID           Application identifier (required)
  --env=ENV-ID           Environment identifier (required)
  --package=PATH         Path to the .tar.gz package (required)
  [--steps=STEP,STEP]    Override which steps run
  [--skip-steps=STEP]    Skip specific steps
  [--no-maintenance]     Skip maintenance mode even if configured
```

**Example:**

```bash
drops import \
  --app=acme-corp \
  --env=staging \
  --package=./packages/acme-20250503-141500.tar.gz
```

#### `drops list:environments`

List all configured environments with their access type and webroot path.

#### `drops list:applications`

List all configured applications with their enabled steps.

#### `drops validate`

Validate one or all configuration files without running a deployment.

```
drops validate --app=APP-ID --env=ENV-ID
drops validate --all
```

#### `drops ping`

Test connectivity to an environment and verify PHP and Drush are reachable.

```
drops ping --env=ENV-ID
```

---

## 7. Deployment Package Format

### 7.1 Directory Structure

```
acme-deploy-20250503-141500/
├── manifest.json
├── database/
│   └── dump.sql.gz
├── config/
│   ├── core.extension.yml
│   ├── system.site.yml
│   └── ...
├── files/
│   └── public/
│       └── images/
└── hooks/
    ├── pre-export.sh
    ├── post-export.sh
    ├── pre-import.sh
    └── post-import.sh
```

### 7.2 `manifest.json` Schema

```json
{
  "tool": "drops",
  "tool_version": "1.0.0",
  "schema_version": 1,
  "created_at": "2025-05-03T14:15:00Z",
  "label": "Pre-launch content freeze",
  "application": {
    "id": "acme-corp",
    "label": "ACME Corp Website"
  },
  "source_environment": {
    "id": "production",
    "label": "Production Server",
    "access_type": "ssh",
    "host": "prod.example.com"
  },
  "steps_included": [
    "database_export",
    "files_export",
    "config_export"
  ],
  "checksums": {
    "database/dump.sql.gz": "sha256:abcdef1234...",
    "config/system.site.yml": "sha256:fedcba5678..."
  }
}
```

Checksums are verified by the import phase before any changes are made to the target.

---

## 8. Execution Model & Error Handling

### 8.1 Step Execution

Each step is a self-contained unit. The tool tracks step state: `pending`, `running`, `complete`, `failed`, `skipped`.

If a step fails, the tool stops by default and reports the failed step. Steps completed before the failure are not automatically rolled back — the operator is responsible for restoring the target if needed. This is a deliberate design choice to avoid cascading rollback failures.

A `--continue-on-error` flag may be passed to allow subsequent steps to run despite a failure.

### 8.2 Rollback Considerations

When `import_options.create_rollback_package` is enabled in the application config, DROPS automatically exports the target's current state before making any changes. This rollback package can be fed back into `drops import` to undo the deployment.

### 8.3 Remote Command Execution

For SSH environments, all Drush commands are executed via a persistent SSH connection (using SSH multiplexing where available). File transfers use `rsync` over SSH for `files_export`/`files_import` steps, and piped SSH streams for database dumps.

For local environments, commands are executed directly via Symfony Process.

---

## 9. Hook Scripts

Hook scripts are shell scripts executed at defined points in the deployment. They receive the following environment variables:

| Variable | Description |
|---|---|
| `DROPS_APP_ID` | Application ID |
| `DROPS_ENV_ID` | Environment ID |
| `DROPS_PHASE` | `export` or `import` |
| `DROPS_WEBROOT` | Absolute path to the Drupal webroot on the current environment |
| `DROPS_PACKAGE_DIR` | Path to the deployment package directory |
| `DROPS_DRUSH` | Path to the Drush executable |
| `DROPS_URI` | Drupal site URI (only set for multi-site environments) |

Any `env_vars` defined in the environment config are also exported to the script.

Hook scripts must exit with status `0` to indicate success. Any non-zero exit code aborts the deployment.

**Example — notify Slack on import:**

```bash
#!/bin/bash
# hooks/post-import.sh
curl -s -X POST "$SLACK_WEBHOOK_URL" \
  -H 'Content-type: application/json' \
  --data "{\"text\": \"✅ ${DROPS_APP_ID} deployed to ${DROPS_ENV_ID}\"}"
```

---

## 10. Typical Workflows

### 10.1 Production → Staging Full Sync

```bash
# Step 1: Export from production
drops export \
  --app=acme-corp \
  --env=production \
  --output=/tmp/acme-$(date +%Y%m%d).tar.gz

# Transfer the package to staging
scp /tmp/acme-20250503.tar.gz deploy@staging.example.com:/tmp/

# Step 2: Import on staging
drops import \
  --app=acme-corp \
  --env=staging \
  --package=/tmp/acme-20250503.tar.gz
```

### 10.2 Config-Only Deployment

```bash
drops export --app=acme-corp --env=production --output=./deploy-config.tar.gz
drops import --app=acme-corp --env=staging   --package=./deploy-config.tar.gz
```

### 10.3 Dry Run Verification

```bash
drops import \
  --app=acme-corp \
  --env=production \
  --package=./deploy.tar.gz \
  --dry-run
```

### 10.4 Selective Step Override

```bash
drops import \
  --app=acme-corp \
  --env=staging \
  --package=./deploy.tar.gz \
  --steps=database_update,cache_rebuild
```

---

## 11. Implementation

### 11.1 Language & Core Dependencies

DROPS is implemented in **PHP 8.2+** and distributed as a Composer package. This aligns with the Drupal developer ecosystem: the same language, the same package manager, the same runtime already present on every machine where DROPS will run.

| Package | Purpose |
|---|---|
| `symfony/console` | CLI framework — commands, input/output, formatting |
| `symfony/process` | Local subprocess execution |
| `symfony/yaml` | YAML configuration parsing |
| `symfony/filesystem` | Cross-platform file operations |
| `phpseclib/phpseclib` | Pure-PHP SSH2 and SFTP client (no ext-ssh2 required) |
| `justinrainbow/json-schema` | Validate manifest and config files against JSON Schema |
| `phar-io/phive` | (dev) Used to build the distributable PHAR |

DROPS is distributed both as a Composer package (`composer global require drops/drops`) and as a self-contained PHAR binary (`drops.phar`) for environments where a global Composer install is not desirable.

### 11.2 Symfony Console Integration

Each DROPS CLI command maps to a Symfony Console `Command` class. The base `DropsCommand` class handles shared concerns (config loading, environment resolution, output styling) so individual command classes stay focused on their logic.

```php
// Example command class structure
class ExportCommand extends DropsCommand
{
    protected static $defaultName = 'export';

    protected function configure(): void
    {
        $this
            ->setDescription('Export a deployment package from a source environment')
            ->addOption('app',   null, InputOption::VALUE_REQUIRED, 'Application ID')
            ->addOption('env',   null, InputOption::VALUE_REQUIRED, 'Environment ID')
            ->addOption('output',null, InputOption::VALUE_REQUIRED, 'Output package path')
            ->addOption('label', null, InputOption::VALUE_OPTIONAL, 'Human-readable label')
            ->addOption('steps', null, InputOption::VALUE_OPTIONAL, 'Override steps (comma-separated)')
            ->addOption('skip-steps', null, InputOption::VALUE_OPTIONAL, 'Steps to skip')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without executing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Resolved via DropsCommand base class
        $app = $this->resolveApplication($input->getOption('app'));
        $env = $this->resolveEnvironment($input->getOption('env'));

        $pipeline = new ExportPipeline($app, $env, $this->buildOptions($input));
        return $pipeline->run(new SymfonyOutputAdapter($output));
    }
}
```

Symfony Console provides out-of-the-box: `--help` generation, `--no-ansi` support, exit code conventions, and the `ProgressBar` and `Table` helpers used for step-progress display.

### 11.3 Extensibility — Custom Steps

DROPS supports custom steps via a simple interface. A custom step is a PHP class implementing `StepInterface`, placed anywhere autoloadable, and registered in `~/.drops/steps.php`:

```php
// src/Step/StepInterface.php
interface StepInterface
{
    public function getId(): string;
    public function getLabel(): string;
    public function getPhase(): Phase;  // Phase::EXPORT | Phase::IMPORT | Phase::BOTH
    public function run(DeployContext $context): StepResult;
}
```

```php
// ~/.drops/steps.php — register custom steps
return [
    MyCompany\Drops\Steps\WarmCacheStep::class,
    MyCompany\Drops\Steps\NotifyPagerDutyStep::class,
];
```

Custom steps are referenced in application configs by their ID, just like built-in steps.

### 11.4 Security Considerations

- SSH private keys are never written to the deployment package
- Database dumps may contain sensitive data — packages should be treated as confidential
- Hook scripts must be executable and owned by the invoking user; DROPS refuses to run scripts writable by other users
- DROPS does not store credentials — SSH agents and key files are referenced directly
- The PHAR binary is signed; signature verification is recommended in CI environments

---

## 12. Project Structure

```
drops/
│
├── bin/
│   └── drops                        # CLI entry point (bootstraps Symfony Console Application)
│
├── config/
│   └── schema/
│       ├── environment.schema.json  # JSON Schema for environment config validation
│       └── application.schema.json  # JSON Schema for application config validation
│
├── src/
│   ├── Application.php              # Symfony Console Application bootstrap
│   │
│   ├── Command/                     # One class per CLI command
│   │   ├── DropsCommand.php         # Abstract base — config loading, env resolution, output
│   │   ├── ExportCommand.php        # drops export
│   │   ├── ImportCommand.php        # drops import
│   │   ├── ValidateCommand.php      # drops validate
│   │   ├── PingCommand.php          # drops ping
│   │   ├── ListEnvironmentsCommand.php
│   │   └── ListApplicationsCommand.php
│   │
│   ├── Config/                      # Configuration loading and validation
│   │   ├── ConfigLoader.php         # Discovers and loads YAML files from config dir
│   │   ├── ConfigValidator.php      # Validates configs against JSON Schema
│   │   ├── EnvironmentConfig.php    # Typed value object for an environment
│   │   └── ApplicationConfig.php   # Typed value object for an application
│   │
│   ├── Environment/                 # Environment access strategies
│   │   ├── EnvironmentInterface.php # Contract: execute(), upload(), download(), exists()
│   │   ├── LocalEnvironment.php     # Implements via Symfony Process + local filesystem
│   │   ├── SshEnvironment.php       # Implements via phpseclib SSH2 + SFTP
│   │   └── EnvironmentFactory.php   # Instantiates the correct class from config
│   │
│   ├── Pipeline/                    # Orchestration layer
│   │   ├── ExportPipeline.php       # Resolves and runs export-phase steps in order
│   │   ├── ImportPipeline.php       # Resolves and runs import-phase steps in order
│   │   ├── DeployContext.php        # Shared state passed to every step during a run
│   │   └── StepResult.php           # Outcome of a single step (success/failure/skipped + log)
│   │
│   ├── Step/                        # Built-in deployment steps
│   │   ├── StepInterface.php        # getId(), getLabel(), getPhase(), run(DeployContext)
│   │   ├── StepRegistry.php         # Discovers built-in and custom steps
│   │   ├── Phase.php                # Enum: EXPORT, IMPORT, BOTH
│   │   │
│   │   ├── PreHooksStep.php
│   │   ├── MaintenanceOnStep.php
│   │   ├── DatabaseExportStep.php
│   │   ├── FilesExportStep.php
│   │   ├── ConfigExportStep.php
│   │   ├── ConfigImportStep.php
│   │   ├── FilesImportStep.php
│   │   ├── DatabaseImportStep.php
│   │   ├── DatabaseUpdateStep.php
│   │   ├── CacheRebuildStep.php
│   │   ├── MaintenanceOffStep.php
│   │   └── PostHooksStep.php
│   │
│   ├── Package/                     # Deployment package creation and reading
│   │   ├── PackageBuilder.php       # Assembles the package directory during export
│   │   ├── PackageReader.php        # Opens and validates a package during import
│   │   ├── Manifest.php             # Typed representation of manifest.json
│   │   └── ManifestWriter.php       # Serialises Manifest to manifest.json
│   │
│   └── Output/                      # Terminal output helpers
│       ├── StepProgressRenderer.php # Renders a live step-by-step progress table
│       └── SummaryRenderer.php      # Renders the final pass/fail summary
│
├── tests/
│   ├── Unit/
│   │   ├── Config/
│   │   ├── Pipeline/
│   │   └── Step/
│   └── Integration/
│       ├── LocalEnvironmentTest.php
│       └── PackageRoundTripTest.php
│
├── composer.json
├── phpstan.neon                     # Static analysis (PHPStan level 8)
├── phpcs.xml                        # Coding standards (Drupal coding standards)
├── phpunit.xml
└── README.md
```

### Key Structural Decisions

**`Environment/` is the only layer that knows about SSH or local filesystem.** Every step works exclusively through `EnvironmentInterface`, so adding a new access strategy (e.g. Docker exec, Kubernetes pod exec) requires only a new class in `Environment/` with no changes to any step.

**`Pipeline/` owns step ordering and error handling.** Steps are pure units of work. They do not decide what to do if they fail, and they do not know about other steps. The pipeline handles sequencing, dry-run short-circuiting, `--continue-on-error` logic, and rollback package creation.

**`Config/` produces typed value objects.** Nothing outside `Config/` works with raw arrays or YAML strings. `EnvironmentConfig` and `ApplicationConfig` are immutable value objects, making the rest of the codebase straightforward to statically analyse.

**`Command/` is thin.** Commands parse input, resolve configs and environments, hand off to a pipeline, and return an exit code. No business logic lives in a command class.

---

## 13. Installation & Distribution

### Via Composer (recommended for Drupal developers)

```bash
composer global require drops/drops
```

Adds `drops` to `~/.composer/vendor/bin/drops`. If that directory is on `$PATH`, `drops` is immediately available.

### Via PHAR (recommended for CI/CD environments)

```bash
curl -OL https://github.com/drops-project/drops/releases/latest/download/drops.phar
chmod +x drops.phar
mv drops.phar /usr/local/bin/drops
```

### Requirements

| Requirement | Version |
|---|---|
| PHP | 8.2 or higher |
| PHP extensions | `json`, `phar`, `zlib` |
| System tools | `rsync` (for file transfer steps), `gzip` (for database dump compression) |
| SSH | SSH agent or key file on the machine running DROPS; no SSH client required on the target |

---

## 14. Configuration Reference Summary

### Environment Config Fields

| Field | Required | Type | Description |
|---|---|---|---|
| `id` | Yes | string | Unique identifier |
| `label` | No | string | Human-readable name |
| `access.type` | Yes | `local` \| `ssh` | Access strategy |
| `access.host` | SSH only | string | Hostname or IP |
| `access.port` | No | integer | SSH port (default: 22) |
| `access.user` | SSH only | string | SSH username |
| `access.identity_file` | No | string | Path to SSH private key |
| `paths.webroot` | Yes | string | Absolute path to Drupal webroot |
| `paths.drush` | No | string | Path to Drush (default: `drush`) |
| `paths.php` | No | string | Path to PHP binary |
| `paths.temp` | No | string | Temp directory for staging files |
| `env_vars` | No | map | Extra env vars for hook scripts |
| `uri` | No | string | Drupal site URI for multi-site installs. Appends `--uri` to Drush commands and targets `sites/<uri>/` for file operations |

### Application Config Fields

| Field | Required | Type | Description |
|---|---|---|---|
| `id` | Yes | string | Unique identifier |
| `label` | No | string | Human-readable name |
| `steps.*` | Yes | boolean | Enable/disable each step |
| `step_config.database_export.skip_data_tables` | No | list | Tables to dump structure-only |
| `step_config.config_export.sync_dir` | No | string | Config sync dir (relative to webroot) |
| `step_config.files_export.directories` | No | list | File dirs to include in export |
| `step_config.files_export.exclude` | No | list | Rsync-style exclude patterns |
| `step_config.files_import.delete_removed` | No | boolean | Delete target files absent from package |
| `step_config.pre_hooks.export_scripts` | No | list | Scripts to run before export |
| `step_config.pre_hooks.import_scripts` | No | list | Scripts to run before import |
| `step_config.post_hooks.export_scripts` | No | list | Scripts to run after export |
| `step_config.post_hooks.import_scripts` | No | list | Scripts to run after import |
| `import_options.create_rollback_package` | No | boolean | Auto-create rollback package |
| `import_options.rollback_package_dir` | No | string | Where to store rollback packages |

---

## 15. Out-of-Scope & Future Extensions

The following items are noted as future extension points but are not part of the initial build:

- **S3/cloud storage** as a package transport layer
- **Slack/email notifications** (currently achievable via post-deployment hooks)
- **Multi-site Drupal** — per-subsite step configuration (currently each site requires its own application config; a future `sites` list could allow one application to deploy across all subsites in a single run)
- **Environment-to-environment streaming** — bypassing the two-phase model by piping export directly to import over SSH in a single invocation
- **Web UI** for triggering deployments and viewing history
- **Package signing** — GPG signatures on packages for verification in high-security environments
- **Composer/code deployment** integration — triggering `composer install` and git operations as additional step types
- **Drush integration** — exposing DROPS commands as Drush sub-commands for teams who want to stay inside the Drush CLI

---

*End of document. DROPS v1.0 — May 2025.*
