<?php

/**
 * Laravel FCM Notifications - Channel Interface
 * 
 * Defines the contract for FCM notification channel implementations.
 * 
 * @package LaravelFcmNotifications\Contracts
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 * @version 1.0.0
 */

namespace LaravelFcmNotifications\Contracts;

use LaravelFcmNotifications\Notifications\FcmNotification;

/**
 * FCM Channel Interface
 * 
 * Defines the contract for notification channels that handle FCM messages.
 */
interface FcmChannelInterface
{
  /**
   * Send the given notification via FCM
   * 
   * @param mixed $notifiable The notifiable model instance
   * @param FcmNotification $notification The notification to send
   * @return void
   */
  public function send($notifiable, FcmNotification $notification): void;
}
