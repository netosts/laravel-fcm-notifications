<?php

/**
 * Laravel FCM Notifications - Unregistered Token Event
 * 
 * Event dispatched when an FCM token is detected as unregistered/invalid
 * by the Firebase Cloud Messaging API.
 * 
 * @package LaravelFcmNotifications
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 */

namespace LaravelFcmNotifications\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Unregistered FCM Token Detected Event
 * 
 * This event is dispatched when an FCM token is detected as unregistered
 * or invalid during message sending. Listeners can use this event to clean
 * up the token from their database.
 */
class UnregisteredFcmTokenDetected
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  /**
   * Create a new event instance.
   * 
   * @param string $token The unregistered FCM token
   */
  public function __construct(public string $token) {}

  /**
   * Get the channels the event should broadcast on.
   *
   * @return array<int, \Illuminate\Broadcasting\Channel>
   */
  public function broadcastOn(): array
  {
    return [
      new PrivateChannel('fcm-token-cleanup'),
    ];
  }
}
