<?php

/**
 * Laravel FCM Notifications - Service Provider
 * 
 * Service provider for registering FCM notification services and channels.
 * 
 * @package LaravelFcmNotifications
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 * @version 1.0.0
 */

namespace LaravelFcmNotifications\Providers;

use LaravelFcmNotifications\Services\FcmChannel;
use LaravelFcmNotifications\Services\FcmService;
use LaravelFcmNotifications\Console\Commands\TestFcmCommand;
use LaravelFcmNotifications\Console\Commands\TestFcmCleanupCommand;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;

/**
 * FCM Notifications Service Provider
 * 
 * Registers FCM services, channels, commands, and configuration.
 */
class FcmNotificationsServiceProvider extends ServiceProvider
{
  /**
   * Register services.
   */
  public function register(): void
  {
    // Register the main FCM service
    $this->app->singleton('fcm-notifications.service', function ($app) {
      return new FcmService();
    });

    // Register alias for easier access
    $this->app->alias('fcm-notifications.service', FcmService::class);

    // Merge configuration from package
    $this->mergeConfigFrom(
      __DIR__ . '/../../config/fcm-notifications.php',
      'fcm-notifications'
    );
  }

  /**
   * Bootstrap services.
   */
  public function boot(): void
  {
    // Register the FCM notification channel
    $this->app->make(ChannelManager::class)->extend('fcm', function ($app) {
      return new FcmChannel($app->make('fcm-notifications.service'));
    });

    // Register console commands
    if ($this->app->runningInConsole()) {
      $this->commands([
        TestFcmCommand::class,
        TestFcmCleanupCommand::class,
      ]);

      // Publish configuration
      $this->publishes([
        __DIR__ . '/../../config/fcm-notifications.php' => config_path('fcm-notifications.php'),
      ], 'fcm-notifications-config');

      // Publish migrations (if any)
      // $this->publishes([
      //     __DIR__ . '/../../database/migrations/' => database_path('migrations'),
      // ], 'fcm-notifications-migrations');
    }
  }

  /**
   * Get the services provided by the provider.
   *
   * @return array
   */
  public function provides(): array
  {
    return [
      'fcm-notifications.service',
      FcmService::class,
    ];
  }
}
