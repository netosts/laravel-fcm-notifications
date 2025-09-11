<?php

/**
 * Laravel FCM Notifications - Service
 * 
 * A robust and secure Firebase Cloud Messaging service for Laravel applications.
 * Handles FCM authentication, message sending, error handling, and token cleanup.
 * 
 * @package LaravelFcmNotifications
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 */

namespace LaravelFcmNotifications\Services;

use LaravelFcmNotifications\Contracts\FcmServiceInterface;
use LaravelFcmNotifications\Events\UnregisteredFcmTokenDetected;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging Service
 * 
 * Provides comprehensive FCM functionality including:
 * - JWT-based authentication with Google FCM API
 * - Single and batch message sending
 * - Automatic token validation and cleanup
 * - Comprehensive error handling and logging
 * - Support for all FCM message types
 */
class FcmService implements FcmServiceInterface
{
  protected ?string $projectId = null;
  protected ?string $clientEmail = null;
  protected ?string $privateKey = null;
  protected ?string $accessToken = null;
  protected bool $initialized = false;

  /**
   * Initialize FCM service (constructor kept minimal for Laravel service container)
   */
  public function __construct()
  {
    // Defer initialization until actually needed to avoid issues during package discovery
  }

  /**
   * Initialize FCM service configuration
   * This method is called lazily when the service is first used
   * 
   * @throws Exception If FCM configuration is incomplete
   */
  protected function initializeIfNeeded(): void
  {
    if ($this->initialized) {
      return;
    }

    // Skip initialization if config values are not available (e.g., during package discovery)
    $projectId = config('fcm-notifications.project_id');
    $clientEmail = config('fcm-notifications.client_email');
    $privateKey = config('fcm-notifications.private_key');

    if (empty($projectId) || empty($clientEmail) || empty($privateKey)) {
      // Don't throw during package discovery - just return and let it initialize later
      return;
    }

    $this->projectId = $projectId;
    $this->clientEmail = $clientEmail;
    $this->privateKey = $privateKey;

    $this->validateConfiguration();
    $this->accessToken = $this->getAccessToken();
    $this->initialized = true;
  }

  /**
   * Validate FCM configuration parameters
   * 
   * @throws Exception If any required configuration is missing
   */
  protected function validateConfiguration(): void
  {
    if (empty($this->projectId) || empty($this->clientEmail) || empty($this->privateKey)) {
      throw new Exception('FCM configuration is incomplete. Please check your environment variables.');
    }
  }

  /**
   * Get or generate OAuth2 access token with caching
   * 
   * @return string Valid access token for FCM API
   * @throws Exception If token generation fails
   */
  protected function getAccessToken(): string
  {
    $cacheKey = config('fcm-notifications.cache_prefix') . ':access_token';

    if (config('fcm-notifications.cache_token', true)) {
      $token = Cache::get($cacheKey);
      if ($token && is_string($token)) {
        return $token;
      }
    }

    $jwt = $this->createJwtToken();
    $accessToken = $this->exchangeJwtForAccessToken($jwt);

    if (config('fcm-notifications.cache_token', true)) {
      // Cache for 55 minutes (5 minutes less than expiry for safety)
      Cache::put($cacheKey, $accessToken, now()->addMinutes(55));
    }

    return $accessToken;
  }

