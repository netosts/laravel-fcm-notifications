<?php

/**
 * Laravel FCM Notifications - Service Interface
 * 
 * Defines the contract for FCM service implementations.
 * 
 * @package LaravelFcmNotifications\Contracts
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 */

namespace LaravelFcmNotifications\Contracts;

use LaravelFcmNotifications\Services\FcmMessage;

/**
 * FCM Service Interface
 * 
 * Defines the methods that must be implemented by any FCM service.
 */
interface FcmServiceInterface
{
  /**
   * Send a message to a single device with automatic token cleanup
   * 
   * @param string $token The FCM token for the target device
   * @param FcmMessage $message The message to send
   * @param mixed|null $model Optional model for token cleanup context
   * @return array Response from the FCM service
   */
  public function sendToDevice(string $token, FcmMessage $message, $model = null): array;

  /**
   * Send a message to multiple devices
   * 
   * @param array $tokens Array of FCM tokens for target devices
   * @param FcmMessage $message The message to send
   * @return array Response from the FCM service
   */
  public function sendToMultipleDevices(array $tokens, FcmMessage $message): array;

  /**
   * Send a message to multiple devices with automatic token cleanup
   * 
   * @param array $tokens Array of FCM tokens for target devices
   * @param FcmMessage $message The message to send
   * @param mixed|null $model Optional model for token cleanup context
   * @return array Response from the FCM service
   */
  public function sendToMultipleDevicesWithCleanup(array $tokens, FcmMessage $message, $model = null): array;

  /**
   * Validate a single FCM token
   * 
   * @param string $token The FCM token to validate
   * @return array Validation result with status and error details
   */
  public function validateToken(string $token): array;

  /**
   * Validate multiple FCM tokens
   * 
   * @param array $tokens Array of FCM tokens to validate
   * @return array Results for each token with validation status
   */
  public function validateTokens(array $tokens): array;

  /**
   * Simple boolean validation for backward compatibility
   * 
   * @param string $token The FCM token to validate
   * @return bool True if the token is valid, false otherwise
   */
  public function isValidToken(string $token): bool;
}
