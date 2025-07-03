<?php

/**
 * Laravel FCM Notifications - Facade
 * 
 * A convenient facade for accessing Firebase Cloud Messaging functionality.
 * 
 * @package LaravelFcmNotifications
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 */

namespace LaravelFcmNotifications\Facades;

use LaravelFcmNotifications\Services\FcmMessage;
use Illuminate\Support\Facades\Facade;

/**
 * Laravel FCM Facade
 * 
 * @method static array sendToDevice(string $token, FcmMessage $message)
 * @method static array sendToMultipleDevices(array $tokens, FcmMessage $message)
 * @method static array sendToMultipleDevicesWithCleanup(array $tokens, FcmMessage $message, $model = null)
 * @method static bool validateToken(string $token)
 * @method static array validateTokens(array $tokens)
 * 
 * @see \LaravelFcmNotifications\Services\FcmService
 */
class Fcm extends Facade
{
  /**
   * Get the registered name of the component.
   * 
   * @return string
   */
  protected static function getFacadeAccessor(): string
  {
    return 'fcm-notifications.service';
  }
}
