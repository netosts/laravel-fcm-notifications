<?php

/**
 * Laravel FCM Notifications - Notification Channel
 * 
 * A Laravel notification channel for Firebase Cloud Messaging.
 * Provides automatic token discovery and management.
 * 
 * @package LaravelFcmNotifications
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 * @version 1.0.0
 */

namespace LaravelFcmNotifications\Services;

use LaravelFcmNotifications\Contracts\FcmChannelInterface;
use LaravelFcmNotifications\Contracts\FcmServiceInterface;
use LaravelFcmNotifications\Notifications\FcmNotification;
use LaravelFcmNotifications\Services\FcmMessage;

/**
 * FCM Notification Channel
 * 
 * Handles sending notifications through FCM with:
 * - Automatic token discovery from notifiable models
 * - Support for single and multiple tokens
 * - Automatic cleanup of invalid tokens
 * - Flexible token source detection
 */
class FcmChannel implements FcmChannelInterface
{
  protected FcmServiceInterface $fcmService;

  /**
   * Create a new FCM channel instance
   * 
   * @param FcmServiceInterface $fcmService FCM service instance
   */
  public function __construct(FcmServiceInterface $fcmService)
  {
    $this->fcmService = $fcmService;
  }

  /**
   * Send the given notification via FCM
   * 
   * @param mixed $notifiable The notifiable model instance
   * @param FcmNotification $notification The notification to send
   * @return void
   */
  public function send($notifiable, FcmNotification $notification): void
  {
    if (!method_exists($notification, 'toFcm')) {
      return;
    }

    $message = $notification->toFcm($notifiable);

    if (!$message instanceof FcmMessage) {
      throw new \InvalidArgumentException('toFcm method must return a FcmMessage instance');
    }

    // Get FCM tokens - use specific token from notification or get from notifiable
    $specificToken = $notification->getToken();

    if ($specificToken) {
      // Use the specific token from the notification
      $result = $this->fcmService->sendToDevice($specificToken, $message);
      $this->handleSendResult($result, $notifiable);
    } else {
      // Get all tokens from the notifiable and send to all
      $tokens = $this->getTokensFromNotifiable($notifiable);

      if (empty($tokens)) {
        return; // No tokens available, skip sending
      }

      // Send to single or multiple tokens
      if (is_string($tokens)) {
        $result = $this->fcmService->sendToDevice($tokens, $message);
        $this->handleSendResult($result, $notifiable);
      } elseif (is_array($tokens) && count($tokens) === 1) {
        $result = $this->fcmService->sendToDevice($tokens[0], $message);
        $this->handleSendResult($result, $notifiable);
      } elseif (is_array($tokens) && count($tokens) > 1) {
        $result = $this->fcmService->sendToMultipleDevicesWithCleanup($tokens, $message, $notifiable);
        $this->handleBatchSendResult($result, $notifiable);
      }
    }
  }

  /**
   * Handle the result of a single device send
   * 
   * @param array $result Send result from FCM service
   * @param mixed $notifiable The notifiable model instance
   * @return void
   */
  protected function handleSendResult(array $result, $notifiable): void
  {
    // Additional single send result handling can be added here if needed
    // Token cleanup is already handled in the service layer
  }

  /**
   * Handle the result of a batch send
   * 
   * @param array $result Batch send result from FCM service
   * @param mixed $notifiable The notifiable model instance
   * @return void
   */
  protected function handleBatchSendResult(array $result, $notifiable): void
  {
    // Additional batch result handling can be added here if needed
    // Token cleanup is already handled in sendToMultipleDevicesWithCleanup
  }

  /**
   * Get FCM tokens from notifiable model (supports single or multiple tokens)
   * 
   * @param mixed $notifiable The notifiable model instance
   * @return string|array|null Token(s) or null if none found
   */
  protected function getTokensFromNotifiable($notifiable)
  {
    // Try to get multiple tokens first (from relationship)
    if (method_exists($notifiable, 'notificationTokens')) {
      $tokenColumn = config('fcm-notifications.token_column', 'token');
      $tokens = $notifiable->notificationTokens()
        ->pluck($tokenColumn)
        ->filter()
        ->toArray();

      if (!empty($tokens)) {
        return $tokens;
      }
    }

    // Try common methods to get single FCM token
    if (method_exists($notifiable, 'getFcmToken')) {
      return $notifiable->getFcmToken();
    }

    if (method_exists($notifiable, 'fcm_token')) {
      return $notifiable->fcm_token;
    }

    if (isset($notifiable->fcm_token)) {
      return $notifiable->fcm_token;
    }

    return null;
  }
}
