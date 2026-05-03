# DROPS — Drupal Remote Operations and Pipeline System

A PHP command-line tool for deploying Drupal websites between environments. Built on Symfony Console, distributed as a Composer package.

## Requirements

- PHP 8.2+
- PHP extensions: `json`, `phar`, `zlib`
- System tools: `rsync`, `gzip`

## Installation

```bash
composer global require drops/drops
```

## Configuration

Configuration lives in `~/.drops/` (or specify with `--config-dir`):

```
~/.drops/
├── environments/
│   ├── production.yml
│   └── staging.yml
└── applications/
    └── acme-corp.yml
```

### Environment example

```yaml
id: production
label: "Production Server"
access:
  type: ssh
  host: prod.example.com
  user: deploy
paths:
  webroot: /var/www/drupal/web
```

### Application example

```yaml
id: acme-corp
label: "ACME Corp Website"
steps:
  database_export: true
  config_export: true
  config_import: true
  database_update: true
  cache_rebuild: true
```

## Usage

```bash
# Export a deployment package
drops export --app=acme-corp --env=production --output=./deploy.tar.gz

# Import a deployment package
drops import --app=acme-corp --env=staging --package=./deploy.tar.gz

# Test connectivity
drops ping --env=production

# Validate configs
drops validate --all

# List environments/applications
drops list:environments
drops list:applications
```

## Development

```bash
composer install
vendor/bin/phpunit          # Run tests
vendor/bin/phpstan analyse  # Static analysis
```

## License

MIT
