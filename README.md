![Laravel FCM Notifications Banner](assets/banner_wide.png)

# Laravel FCM Notifications

[![Latest Version on Packagist](https://img.shields.io/packagist/v/netosts/laravel-fcm-notifications?style=flat-square)](https://packagist.org/packages/netosts/laravel-fcm-notifications)
[![Total Downloads](https://img.shields.io/packagist/dt/netosts/laravel-fcm-notifications?style=flat-square)](https://packagist.org/packages/netosts/laravel-fcm-notifications)
[![License](https://img.shields.io/packagist/l/netosts/laravel-fcm-notifications?style=flat-square)](https://packagist.org/packages/netosts/laravel-fcm-notifications)

A robust and secure Firebase Cloud Messaging (FCM) notification system for Laravel applications. This package provides a comprehensive solution for sending push notifications with automatic token management, cleanup, and support for all FCM message types.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
  - [Firebase Setup](#1-firebase-setup)
  - [Environment Variables](#2-environment-variables)
  - [Database Setup](#3-database-setup)
  - [Token Management](#4-token-management)
- [Usage](#usage)
  - [Basic Usage](#basic-usage)
  - [Direct Service Usage](#direct-service-usage)
  - [Message Types](#message-types)
  - [Batch Sending](#batch-sending)
  - [Platform-Specific Configuration](#platform-specific-configuration)
- [Testing](#testing)
- [Configuration Options](#configuration-options)
- [Troubleshooting](#troubleshooting)
- [Support](#support)

## Features

- 🚀 **Easy Integration** - Drop-in Laravel notification channel
- 🔐 **Secure Authentication** - JWT-based Google OAuth2 authentication
- 📱 **Multiple Message Types** - Support for notification-only, data-only, and combined messages
- 🔄 **Automatic Token Cleanup** - Removes invalid tokens automatically
- 📊 **Batch Sending** - Send to multiple devices efficiently
- 🛠️ **Platform Specific** - Android and iOS specific configurations
- 📝 **Comprehensive Logging** - Detailed logging for debugging
- ⚡ **Performance Optimized** - Token caching and efficient API calls
- 🧪 **Testing Commands** - Built-in commands for testing functionality

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- Firebase project with FCM enabled

## Installation

You can install the package via Composer:

```bash
composer require netosts/laravel-fcm-notifications
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=fcm-notifications-config
```

## Quick Start

Get up and running in minutes:

### 1. Set up your Firebase credentials

Add these environment variables to your `.env` file:

```env
FCM_PROJECT_ID=your-firebase-project-id
FCM_CLIENT_EMAIL=your-service-account@your-project.iam.gserviceaccount.com
FCM_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nYour-Private-Key-Here\n-----END PRIVATE KEY-----"
```

### 2. Add FCM token to your User model

```php
// Add to your users table migration
Schema::table('users', function (Blueprint $table) {
    $table->string('fcm_token')->nullable();
});

// Make it fillable in your User model
class User extends Model
{
    protected $fillable = ['fcm_token'];
}
```

### 3. Send your first notification

```php
use LaravelFcmNotifications\Notifications\FcmNotification;

$notification = new FcmNotification(
    title: 'Welcome!',
    body: 'Thanks for joining our app'
);

$user->notify($notification);
```

That's it! Your notification will be sent to the user's device.

## Configuration

> **💡 Tip:** If you just want to get started quickly, check the [Quick Start](#quick-start) section above.

### 1. Firebase Setup

To use FCM, you need a Firebase project with proper credentials:

1. Go to the [Firebase Console](https://console.firebase.google.com/)
2. Create a new project or select an existing one
3. Navigate to **Project Settings** → **Service Accounts**
4. Click **Generate New Private Key** to download the service account JSON file

### 2. Environment Variables

Add the following variables to your `.env` file:

```env
# Required - Firebase Credentials
FCM_PROJECT_ID=your-firebase-project-id
FCM_CLIENT_EMAIL=your-service-account@your-project.iam.gserviceaccount.com
FCM_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nYour-Private-Key-Here\n-----END PRIVATE KEY-----"

# Optional - API Settings
FCM_TIMEOUT=30
FCM_DEFAULT_MODE=data_only

# Optional - Token Management
FCM_TOKEN_COLUMN=token
FCM_AUTO_CLEANUP_TOKENS=true
```

> **⚠️ Important:** The private key must include `\n` characters for line breaks.

### 3. Database Setup

Choose one of the following approaches for storing FCM tokens:

#### Option A: Multiple Tokens per User (Recommended)

For users with multiple devices, create a dedicated tokens table:

```php
// Create migration: php artisan make:migration create_notification_tokens_table
Schema::create('notification_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('token')->unique();
    $table->string('device_type')->nullable(); // 'android', 'ios', 'web'
    $table->timestamps();
});
```

#### Option B: Single Token per User

Add a token column to your existing users table:

```php
// Add to your users table migration
Schema::table('users', function (Blueprint $table) {
    $table->string('fcm_token')->nullable();
});
```

### 4. Token Management

Configure how the package discovers FCM tokens from your models:

#### For Multiple Tokens (Option A)

```php
class User extends Model
{
    public function notificationTokens()
    {
        return $this->hasMany(NotificationToken::class);
    }
}

// Optional: Create a NotificationToken model
class NotificationToken extends Model
{
    protected $fillable = ['user_id', 'token', 'device_type'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

#### For Single Token (Option B)

```php
class User extends Model
{
    protected $fillable = ['fcm_token'];

    // Optional: Custom method name
    public function getFcmToken()
    {
        return $this->fcm_token;
    }
}
```

## Usage

### Basic Usage

There are two main ways to send FCM notifications:

#### Method 1: Using FcmNotification Directly (Simple)

```php
use LaravelFcmNotifications\Notifications\FcmNotification;

// Simple notification
$notification = new FcmNotification(
    title: 'New Message',
    body: 'You have a new message from John'
);

$user->notify($notification);
```

#### Method 2: Custom Notification Class (Recommended)

Create a custom notification class for better organization:

```bash
php artisan make:notification PushNotification
```

Extend the `FcmNotification` class:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use LaravelFcmNotifications\Notifications\FcmNotification;

class PushNotification extends FcmNotification implements ShouldQueue
{
    use Queueable;

    // Add custom logic here if needed
}
```

Use your custom notification:

```php
use App\Notifications\PushNotification;

$notification = new PushNotification(
    title: 'New Message',
    body: 'You have a new message from John',
    image: 'https://example.com/avatar.jpg',
    data: ['message_id' => '123', 'sender_id' => '456']
);

$user->notify($notification);
```

### Direct Service Usage

For more control, use the FCM service directly:

```php
use LaravelFcmNotifications\Facades\Fcm;
use LaravelFcmNotifications\Services\FcmMessage;

// Create a detailed message
$message = FcmMessage::create(
    title: 'Direct Message',
    body: 'This message was sent directly via the service'
)
->addData('custom_key', 'custom_value')
->addData('user_action', 'view_profile')
->setAndroidPriority('high')
->setIosBadge(1);

// Send to a specific device
$result = Fcm::sendToDevice($deviceToken, $message);

// Handle the result
if ($result['success']) {
    echo "Message sent successfully!";
} else {
    echo "Failed to send: " . $result['error'];
}
```

### Message Types

FCM supports different message types for different use cases:

#### 1. Notification + Data (Default)

Shows a system notification and passes custom data to your app:

```php
$notification = new FcmNotification(
    title: 'New Order',
    body: 'You received a new order #1234',
    data: ['order_id' => '1234', 'action' => 'view_order']
);
```

#### 2. Data Only

Sends data silently to your app without showing a notification:

```php
$notification = (new FcmNotification(
    title: 'Background Sync', // Not shown to user
    body: 'Data updated',     // Not shown to user
    data: ['sync' => 'true', 'timestamp' => time()]
))->dataOnly();
```

#### 3. Notification Only

Shows only a system notification without custom data:

```php
$notification = (new FcmNotification(
    title: 'System Maintenance',
    body: 'Our systems will be down for maintenance tonight'
))->notificationOnly();
```

### Batch Sending

Send the same message to multiple devices efficiently:

```php
$deviceTokens = ['token1', 'token2', 'token3'];

$message = FcmMessage::create(
    title: 'System Announcement',
    body: 'Important update for all users'
);

$result = Fcm::sendToMultipleDevices($deviceTokens, $message);

// Check results
echo "Successfully sent to: {$result['summary']['success']} devices\n";
echo "Failed to send to: {$result['summary']['failure']} devices\n";

// Handle individual failures
foreach ($result['details'] as $detail) {
    if (!$detail['success']) {
        echo "Failed for token: {$detail['token']}, Error: {$detail['error']}\n";
    }
}
```

### Platform-Specific Configuration

Customize notifications for different platforms:

#### Android Specific Settings

```php
$message = FcmMessage::create('Android Notification', 'Optimized for Android devices')
    ->setAndroidChannel('important_notifications')
    ->setAndroidPriority('high')
    ->setAndroidSound('custom_sound.mp3')
    ->addData('android_specific', 'value');

$user->notify($notification);
```

#### iOS Specific Settings

```php
$message = FcmMessage::create('iOS Notification', 'Optimized for iOS devices')
    ->setIosBadge(5)                    // Badge count
    ->setIosSound('custom_sound.caf')   // Custom sound
    ->addData('ios_specific', 'value');

$user->notify($notification);
```

#### Cross-Platform Message

```php
$message = FcmMessage::create('Universal Message', 'Works on all platforms')
    // Android settings
    ->setAndroidPriority('high')
    ->setAndroidChannel('notifications')

    // iOS settings
    ->setIosBadge(1)
    ->setIosSound('default')

    // Common data
    ->addData('universal_data', 'value');
```

### Event Listeners

The package automatically handles token cleanup through events:

```php
// In your EventServiceProvider
protected $listen = [
    \LaravelFcmNotifications\Events\UnregisteredFcmTokenDetected::class => [
        \LaravelFcmNotifications\Listeners\CleanupUnregisteredFcmToken::class,
    ],
];
```

When an invalid token is detected, it's automatically removed from your database (if `FCM_AUTO_CLEANUP_TOKENS=true`).

## Testing

### Built-in Test Commands

The package includes helpful commands for testing your FCM integration:

```bash
# Test basic FCM functionality with a real device token
php artisan fcm:test --token=your-actual-device-token

# Test the direct service (bypasses Laravel notifications)
php artisan fcm:test --token=your-device-token --direct

# Test token cleanup functionality
php artisan fcm:test-cleanup your-device-token
```

### Token Validation

Validate FCM tokens before sending notifications:

```php
use LaravelFcmNotifications\Facades\Fcm;

// Validate a single token
$validation = Fcm::validateToken($deviceToken);

if ($validation['valid']) {
    echo "Token is valid and ready to receive notifications";
} else {
    echo "Token is invalid: " . $validation['message'];
    // Error type: $validation['error_type']
}

// Validate multiple tokens at once
$tokens = ['token1', 'token2', 'token3'];
$results = Fcm::validateTokens($tokens);

$validCount = 0;
$invalidCount = 0;

foreach ($results as $result) {
    if ($result['valid']) {
        $validCount++;
    } else {
        $invalidCount++;
        // Remove invalid token from database
        NotificationToken::where('token', $result['token'])->delete();
    }
}

echo "Valid tokens: {$validCount}\n";
echo "Invalid tokens: {$invalidCount}\n";
```

### Testing in Development

```php
// Only send notifications in production
if (app()->environment('production')) {
    $user->notify(new PushNotification('Production Alert', 'This is live!'));
} else {
    // Log instead of sending during development
    Log::info('Would send notification: Production Alert');
}
```

## Configuration Options

The `config/fcm-notifications.php` file provides comprehensive configuration options:

```php
return [
    // Firebase Credentials (Required)
    'project_id' => env('FCM_PROJECT_ID'),
    'client_email' => env('FCM_CLIENT_EMAIL'),
    'private_key' => env('FCM_PRIVATE_KEY'),

    // API Settings
    'timeout' => env('FCM_TIMEOUT', 30), // Request timeout in seconds

    // Message Behavior
    'default_mode' => env('FCM_DEFAULT_MODE', 'data_only'), // 'notification_only', 'data_only', 'both'

    // Token Management
    'token_column' => env('FCM_TOKEN_COLUMN', 'token'),              // Column name for single token storage
    'auto_cleanup_tokens' => env('FCM_AUTO_CLEANUP_TOKENS', true),   // Auto-remove invalid tokens

    // Performance Settings
    'cache_token' => true,                              // Cache JWT tokens
    'cache_prefix' => 'fcm_notifications_token',        // Cache key prefix
];
```

### Configuration Details

| Option                | Description                     | Default     |
| --------------------- | ------------------------------- | ----------- |
| `project_id`          | Your Firebase project ID        | Required    |
| `client_email`        | Service account email           | Required    |
| `private_key`         | Service account private key     | Required    |
| `timeout`             | HTTP request timeout (seconds)  | 30          |
| `default_mode`        | Default message type            | `data_only` |
| `token_column`        | Single token column name        | `token`     |
| `auto_cleanup_tokens` | Auto-remove invalid tokens      | `true`      |
| `cache_token`         | Cache JWT authentication tokens | `true`      |

## Troubleshooting

### Common Issues

#### 🔐 Authentication Failed

**Symptoms:** Authentication errors, 401 responses from FCM

**Solutions:**

- Verify your Firebase service account credentials in `.env`
- Ensure the private key includes proper line breaks (`\n`)
- Check that your service account has FCM permissions
- Validate your `project_id` matches your Firebase project

```bash
# Test your credentials
php artisan fcm:test --token=test-token
```

#### 🔍 Tokens Not Found

**Symptoms:** No notifications sent, "no tokens found" errors

**Solutions:**

- Check your token storage implementation
- Verify the `token_column` configuration matches your database
- Ensure users have FCM tokens stored
- Check relationship methods in your User model

```php
// Debug token discovery
$user = User::find(1);
dd($user->notificationTokens); // For multiple tokens
dd($user->fcm_token);          // For single token
```

#### 📱 Messages Not Received

**Symptoms:** API calls succeed but notifications don't appear

**Solutions:**

- Test with the built-in test command
- Check FCM token validity
- Verify your mobile app is properly configured for FCM
- Check device notification settings
- Ensure app is not in battery optimization mode (Android)

```bash
# Validate your tokens
php artisan tinker
>>> $result = Fcm::validateToken('your-device-token');
>>> dd($result); // Shows detailed validation results
```

#### ⚡ Performance Issues

**Symptoms:** Slow notification sending, timeouts

**Solutions:**

- Enable token caching in config
- Use batch sending for multiple recipients
- Implement queue processing for large volumes
- Increase timeout setting if needed

```php
// Use queued notifications for better performance
class PushNotification extends FcmNotification implements ShouldQueue
{
    use Queueable;
}
```

### Debug Mode

Enable detailed logging for troubleshooting:

```env
# In your .env file
LOG_LEVEL=debug
APP_DEBUG=true
```

This will log detailed FCM request/response information to help diagnose issues.

### Getting Help

If you're still having issues:

1. **Check the logs** - Look in `storage/logs/laravel.log` for detailed error messages
2. **Test with the built-in commands** - Use `php artisan fcm:test` to isolate issues
3. **Validate your setup** - Double-check Firebase credentials and configuration
4. **Review the FCM documentation** - Some issues may be Firebase-specific

---

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security

If you discover any security-related issues, please email **netostt91@gmail.com** instead of using the issue tracker.

## Credits

- [Neto Santos](https://github.com/netosts) - Creator and maintainer
- [All Contributors](../../contributors) - Community contributors

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Support

Need help? Here's how to get support:

- 📧 **Email:** [netostt91@gmail.com](mailto:netostt91@gmail.com)
- 🐛 **Bug Reports:** [GitHub Issues](https://github.com/netosts/laravel-fcm-notifications/issues)
- 💬 **Questions & Discussions:** [GitHub Discussions](https://github.com/netosts/laravel-fcm-notifications/discussions)
- 📖 **Documentation:** This README and inline code documentation

---

<div align="center">

**⭐ If this package helped you, please consider giving it a star on GitHub! ⭐**

</div>
