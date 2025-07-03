# Changelog

All notable changes to `laravel-fcm-notifications` will be documented in this file.

## [1.0.0] - 2025-07-03

### Added

- Initial release of Laravel FCM Notifications package
- Complete Firebase Cloud Messaging integration for Laravel
- Support for all FCM message types (notification-only, data-only, combined)
- Automatic JWT-based authentication with Google FCM API
- Comprehensive token management and automatic cleanup
- Batch message sending capabilities
- Platform-specific configurations for Android and iOS
- Event-driven architecture for token cleanup
- Built-in testing commands for development
- Comprehensive error handling and logging
- Laravel notification channel integration
- Facade for direct service access
- Token validation utilities
- Queue support for asynchronous processing
- Automatic token discovery from models
- Configurable caching for OAuth tokens
- MIT license for open-source usage

### Features

- **FcmService**: Core service for FCM API communication
- **FcmMessage**: Fluent message builder with platform-specific options
- **FcmChannel**: Laravel notification channel for seamless integration
- **FcmNotification**: Base notification class with multiple modes
- **Fcm Facade**: Convenient facade for direct service access
- **Event System**: UnregisteredFcmTokenDetected event with cleanup listener
- **Console Commands**: Testing commands for development and debugging
- **Configuration**: Comprehensive configuration with environment variable support

### Security

- Secure JWT token generation and signing
- Token masking in logs for privacy
- OAuth2 token caching with expiration
- Input validation and sanitization
- Secure error handling without information leakage

### Performance

- Token caching to reduce API calls
- Batch sending for multiple devices
- Efficient error handling and retry logic
- Optimized database queries for token management
- Asynchronous processing support via Laravel queues

### Developer Experience

- Comprehensive documentation and examples
- Built-in testing commands
- Detailed error messages and logging
- Type hints and PHPDoc annotations
- Laravel package auto-discovery
- Flexible configuration options
- Migration guide from other FCM packages

### Compatibility

- PHP 8.1+ support
- Laravel 10.0+ support
- Firebase FCM v1 API
- Android and iOS platform support
- MySQL, PostgreSQL, SQLite database support
