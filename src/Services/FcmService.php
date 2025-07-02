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
 * @version 1.0.0
 */

namespace LaravelFcmNotifications\Services;

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
class FcmService
{
  protected string $projectId;
  protected string $clientEmail;
  protected string $privateKey;
  protected string $accessToken;

  /**
   * Initialize FCM service with configuration validation
   * 
   * @throws Exception If FCM configuration is incomplete
   */
  public function __construct()
  {
    $this->projectId = config('fcm-notifications.project_id');
    $this->clientEmail = config('fcm-notifications.client_email');
    $this->privateKey = config('fcm-notifications.private_key');

    $this->validateConfiguration();
    $this->accessToken = $this->getAccessToken();
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
   * Send FCM notification to a single device
   * 
   * @param string $token FCM registration token
   * @param FcmMessage $message Message to send
   * @return array Result with success status and response data
   */
  public function sendToDevice(string $token, FcmMessage $message): array
  {
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
        $this->handleFcmApiError($response, $token, $message);
      }

      Log::info('FCM: Message sent successfully', [
        'token' => $this->maskToken($token),
        'title' => $message->getTitle(),
        'mode' => $message->getMode(),
      ]);

      return [
        'success' => true,
        'response' => $response->json(),
        'token' => $token
      ];
    } catch (Exception $e) {
      Log::error('FCM: Failed to send message', [
        'error' => $e->getMessage(),
        'token' => $this->maskToken($token),
        'title' => $message->getTitle(),
        'mode' => $message->getMode(),
      ]);

      return [
        'success' => false,
        'error' => $e->getMessage(),
        'token' => $token,
        'error_type' => $this->getErrorType($e->getMessage())
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
   * Validate a single FCM token format
   * 
   * @param string $token FCM registration token
   * @return bool True if token appears valid
   */
  public function validateToken(string $token): bool
  {
    // FCM tokens are typically 152+ characters with alphanumeric, hyphens, and underscores
    return strlen($token) >= 140 && preg_match('/^[a-zA-Z0-9_-]+$/', $token);
  }

  /**
   * Validate multiple FCM tokens
   * 
   * @param array $tokens Array of FCM registration tokens
   * @return array Array with 'valid' and 'invalid' token arrays
   */
  public function validateTokens(array $tokens): array
  {
    $valid = [];
    $invalid = [];

    foreach ($tokens as $token) {
      if ($this->validateToken($token)) {
        $valid[] = $token;
      } else {
        $invalid[] = $token;
      }
    }

    if (!empty($invalid)) {
      Log::warning('FCM: Invalid tokens detected', [
        'invalid_count' => count($invalid),
        'valid_count' => count($valid),
      ]);
    }

    return [
      'valid' => $valid,
      'invalid' => $invalid
    ];
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

    // Parse specific FCM error codes
    if (isset($errorData['error']['details'][0]['errorCode'])) {
      $fcmErrorCode = $errorData['error']['details'][0]['errorCode'];

      switch ($fcmErrorCode) {
        case 'UNREGISTERED':
          $errorType = 'unregistered';
          $errorMessage = 'FCM token is unregistered or invalid';
          $this->handleUnregisteredToken($token);
          break;

        case 'INVALID_ARGUMENT':
          $errorType = 'invalid_argument';
          $errorMessage = 'Invalid FCM message format or parameters';
          break;

        case 'SENDER_ID_MISMATCH':
          $errorType = 'sender_mismatch';
          $errorMessage = 'FCM token sender ID mismatch';
          break;

        case 'QUOTA_EXCEEDED':
          $errorType = 'quota_exceeded';
          $errorMessage = 'FCM quota exceeded';
          break;

        default:
          $errorMessage = "FCM API error: {$fcmErrorCode}";
          $errorType = strtolower($fcmErrorCode);
      }
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
    Log::info('FCM: Unregistered token detected', [
      'token' => $this->maskToken($token),
      'action' => 'should_remove_from_database'
    ]);

    // Fire an event that listeners can use to clean up the token
    if (class_exists('\Illuminate\Support\Facades\Event') && config('fcm-notifications.cleanup_unregistered_tokens', true)) {
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
}
