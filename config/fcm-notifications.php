<?php

/**
 * Laravel FCM Notifications Configuration
 * 
 * A robust and secure FCM notification system for Laravel applications.
 * 
 * @package LaravelFcmNotifications
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 * @version 1.0.0
 */

return [
  /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase Cloud Messaging service.
    | These values are used to authenticate with Google's FCM API.
    | You'll need to create a service account in your Firebase project
    | and download the JSON credentials file.
    |
    */

  'project_id' => env('FCM_PROJECT_ID'),
  'client_email' => env('FCM_CLIENT_EMAIL'),
  'private_key' => env('FCM_PRIVATE_KEY'),

  /*
    |--------------------------------------------------------------------------
    | FCM API Settings
    |--------------------------------------------------------------------------
    |
    | Additional settings for FCM API communication.
    |
    */

  'base_url' => 'https://fcm.googleapis.com/v1/projects',
  'oauth_url' => 'https://oauth2.googleapis.com/token',
  'scope' => 'https://www.googleapis.com/auth/cloud-platform',
  'timeout' => env('FCM_TIMEOUT', 30), // HTTP timeout in seconds

  /*
    |--------------------------------------------------------------------------
    | JWT Token Settings
    |--------------------------------------------------------------------------
    |
    | Settings for JWT token generation and caching.
    | The access token is cached to avoid generating new tokens for each request.
    |
    */

  'jwt_expiry' => 3600, // 1 hour
  'cache_token' => true,
  'cache_prefix' => 'fcm_notifications_token',

  /*
    |--------------------------------------------------------------------------
    | Authentication Retry Settings
    |--------------------------------------------------------------------------
    |
    | Configure retry behavior for authentication failures.
    | max_auth_retries: Maximum number of retry attempts when access token expires
    | Setting this to 0 disables retries, higher values may increase server load
    |
    */

  'max_auth_retries' => env('FCM_MAX_AUTH_RETRIES', 2),

  /*
    |--------------------------------------------------------------------------
    | Default Message Settings
    |--------------------------------------------------------------------------
    |
    | Default behavior for FCM messages:
    | - notification_only: System handles UI, no app data
    | - data_only: App handles everything, no system UI  
    | - notification_and_data: Both system UI and app data
    |
    */

  'default_mode' => env('FCM_DEFAULT_MODE', 'notification_and_data'),

  /*
    |--------------------------------------------------------------------------
    | Token Storage Settings
    |--------------------------------------------------------------------------
    |
    | Configure how FCM tokens are stored in your database.
    | The token_column setting allows you to specify which column contains
    | the FCM token in your notification_tokens table (e.g., 'token', 'key').
    |
    */

  'token_column' => env('FCM_TOKEN_COLUMN', 'token'),

  /*
    |--------------------------------------------------------------------------
    | Automatic Token Cleanup
    |--------------------------------------------------------------------------
    |
    | When enabled, unregistered FCM tokens will be automatically removed
    | from your database when they're detected during message sending.
    |
    */

  'auto_cleanup_tokens' => env('FCM_AUTO_CLEANUP_TOKENS', true),
];
