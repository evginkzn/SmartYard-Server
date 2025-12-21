# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SmartYard-Server (RBT) is a fully autonomous server for intercom and video surveillance services. It consists of a PHP backend with a pluggable architecture, a vanilla JavaScript SPA frontend, and Node.js microservices.

## Technology Stack

- **Backend**: PHP 8.1+ with Composer
- **Frontend**: Vanilla JavaScript SPA with AdminLTE v3.2.0
- **Databases**: PostgreSQL (primary), Redis (cache), MongoDB (files), ClickHouse (analytics)
- **VoIP**: Asterisk with Kamailio (optional)
- **Services**: Node.js microservices for events, push notifications, MQTT, device provisioning

## Key Entry Points

| Entry Point | File | Purpose |
|-------------|------|---------|
| Web API | `server/frontend.php` | Frontend REST API |
| Mobile API | `server/mobile.php` | Mobile client API with Bearer auth |
| CLI | `server/cli.php` | Command-line interface |
| Asterisk | `server/asterisk.php` | AMI gateway |
| Internal | `server/internal.php` | Internal services (FRS, LPRS) |
| Webhooks | `server/wh.php` | Webhook handler |

## CLI Commands

```bash
php server/cli.php --init-db              # Initialize/migrate database
php server/cli.php --init-clickhouse-db   # Setup analytics database
php server/cli.php --admin-password=<pw>  # Set admin password
php server/cli.php --reindex              # Rebuild search indexes
php server/cli.php --install-crontabs     # Setup scheduled tasks
php server/cli.php --strip-config         # Convert JSON5 config to JSON
```

## Build Commands

```bash
make all                # Full setup
make get-server-libs    # Install PHP dependencies via Composer
make get-client-libs    # Clone client libraries (AdminLTE, Leaflet, etc.)
make init-server-db     # Initialize database
```

## Architecture

### Backend Plugin System

The backend uses a pluggable architecture. Each backend module in `server/backends/` extends the base class and implements standard CRUD methods:

```php
namespace backends\moduleName {
    class moduleName extends backend {
        public function methodName($params) { }
    }
}
```

Backends are loaded dynamically based on configuration in `server/config/config.json`.

**Key backend categories:**
- `authentication/`, `authorization/` - Auth system
- `users/`, `households/`, `addresses/` - Core data
- `files/mongo`, `memfs/`, `tmpfs/` - Storage backends
- `isdn/`, `mqtt/`, `sip/` - Communication
- `frs/`, `plog/`, `monitoring/` - AI/monitoring

### API Structure

REST API endpoints in `server/api/[module]/` follow this pattern:

```php
namespace api\moduleName {
    class api {
        public static function GET($params) { }
        public static function POST($params) { }
        public static function PUT($params) { }
        public static function DELETE($params) { }
    }
}
```

Response format uses `response($code, $data)` helper from `server/utils/response.php`.

### Frontend Modules

Client modules in `client/modules/[name]/` contain:
- JavaScript logic for UI functionality
- Each module exports functions called by the main `app.js`
- REST client in `client/js/rest.js` handles API communication

### Node.js Services

Located in `server/services/`:
- `event/` - Event processing
- `push/` - Push notifications
- `mqtt/` - MQTT broker integration
- `intercom_provision/` - Device provisioning
- `ami/` - Asterisk Manager Interface
- `sys_exporter/` - Prometheus metrics

## Database

**PostgreSQL migrations** are in `server/data/pgsql/` with 88+ versioned SQL files. The migration system tracks version in the `core_vars` table.

**Key tables:** addresses, houses, cameras, subscribers, domophones, contacts, companies, providers, core_users, core_groups

## Configuration

1. Server config: `server/config/config.json` (copy from `config.sample.json5`)
2. Client config: `client/config/config.json` (copy from `config.sample.json5`)
3. Convert JSON5 to JSON: `php server/cli.php --strip-config`

## Directory Structure

```
server/
├── api/          # 26 REST API endpoint modules
├── backends/     # 39 pluggable backend modules
├── mobile/       # 15 mobile API endpoints
├── services/     # Node.js microservices
├── utils/        # Utility functions (PDOExt, i18n, response, etc.)
├── data/         # Database migrations (pgsql/, clickhouse/)
├── hw/           # Hardware device support
├── internal/     # Internal service APIs
└── kamailio/     # Kamailio configuration

client/
├── modules/      # 23 UI modules
├── js/           # Core JS libraries and utilities
├── css/          # Stylesheets
├── i18n/         # Internationalization
└── lib/          # Third-party libraries (AdminLTE, Leaflet, etc.)

asterisk/         # Asterisk configuration and Lua scripts
install/          # Installation guides (01-99.md)
```

## Important Files

- `server/utils/loader.php` - Backend module loader
- `server/utils/PDOExt.php` - Database wrapper with schema support
- `server/utils/response.php` - API response formatter
- `server/data/install.php` - Database migration runner
- `client/js/app.js` - Main SPA application
- `client/js/rest.js` - REST API client
