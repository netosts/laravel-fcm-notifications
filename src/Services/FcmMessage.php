<?php

/**
 * Laravel FCM Notifications - Message Builder
 * 
 * A comprehensive Firebase Cloud Messaging message builder for Laravel applications.
 * Supports all FCM message types and platform-specific configurations.
 * 
 * @package LaravelFcmNotifications
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 */

namespace LaravelFcmNotifications\Services;

/**
 * FCM Message Builder
 * 
 * Provides a fluent interface for building FCM messages with support for:
 * - Notification-only messages (system UI)
 * - Data-only messages (app handles display)
 * - Combined notification and data messages
 * - Platform-specific configurations (Android/iOS)
 * - Automatic data type conversion for FCM compatibility
 */
class FcmMessage
{
  protected array $notification = [];
  protected array $data = [];
  protected array $androidConfig = [];
  protected array $apnsConfig = [];
  protected string $mode = 'notification_and_data';

  /**
   * Create a new FCM message
   * 
   * @param string $title Notification title
   * @param string $body Notification body
   * @param string|null $image Notification image URL
   */
  public function __construct(
    protected string $title = '',
    protected string $body = '',
    protected ?string $image = null
  ) {
    $this->notification = [
      'title' => $this->title,
      'body' => $this->body,
    ];

    if ($this->image) {
      $this->notification['image'] = $this->image;
    }
  }

  /**
   * Create a new message instance
   * 
   * @param string $title Notification title
   * @param string $body Notification body
   * @param string|null $image Notification image URL
   * @return self
   */
  public static function create(string $title, string $body, ?string $image = null): self
  {
    return new self($title, $body, $image);
  }

  /**
   * Create a data-only message (no notification UI, app handles display)
   * 
   * @param array $data Data payload for the app
   * @return self
   */
  public static function createDataOnly(array $data = []): self
  {
    $instance = new self('', '', null);
    $instance->mode = 'data_only';
    $instance->notification = [];
    // Ensure all data values are strings for FCM compatibility
    $instance->data = array_map(fn($value) => (string) $value, $data);
    return $instance;
  }

  /**
   * Create a notification-only message (system handles UI, no app data)
   * 
   * @param string $title Notification title
   * @param string $body Notification body
   * @param string|null $image Notification image URL
   * @return self
   */
  public static function createNotificationOnly(string $title, string $body, ?string $image = null): self
  {
    $instance = new self($title, $body, $image);
    $instance->mode = 'notification_only';
    $instance->data = [];
    return $instance;
  }

  /**
   * Set notification title
   * 
   * @param string $title Notification title
   * @return self
   */
  public function setTitle(string $title): self
  {
    $this->title = $title;
    $this->notification['title'] = $title;
    return $this;
  }

  /**
   * Set notification body
   * 
   * @param string $body Notification body
   * @return self
   */
  public function setBody(string $body): self
  {
    $this->body = $body;
    $this->notification['body'] = $body;
    return $this;
  }

  /**
   * Set notification image
   * 
   * @param string|null $image Notification image URL
   * @return self
   */
  public function setImage(?string $image): self
  {
    $this->image = $image;
    if ($image) {
      $this->notification['image'] = $image;
    } else {
      unset($this->notification['image']);
    }
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
   * Set multiple data fields (ensures all values are strings for FCM compatibility)
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
   * Set Android notification channel
   * 
   * @param string $channelId Android notification channel ID
   * @return self
   */
  public function setAndroidChannel(string $channelId): self
  {
    $this->androidConfig['notification']['channel_id'] = $channelId;
    return $this;
  }

  /**
   * Set Android notification priority
   * 
   * @param string $priority Priority level (high, normal, low)
   * @return self
   */
  public function setAndroidPriority(string $priority = 'high'): self
  {
    $this->androidConfig['priority'] = $priority;
    return $this;
  }

  /**
   * Set Android notification sound
   * 
   * @param string $sound Sound file name or 'default'
   * @return self
   */
  public function setAndroidSound(string $sound = 'default'): self
  {
    $this->androidConfig['notification']['sound'] = $sound;
    return $this;
  }

  /**
   * Set iOS badge count
   * 
   * @param int $badge Badge number to display
   * @return self
   */
  public function setIosBadge(int $badge): self
  {
    $this->apnsConfig['payload']['aps']['badge'] = $badge;
    return $this;
  }

  /**
   * Set iOS notification sound
   * 
   * @param string $sound Sound file name or 'default'
   * @return self
   */
  public function setIosSound(string $sound = 'default'): self
  {
    $this->apnsConfig['payload']['aps']['sound'] = $sound;
    return $this;
  }

  /**
   * Set the message mode (determines what components are included in FCM message)
   * 
   * @param string $mode Message mode: 'notification_only', 'data_only', 'notification_and_data'
   * @return self
   * @throws \InvalidArgumentException If mode is invalid
   */
  public function setMode(string $mode): self
  {
    if (!in_array($mode, ['notification_only', 'data_only', 'notification_and_data'])) {
      throw new \InvalidArgumentException('Invalid mode. Use: notification_only, data_only, or notification_and_data');
    }

    $this->mode = $mode;

    // Clear appropriate arrays based on mode
    if ($mode === 'data_only') {
      $this->notification = [];
    } elseif ($mode === 'notification_only') {
      $this->data = [];
    }

    return $this;
  }

  /**
   * Get the current message mode
   * 
   * @return string Current message mode
   */
  public function getMode(): string
  {
    return $this->mode;
  }

  /**
   * Get notification array
   * 
   * @return array Notification configuration
   */
  public function getNotification(): array
  {
    return array_filter($this->notification);
  }

  /**
   * Get data array
   * 
   * @return array Data payload
   */
  public function getData(): array
  {
    return $this->data;
  }

  /**
   * Get Android configuration
   * 
   * @return array Android-specific configuration
   */
  public function getAndroidConfig(): array
  {
    return $this->androidConfig;
  }

  /**
   * Get APNS configuration
   * 
   * @return array iOS-specific configuration
   */
  public function getApnsConfig(): array
  {
    return $this->apnsConfig;
  }

  /**
   * Get notification title
   * 
   * @return string Notification title
   */
  public function getTitle(): string
  {
    return $this->title;
  }

  /**
   * Get notification body
   * 
   * @return string Notification body
   */
  public function getBody(): string
  {
    return $this->body;
  }

  /**
   * Get notification image URL
   * 
   * @return string|null Notification image URL
   */
  public function getImage(): ?string
  {
    return $this->image;
  }
  /**
   * Convert message to array format for FCM API
   * 
   * @return array FCM-compatible message array
   */
  public function toArray(): array
  {
    $message = [];

    // Add components based on mode
    switch ($this->mode) {
      case 'notification_only':
        if (!empty($this->getNotification())) {
          $message['notification'] = $this->getNotification();
        }
        break;

      case 'data_only':
        if (!empty($this->getData())) {
          $message['data'] = $this->getData();
        }
        break;

      case 'notification_and_data':
      default:
        if (!empty($this->getNotification())) {
          $message['notification'] = $this->getNotification();
        }
        if (!empty($this->getData())) {
          $message['data'] = $this->getData();
        }
        break;
    }

    // Always add platform-specific configs if they exist
    if (!empty($this->getAndroidConfig())) {
      $message['android'] = $this->getAndroidConfig();
    }
    if (!empty($this->getApnsConfig())) {
      $message['apns'] = $this->getApnsConfig();
    }

    return $message;
  }
}
