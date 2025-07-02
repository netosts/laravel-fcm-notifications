# Laravel FCM Notifications

[![Latest Version on Packagist](https://img.shields.io/packagist/v/netosts/laravel-fcm-notifications.svg?style=flat-square)](https://packagist.org/packages/netosts/laravel-fcm-notifications)
[![Total Downloads](https://img.shields.io/packagist/dt/netosts/laravel-fcm-notifications.svg?style=flat-square)](https://packagist.org/packages/netosts/laravel-fcm-notifications)
[![License](https://img.shields.io/packagist/l/netosts/laravel-fcm-notifications.svg?style=flat-square)](https://packagist.org/packages/netosts/laravel-fcm-notifications)

A robust and secure Firebase Cloud Messaging (FCM) notification system for Laravel applications. This package provides a comprehensive solution for sending push notifications with automatic token management, cleanup, and support for all FCM message types.

## Features

-   🚀 **Easy Integration** - Drop-in Laravel notification channel
-   🔐 **Secure Authentication** - JWT-based Google OAuth2 authentication
-   📱 **Multiple Message Types** - Support for notification-only, data-only, and combined messages
-   🔄 **Automatic Token Cleanup** - Removes invalid tokens automatically
-   📊 **Batch Sending** - Send to multiple devices efficiently
-   🛠️ **Platform Specific** - Android and iOS specific configurations
-   📝 **Comprehensive Logging** - Detailed logging for debugging
-   ⚡ **Performance Optimized** - Token caching and efficient API calls
-   🧪 **Testing Commands** - Built-in commands for testing functionality

## Requirements

-   PHP 8.1 or higher
-   Laravel 10.0 or higher
-   Firebase project with FCM enabled

## Installation

Install the package via Composer:

```bash
composer require netosts/laravel-fcm-notifications
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=fcm-notifications-config
```

## Configuration

### 1. Firebase Setup

1. Go to the [Firebase Console](https://console.firebase.google.com/)
2. Create a new project or select an existing one
3. Go to **Project Settings** → **Service Accounts**
4. Click **Generate New Private Key** to download the service account JSON file

### 2. Environment Variables

Add the following to your `.env` file:

```env
FCM_PROJECT_ID=your-firebase-project-id
FCM_CLIENT_EMAIL=your-service-account@your-project.iam.gserviceaccount.com
FCM_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nYour-Private-Key-Here\n-----END PRIVATE KEY-----"
FCM_DEFAULT_MODE=data_only
FCM_TOKEN_COLUMN=token
FCM_AUTO_CLEANUP_TOKENS=true
```

**Note:** The private key should include the `\n` characters for line breaks.

### 3. Database Setup (Optional)

If you want to store multiple FCM tokens per user, create a `notification_tokens` table:

```php
Schema::create('notification_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('token')->unique();
    $table->timestamps();
});
```

Or add a single token column to your users table:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('fcm_token')->nullable();
});
```

## Usage

### Basic Usage

Create a notification class:

```php
<?php

namespace App\Notifications;

use LaravelFcmNotifications\Notifications\FcmNotification;

class WelcomeNotification extends FcmNotification
{
    public function __construct(private string $username)
    {
        parent::__construct(
            title: 'Welcome!',
            body: "Hello {$this->username}, welcome to our app!",
            data: [
                'type' => 'welcome',
                'user_id' => '123'
            ]
        );
    }
}
```

Send the notification:

```php
use App\Notifications\WelcomeNotification;

$user->notify(new WelcomeNotification($user->name));
```

### Direct Service Usage

You can also use the FCM service directly:

```php
use LaravelFcmNotifications\Facades\Fcm;
use LaravelFcmNotifications\Services\FcmMessage;

$message = FcmMessage::create(
    title: 'Direct Message',
    body: 'This is sent directly via the service',
    image: 'https://example.com/image.jpg'
)
->addData('custom_key', 'custom_value')
->setAndroidPriority('high')
->setIosBadge(1);

$result = Fcm::sendToDevice($token, $message);

if ($result['success']) {
    echo "Message sent successfully!";
} else {
    echo "Failed to send: " . $result['error'];
}
```

### Message Types

#### 1. Notification + Data (Default)

Shows system notification and passes data to your app:

```php
$notification = new FcmNotification(
    title: 'New Message',
    body: 'You have a new message',
    data: ['message_id' => '123']
);
```

#### 2. Data Only

No system notification, your app handles everything:

```php
$notification = (new FcmNotification(
    title: 'Background Update',
    body: 'Data updated',
    data: ['sync' => 'true']
))->dataOnly();
```

#### 3. Notification Only

Only shows system notification, no app data:

```php
$notification = (new FcmNotification(
    title: 'System Alert',
    body: 'Important system message'
))->notificationOnly();
```

### Batch Sending

Send to multiple devices:

```php
$tokens = ['token1', 'token2', 'token3'];

$message = FcmMessage::create('Batch Message', 'Sent to multiple devices');

$result = Fcm::sendToMultipleDevices($tokens, $message);

echo "Sent to {$result['summary']['success']} devices";
echo "Failed: {$result['summary']['failure']} devices";
```

### Platform-Specific Configuration

#### Android

```php
$message = FcmMessage::create('Android Message', 'Optimized for Android')
    ->setAndroidChannel('important_notifications')
    ->setAndroidPriority('high')
    ->setAndroidSound('custom_sound.mp3');
```

#### iOS

```php
$message = FcmMessage::create('iOS Message', 'Optimized for iOS')
    ->setIosBadge(5)
    ->setIosSound('custom_sound.caf');
```

### Token Management

The package automatically discovers FCM tokens from your models. Implement one of these methods:

#### Option 1: Relationship Method (Multiple Tokens)

```php
class User extends Model
{
    public function notificationTokens()
    {
        return $this->hasMany(NotificationToken::class);
    }
}
```

#### Option 2: Single Token Methods

```php
class User extends Model
{
    // Method 1: Attribute
    protected $fillable = ['fcm_token'];

    // Method 2: Custom method
    public function getFcmToken()
    {
        return $this->fcm_token;
    }
}
```

### Event Listeners

The package dispatches events for automatic token cleanup:

```php
// In EventServiceProvider
protected $listen = [
    \LaravelFcmNotifications\Events\UnregisteredFcmTokenDetected::class => [
        \LaravelFcmNotifications\Listeners\CleanupUnregisteredFcmToken::class,
    ],
];
```

## Testing

### Test Commands

The package includes testing commands:

```bash
# Test basic FCM functionality
php artisan fcm:test --token=your-test-token

# Test direct service usage
php artisan fcm:test --token=your-test-token --direct

# Test token cleanup functionality
php artisan fcm:test-cleanup your-test-token
```

### Token Validation

```php
use LaravelFcmNotifications\Facades\Fcm;

// Validate single token
$isValid = Fcm::validateToken($token);

// Validate multiple tokens
$result = Fcm::validateTokens([$token1, $token2, $token3]);
echo "Valid: " . count($result['valid']);
echo "Invalid: " . count($result['invalid']);
```

## Configuration Options

The `config/fcm-notifications.php` file contains all configuration options:

```php
return [
    // Firebase credentials
    'project_id' => env('FCM_PROJECT_ID'),
    'client_email' => env('FCM_CLIENT_EMAIL'),
    'private_key' => env('FCM_PRIVATE_KEY'),

    // API settings
    'timeout' => env('FCM_TIMEOUT', 30),

    // Default message behavior
    'default_mode' => env('FCM_DEFAULT_MODE', 'data_only'),

    // Token storage
    'token_column' => env('FCM_TOKEN_COLUMN', 'token'),
    'auto_cleanup_tokens' => env('FCM_AUTO_CLEANUP_TOKENS', true),

    // JWT settings
    'cache_token' => true,
    'cache_prefix' => 'fcm_notifications_token',
];
```

## Troubleshooting

### Common Issues

1. **Authentication Failed**

    - Verify your Firebase service account credentials
    - Ensure the private key includes proper line breaks (`\n`)

2. **Tokens Not Found**

    - Check your token storage implementation
    - Verify the `token_column` configuration

3. **Messages Not Received**
    - Test with the `fcm:test` command
    - Check FCM token validity
    - Verify app is properly configured for FCM

### Debug Mode

Enable detailed logging by setting your log level to `debug`:

```env
LOG_LEVEL=debug
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

<!-- ## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details. -->

## Security

If you discover any security-related issues, please email netostt91@gmail.com instead of using the issue tracker.

## Credits

-   [Neto Santos](https://github.com/netosts)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Support

-   📧 Email: netostt91@gmail.com
-   🐛 Issues: [GitHub Issues](https://github.com/netosts/laravel-fcm-notifications/issues)
-   💬 Discussions: [GitHub Discussions](https://github.com/netosts/laravel-fcm-notifications/discussions)
