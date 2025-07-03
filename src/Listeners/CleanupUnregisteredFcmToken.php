<?php

/**
 * Laravel FCM Notifications - Token Cleanup Listener
 * 
 * Automatically removes unregistered FCM tokens from the database.
 * Supports both notification_tokens table and direct user fcm_token columns.
 * 
 * @package LaravelFcmNotifications
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 */

namespace LaravelFcmNotifications\Listeners;

use LaravelFcmNotifications\Events\UnregisteredFcmTokenDetected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FCM Token Cleanup Listener
 * 
 * Handles the UnregisteredFcmTokenDetected event by removing
 * invalid tokens from both notification_tokens table and user records.
 */
class CleanupUnregisteredFcmToken implements ShouldQueue
{
  /**
   * Handle the event by cleaning up unregistered FCM tokens
   * 
   * @param UnregisteredFcmTokenDetected $event Event containing the unregistered token
   * @return void
   */
  public function handle(UnregisteredFcmTokenDetected $event): void
  {
    $unregisteredToken = $event->token ?? null;

    if (!$unregisteredToken) {
      Log::warning('FCM: No token provided to cleanup listener');
      return;
    }

    try {
      // Find and remove the unregistered token from the notification_tokens table
      $tokenColumn = config('fcm-notifications.token_column', 'token');
      $deletedCount = DB::table('notification_tokens')
        ->where($tokenColumn, $unregisteredToken)
        ->delete();

      if ($deletedCount > 0) {
        Log::info('FCM: Successfully cleaned up unregistered token', [
          'token' => $this->maskToken($unregisteredToken),
          'deleted_count' => $deletedCount,
        ]);
      } else {
        Log::info('FCM: Unregistered token not found in database', [
          'token' => $this->maskToken($unregisteredToken),
        ]);
      }

      // Also try to find users who might have this token in a direct fcm_token column
      // (in case the app uses both approaches)
      if (Schema::hasColumn('users', 'fcm_token')) {
        $usersUpdated = DB::table('users')
          ->where('fcm_token', $unregisteredToken)
          ->update(['fcm_token' => null]);

        if ($usersUpdated > 0) {
          Log::info('FCM: Cleaned up FCM token from user records', [
            'token' => $this->maskToken($unregisteredToken),
            'users_updated' => $usersUpdated,
          ]);
        }
      }
    } catch (\Exception $e) {
      Log::error('FCM: Failed to cleanup unregistered token', [
        'error' => $e->getMessage(),
        'token' => $this->maskToken($unregisteredToken),
      ]);
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
}