  /**
   * Create JWT token for Google OAuth2 authentication
   * 
   * @return string Signed JWT token
   * @throws Exception If JWT signing fails
   */
  protected function createJwtToken(): string
  {
    $header = [
      'alg' => 'RS256',
      'typ' => 'JWT',
    ];

    $now = time();
    $expiry = $now + config('fcm-notifications.jwt_expiry', 3600);

    $payload = [
      'iss' => $this->clientEmail,
      'sub' => $this->clientEmail,
      'aud' => config('fcm-notifications.oauth_url'),
      'iat' => $now,
      'exp' => $expiry,
      'scope' => config('fcm-notifications.scope'),
    ];

    $base64UrlHeader = $this->base64UrlEncode(json_encode($header));
    $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));

    $signature = '';
    $success = openssl_sign(
      $base64UrlHeader . "." . $base64UrlPayload,
      $signature,
      $this->privateKey,
      'sha256WithRSAEncryption'
    );

    if (!$success) {
      throw new Exception('Failed to sign JWT token');
    }

    $base64UrlSignature = $this->base64UrlEncode($signature);

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
  }

  /**
   * Exchange JWT token for OAuth2 access token
   * 
   * @param string $jwt Signed JWT token
   * @return string OAuth2 access token
   * @throws Exception If token exchange fails
   */
  protected function exchangeJwtForAccessToken(string $jwt): string
  {
    try {
      $response = Http::asForm()->post(config('fcm-notifications.oauth_url'), [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
      ]);

      if (!$response->successful()) {
        throw new Exception('Failed to obtain access token: ' . $response->body());
      }

      $data = $response->json();

      if (!isset($data['access_token'])) {
        throw new Exception('Access token not found in response');
      }

      return $data['access_token'];
    } catch (Exception $e) {
      Log::error('FCM: Failed to get access token', [
        'error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Base64 URL encode (RFC 4648)
   * 
   * @param string $data Data to encode
   * @return string Base64 URL encoded string
   */
  protected function base64UrlEncode(string $data): string
  {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
  }

  /**
   * Send FCM notification to a single device with automatic token cleanup
   * 
   * @param string $token FCM registration token
   * @param FcmMessage $message Message to send
   * @param mixed $model Model instance for token cleanup (optional)
   * @return array Result with success status and response data
   */
  public function sendToDevice(string $token, FcmMessage $message, $model = null): array
  {
    $this->initializeIfNeeded();
    $result = $this->sendToDeviceWithRetry($token, $message, 0);

    // If we have a model and the token is unregistered, clean it up
    if (
      $model && !$result['success'] &&
      isset($result['error_type']) && $result['error_type'] === 'unregistered'
    ) {
      $this->cleanupUnregisteredTokens([$token], $model);
    }

    return $result;
  }

  /**
   * Send FCM notification to a single device with automatic retry on auth failure
   * 
   * @param string $token FCM registration token
   * @param FcmMessage $message Message to send
   * @param int $attemptNumber Current attempt number (0-based)
   * @return array Result with success status and response data
   */
  protected function sendToDeviceWithRetry(string $token, FcmMessage $message, int $attemptNumber = 0): array
  {
    // Check if service is properly initialized
    if (!$this->initialized || empty($this->projectId) || empty($this->accessToken)) {
      return [
        'success' => false,
        'error' => 'FCM service not properly initialized. Please check your configuration.',
        'error_type' => 'configuration'
      ];
    }

    // Maximum number of retry attempts to prevent infinite loops
    $maxRetries = config('fcm-notifications.max_auth_retries', 2);

    $url = config('fcm-notifications.base_url') . "/{$this->projectId}/messages:send";

    $payload = [
      'message' => array_merge(
        ['token' => $token],
        $message->toArray()
      )
    ];

    try {
      $response = Http::withToken($this->accessToken)
        ->timeout(config('fcm-notifications.timeout', 30))
        ->post($url, $payload);

      if (!$response->successful()) {
        // Check if it's an authentication error and we haven't exceeded max retries
        if ($response->status() === 401 && $attemptNumber < $maxRetries) {
          Log::info('FCM: Access token expired, refreshing and retrying', [
            'token' => $this->maskToken($token),
            'title' => $message->getTitle(),
            'attempt' => $attemptNumber + 1,
            'max_retries' => $maxRetries,
          ]);

          // Clear cached token and get a fresh one
          $this->refreshAccessToken();

          // Add a small delay to prevent rapid-fire retries
          if ($attemptNumber > 0) {
            usleep(250000); // 250ms delay for subsequent retries
          }

          // Retry with incremented attempt number
          return $this->sendToDeviceWithRetry($token, $message, $attemptNumber + 1);
        }

        $this->handleFcmApiError($response, $token, $message);
      }

      Log::info('FCM: Message sent successfully', [
        'token' => $this->maskToken($token),
        'title' => $message->getTitle(),
        'mode' => $message->getMode(),
        'attempt_number' => $attemptNumber + 1,
      ]);

      return [
        'success' => true,
        'response' => $response->json(),
        'token' => $token,
        'attempts' => $attemptNumber + 1
      ];
    } catch (Exception $e) {
      Log::error('FCM: Failed to send message', [
        'error' => $e->getMessage(),
        'token' => $this->maskToken($token),
        'title' => $message->getTitle(),
        'mode' => $message->getMode(),
        'attempt_number' => $attemptNumber + 1,
        'max_retries_exceeded' => $attemptNumber >= $maxRetries,
      ]);

      return [
        'success' => false,
        'error' => $e->getMessage(),
        'token' => $token,
        'error_type' => $this->getErrorType($e->getMessage()),
        'attempts' => $attemptNumber + 1,
        'max_retries_exceeded' => $attemptNumber >= $maxRetries
      ];
    }
  }

  /**
   * Send FCM notification to multiple devices
   * 
   * @param array $tokens Array of FCM registration tokens
   * @param FcmMessage $message Message to send
   * @return array Batch send results with summary
   */
  public function sendToMultipleDevices(array $tokens, FcmMessage $message): array
  {
    $this->initializeIfNeeded();
    $results = [];
    $successCount = 0;
    $failureCount = 0;
    $unregisteredTokens = [];

    foreach ($tokens as $token) {
      $result = $this->sendToDevice($token, $message);

      if ($result['success']) {
        $successCount++;
      } else {
        $failureCount++;

        // Track unregistered tokens for cleanup
        if (isset($result['error_type']) && $result['error_type'] === 'unregistered') {
          $unregisteredTokens[] = $token;
        }
      }

      $results[] = $result;
    }

    Log::info('FCM: Batch send completed', [
      'total_tokens' => count($tokens),
      'success_count' => $successCount,
      'failure_count' => $failureCount,
      'unregistered_count' => count($unregisteredTokens),
      'title' => $message->getTitle(),
      'mode' => $message->getMode(),
    ]);

    return [
      'results' => $results,
      'summary' => [
        'total' => count($tokens),
        'success' => $successCount,
        'failure' => $failureCount,
        'unregistered_tokens' => $unregisteredTokens,
      ]
    ];
  }

  /**
   * Send FCM notification to multiple devices with automatic token cleanup
   * 
   * @param array $tokens Array of FCM registration tokens
   * @param FcmMessage $message Message to send
   * @param mixed $model Model instance for token cleanup (optional)
   * @return array Batch send results with summary
   */
  public function sendToMultipleDevicesWithCleanup(array $tokens, FcmMessage $message, $model = null): array
  {
    $this->initializeIfNeeded();
    $result = $this->sendToMultipleDevices($tokens, $message);

    // If we have a model and unregistered tokens, clean them up
    if ($model && !empty($result['summary']['unregistered_tokens'])) {
      $this->cleanupUnregisteredTokens($result['summary']['unregistered_tokens'], $model);
    }

    return $result;
  }

  /**
   * Clean up unregistered tokens from a model
   * 
   * @param array $unregisteredTokens Array of unregistered tokens
   * @param mixed $model Model instance with notificationTokens relationship
   */
  protected function cleanupUnregisteredTokens(array $unregisteredTokens, $model): void
  {
    if (method_exists($model, 'notificationTokens')) {
      try {
        $tokenColumn = config('fcm-notifications.token_column', 'token');
        $model->notificationTokens()
          ->whereIn($tokenColumn, $unregisteredTokens)
          ->delete();

        Log::info('FCM: Cleaned up unregistered tokens', [
          'model_type' => get_class($model),
          'model_id' => $model->id ?? 'unknown',
          'tokens_removed' => count($unregisteredTokens),
        ]);
      } catch (Exception $e) {
        Log::error('FCM: Failed to cleanup unregistered tokens', [
          'error' => $e->getMessage(),
          'model_type' => get_class($model),
          'model_id' => $model->id ?? 'unknown',
        ]);
      }
    }
  }

  /**
   * Validate an FCM token by sending a test message
   * 
   * @param string $token FCM registration token to validate
   * @return array Validation result with status and error details
   */
  public function validateToken(string $token): array
  {
    $this->initializeIfNeeded();
    try {
      // Create a minimal test message
      $testMessage = new FcmMessage();
      $testMessage->setData(['test' => 'validation']);

      // Attempt to send the test message
      $result = $this->sendToDevice($token, $testMessage);

      if ($result['success']) {
        return [
          'valid' => true,
          'token' => $token,
          'message' => 'Token is valid'
        ];
      } else {
        return [
          'valid' => false,
          'token' => $token,
          'error_type' => $result['error_type'] ?? 'unknown',
          'message' => $result['error'] ?? 'Token validation failed'
        ];
      }
    } catch (Exception $e) {
      return [
        'valid' => false,
        'token' => $token,
        'error_type' => 'exception',
        'message' => $e->getMessage()
      ];
    }
  }

  /**
   * Bulk validate multiple FCM tokens
   * 
   * @param array $tokens Array of FCM registration tokens
   * @return array Results for each token with validation status
   */
  public function validateTokens(array $tokens): array
  {
    $this->initializeIfNeeded();
    $results = [];

    foreach ($tokens as $token) {
      $results[] = $this->validateToken($token);
    }

    return $results;
  }

  /**
   * Simple boolean validation for backward compatibility
   * 
   * @param string $token FCM registration token to validate
   * @return bool True if the token is valid, false otherwise
   */
  public function isValidToken(string $token): bool
  {
    $this->initializeIfNeeded();
    $result = $this->validateToken($token);
    return $result['valid'] ?? false;
  }

  /**
   * Handle FCM API errors with specific logic for different error types
   * 
   * @param mixed $response HTTP response object
   * @param string $token FCM registration token
   * @param FcmMessage $message FCM message
   * @throws Exception With detailed error information
   */
  protected function handleFcmApiError($response, string $token, FcmMessage $message): void
  {
    $statusCode = $response->status();
    $responseBody = $response->body();
    $errorData = $response->json();

    $errorMessage = 'FCM API error';
    $errorType = 'unknown';

    // Parse specific FCM error codes and Google API error details
    if (isset($errorData['error']['details'][0]['errorCode'])) {
      $fcmErrorCode = $errorData['error']['details'][0]['errorCode'];

      switch ($fcmErrorCode) {
        case 'UNREGISTERED':
          $errorType = 'unregistered';
          $errorMessage = 'FCM token is unregistered or invalid';
          if (config('fcm-notifications.auto_cleanup_tokens', true)) {
            $this->handleUnregisteredToken($token);
          }
          break;

        case 'INVALID_ARGUMENT':
          $errorType = 'invalid_argument';
          $errorMessage = 'Invalid FCM message format or parameters';
          break;

        case 'SENDER_ID_MISMATCH':
          $errorType = 'sender_mismatch';
          $errorMessage = 'FCM token sender ID mismatch - The token was registered with a different Firebase project. ' .
            'Check your FCM_PROJECT_ID configuration or re-register the device token.';
          // Also mark token as invalid for cleanup if auto-cleanup is enabled
          if (config('fcm-notifications.auto_cleanup_tokens', true)) {
            $this->handleUnregisteredToken($token);
          }
          break;

        case 'QUOTA_EXCEEDED':
          $errorType = 'quota_exceeded';
          $errorMessage = 'FCM quota exceeded';
          break;

        default:
          $errorMessage = "FCM API error: {$fcmErrorCode}";
          $errorType = strtolower($fcmErrorCode);
      }
    } elseif (isset($errorData['error']['details'][0]['reason']) && $errorData['error']['details'][0]['reason'] === 'ACCESS_TOKEN_EXPIRED') {
      $errorType = 'access_token_expired';
      $errorMessage = 'FCM access token has expired';
      // Clear cached token to force refresh on next request
      Cache::forget(config('fcm-notifications.cache_prefix') . ':access_token');
    } elseif ($statusCode === 401) {
      $errorType = 'unauthorized';
      $errorMessage = 'FCM authentication failed - invalid access token';
      // Clear cached token to force refresh on next request
      Cache::forget(config('fcm-notifications.cache_prefix') . ':access_token');
    } elseif ($statusCode === 400) {
      $errorType = 'bad_request';
      $errorMessage = 'FCM bad request - invalid message format';
    } elseif ($statusCode >= 500) {
      $errorType = 'server_error';
      $errorMessage = 'FCM server error - temporary issue';
    }

    Log::warning('FCM: API Error', [
      'error_type' => $errorType,
      'status_code' => $statusCode,
      'token' => $this->maskToken($token),
      'title' => $message->getTitle(),
      'response_body' => $responseBody,
    ]);

    throw new Exception("{$errorMessage} (HTTP {$statusCode}): {$responseBody}");
  }

  /**
   * Handle unregistered FCM tokens by dispatching cleanup events
   * 
   * @param string $token Unregistered FCM token
   */
  protected function handleUnregisteredToken(string $token): void
  {
    Log::info('FCM: Unregistered or invalid token detected', [
      'token' => $this->maskToken($token),
      'action' => 'should_remove_from_database'
    ]);

    // Fire an event that listeners can use to clean up the token
    if (class_exists('\Illuminate\Support\Facades\Event') && config('fcm-notifications.auto_cleanup_tokens', true)) {
      UnregisteredFcmTokenDetected::dispatch($token);
    }
  }

  /**
   * Mask token for secure logging (shows first 4 and last 4 characters)
   * 
   * @param string $token FCM registration token
   * @return string Masked token string
   */
  protected function maskToken(string $token): string
  {
    $length = strlen($token);
    if ($length <= 8) {
      return str_repeat('*', $length);
    }

    return substr($token, 0, 4) . str_repeat('*', $length - 8) . substr($token, -4);
  }

  /**
   * Determine error type from error message content
   * 
   * @param string $errorMessage Error message text
   * @return string Error type classification
   */
  protected function getErrorType(string $errorMessage): string
  {
    $message = strtolower($errorMessage);

    if (strpos($message, 'unregistered') !== false) {
      return 'unregistered';
    } elseif (strpos($message, 'invalid') !== false) {
      return 'invalid_argument';
    } elseif (strpos($message, 'unauthorized') !== false || strpos($message, 'authentication') !== false) {
      return 'unauthorized';
    } elseif (strpos($message, 'quota') !== false) {
      return 'quota_exceeded';
    } elseif (strpos($message, 'timeout') !== false) {
      return 'timeout';
    } elseif (strpos($message, 'server') !== false) {
      return 'server_error';
    }

    return 'unknown';
  }

  /**
   * Refresh the cached access token by clearing cache and generating a new one
   * 
   * @return void
   * @throws Exception If token refresh fails
   */
  protected function refreshAccessToken(): void
  {
    $cacheKey = config('fcm-notifications.cache_prefix') . ':access_token';

    // Clear the cached token
    Cache::forget($cacheKey);

    // Generate a new access token
    $this->accessToken = $this->getAccessToken();
  }
}
