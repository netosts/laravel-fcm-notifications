<?php

/**
 * Laravel FCM Notifications - Service Interface
 * 
 * Defines the contract for FCM service implementations.
 * 
 * @package LaravelFcmNotifications\Contracts
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 * @version 1.0.0
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
   * Send a message to a single device
   * 
   * @param string $token The FCM token for the target device
   * @param FcmMessage $message The message to send
   * @return array Response from the FCM service
   */
  public function sendToDevice(string $token, FcmMessage $message): array;

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
   * @return bool True if the token is valid, false otherwise
   */
  public function validateToken(string $token): bool;

  /**
   * Validate multiple FCM tokens
   * 
   * @param array $tokens Array of FCM tokens to validate
   * @return array Array containing valid and invalid tokens
   */
  public function validateTokens(array $tokens): array;
}
