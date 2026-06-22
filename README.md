# MSHK Core

A lightweight framework built on Laravel Illuminate components for Discuz Q.

## Features

- Built on Laravel 11 Illuminate components
- PSR-7/PSR-15 HTTP middleware support
- FastRoute for high-performance routing
- JSON API response formatting
- Built-in authentication and authorization
- Event-driven architecture

## Requirements

- PHP 8.2+
- Composer

## Installation

```bash
composer require mshk/core
```

## Quick Start

```php
use Discuz\Foundation\Application;
use Discuz\Http\Server;

// Create application instance
$app = new Application(__DIR__);

// Register HTTP server
$app->singleton(Server::class, Server::class);

// Start the server
$app->make(Server::class)->listen();
```

## Documentation

- [Laravel Upgrade Guide](../docs/laravel-upgrade-summary.md)
- [Package Compatibility](../docs/package-compatibility-analysis.md)

## License

Apache License 2.0 - See [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Author

**MSHK** - your-email@example.com
