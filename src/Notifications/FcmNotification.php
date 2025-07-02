<?php

/**
 * Laravel FCM Notifications - Push Notification
 * 
 * A Laravel notification class for Firebase Cloud Messaging.
 * Provides a clean interface for sending FCM notifications with various modes.
 * 
 * @package LaravelFcmNotifications
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 * @version 1.0.0
 */

namespace LaravelFcmNotifications\Notifications;

use LaravelFcmNotifications\Services\FcmMessage;
use Illuminate\Notifications\Notification;

/**
 * FCM Push Notification
 * 
 * A comprehensive notification class that supports:
 * - All FCM message modes (notification_only, data_only, notification_and_data)
 * - Automatic token discovery and multi-token sending
 * - Queue support for async processing
 * - Automatic data type conversion for FCM compatibility
 */
class FcmNotification extends Notification
{
  protected ?string $token;
  protected string $title;
  protected string $body;
  protected ?string $image;
  protected array $data;
  protected ?string $mode = null; // null means use config default

  /**
   * Create a new FCM push notification
   * 
   * @param string $title Notification title
   * @param string $body Notification body
   * @param string|null $image Notification image URL
   * @param array $data Data payload for the app
   * @param string|null $token Specific FCM token (optional, auto-detects if null)
   */
  public function __construct(
    string $title = '',
    string $body = '',
    ?string $image = null,
    array $data = [],
    ?string $token = null
  ) {
    $this->token = $token;
    $this->title = $title;
    $this->body = $body;
    $this->image = $image;
    $this->data = $data;
  }

  /**
   * Get the notification's delivery channels
   * 
   * @param mixed $notifiable The notifiable model instance
   * @return array Array of channel names
   */
  public function via($notifiable): array
  {
    return ['fcm'];
  }

  /**
   * Get the FCM token for this notification
   * 
   * @return string|null FCM token or null for auto-detection
   */
  public function getToken(): ?string
  {
    return $this->token;
  }

  /**
   * Set to data-only mode (no notification UI, app handles everything)
   * 
   * @return self
   */
  public function dataOnly(): self
  {
    $this->mode = 'data_only';
    return $this;
  }

  /**
   * Set to notification-only mode (system UI only, no app data)
   * 
   * @return self
   */
  public function notificationOnly(): self
  {
    $this->mode = 'notification_only';
    return $this;
  }

  /**
   * Set to both notification and data mode (system UI + app data)
   * 
   * @return self
   */
  public function withNotificationAndData(): self
  {
    $this->mode = 'notification_and_data';
    return $this;
  }

  /**
   * Add custom data (ensures value is string for FCM compatibility)
   * 
   * @param string $key Data key
   * @param mixed $value Data value (will be converted to string)
   * @return self
   */
  public function addData(string $key, mixed $value): self
  {
    $this->data[$key] = (string) $value;
    return $this;
  }

  /**
   * Set custom data (ensures all values are strings for FCM compatibility)
   * 
   * @param array $data Data array
   * @return self
   */
  public function setData(array $data): self
  {
    // Ensure all values are strings for FCM compatibility
    $stringData = array_map(fn($value) => (string) $value, $data);
    $this->data = array_merge($this->data, $stringData);
    return $this;
  }

  /**
   * Convert notification to FCM message format
   * 
   * @param mixed $notifiable The notifiable model instance
   * @return FcmMessage FCM message instance
   */
  public function toFcm($notifiable): FcmMessage
  {
    // Use the explicitly set mode or fall back to config default
    $mode = $this->mode ?? config('fcm-notifications.default_mode', 'data_only');

    // Create message based on mode
    switch ($mode) {
      case 'notification_only':
        $message = FcmMessage::createNotificationOnly($this->title, $this->body, $this->image);
        break;

      case 'data_only':
        // For data-only, include title/body as data if provided
        $dataPayload = array_map(fn($value) => (string) $value, $this->data);
        if ($this->title) $dataPayload['title'] = $this->title;
        if ($this->body) $dataPayload['body'] = $this->body;
        if ($this->image) $dataPayload['image'] = $this->image;
        $message = FcmMessage::createDataOnly($dataPayload);
        break;

      case 'notification_and_data':
      default:
        $message = FcmMessage::create($this->title, $this->body, $this->image);
        if (!empty($this->data)) {
          // Ensure all data values are strings for FCM compatibility
          $stringData = array_map(fn($value) => (string) $value, $this->data);
          $message->setData($stringData);
        }
        break;
    }

    // Default platform configurations for better delivery
    $message->setAndroidPriority('high')
      ->setAndroidSound('default')
      ->setAndroidChannel('default_notifications')
      ->setIosSound('default')
      ->setIosBadge(1);

    return $message;
  }
}
