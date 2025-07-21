<?php

/**
 * Laravel FCM Notifications - Test Command
 * 
 * Test command for Firebase Cloud Messaging functionality.
 * 
 * @package LaravelFcmNotifications
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 */

namespace LaravelFcmNotifications\Console\Commands;

use LaravelFcmNotifications\Facades\Fcm;
use LaravelFcmNotifications\Services\FcmMessage;
use LaravelFcmNotifications\Notifications\FcmNotification;
use Illuminate\Console\Command;

/**
 * FCM Test Command
 * 
 * Provides comprehensive testing for FCM notification functionality including:
 * - Direct service usage testing
 * - Laravel notification system testing
 * - Multiple notification modes
 * - Token validation
 */
class TestFcmCommand extends Command
{
  /**
   * The name and signature of the console command.
   */
  protected $signature = 'fcm:test {--token= : FCM token to test with} {--direct : Send directly via service}';

  /**
   * The console command description.
   */
  protected $description = 'Test FCM notification sending functionality';

  /**
   * Execute the console command.
   * 
   * @return int Command exit code
   */
  public function handle(): int
  {
    $token = $this->option('token');
    $direct = $this->option('direct');

    if (!$token) {
      $token = $this->ask('Please enter an FCM token to test with');
    }

    if (!$token) {
      $this->error('FCM token is required for testing');
      return Command::FAILURE;
    }

    // Validate token format
    $validationResult = Fcm::validateToken($token);
    if (!$validationResult['valid']) {
      $this->error('Invalid FCM token: ' . $validationResult['message']);
      return Command::FAILURE;
    }

    $this->info("Testing FCM with token: " . substr($token, 0, 20) . '...');

    try {
      if ($direct) {
        $this->testDirectService($token);
      } else {
        $this->testViaNotificationSystem($token);
      }

      $this->info("FCM test completed successfully!");
      return Command::SUCCESS;
    } catch (\Exception $e) {
      $this->error("Failed to send FCM notification: " . $e->getMessage());
      return Command::FAILURE;
    }
  }

  /**
   * Test sending notifications directly via the service
   * 
   * @param string $token FCM token
   */
  protected function testDirectService(string $token): void
  {
    $this->info("Testing direct service usage...");

    // Test 1: Notification + Data mode
    $message = FcmMessage::create(
      'FCM Test - Direct Service',
      'This is a test notification from FCM service',
      null
    )
      ->addData('test_type', 'direct_service')
      ->addData('timestamp', (string) time())
      ->setAndroidPriority('high')
      ->setAndroidChannel('fcm_test')
      ->setIosBadge(1);

    $result = Fcm::sendToDevice($token, $message);
    $this->info("Direct send result: " . json_encode($result, JSON_PRETTY_PRINT));

    sleep(2);

    // Test 2: Data-only mode
    $dataMessage = FcmMessage::createDataOnly([
      'title' => 'Data Only Test',
      'body' => 'This is a data-only message',
      'test_type' => 'data_only',
      'timestamp' => (string) time(),
    ]);

    $result = Fcm::sendToDevice($token, $dataMessage);
    $this->info("Data-only send result: " . json_encode($result, JSON_PRETTY_PRINT));

    sleep(2);

    // Test 3: Notification-only mode
    $notificationMessage = FcmMessage::createNotificationOnly(
      'Notification Only Test',
      'This message shows only system notification',
      null
    );

    $result = Fcm::sendToDevice($token, $notificationMessage);
    $this->info("Notification-only send result: " . json_encode($result, JSON_PRETTY_PRINT));
  }

  /**
   * Test sending notifications via the notification system
   * 
   * @param string $token FCM token
   */
  protected function testViaNotificationSystem(string $token): void
  {
    $this->info("Testing via notification system...");

    // Create a mock notifiable object for testing
    $notifiable = new class {
      public function routeNotificationFor($driver)
      {
        return null; // Will use token from notification
      }
    };

    // Test 1: Default notification (notification + data)
    $this->info("1. Testing default notification mode");
    $notification = new FcmNotification(
      title: 'FCM Notification Test',
      body: 'This is a test notification via the notification system',
      data: ['type' => 'notification_test', 'timestamp' => (string) time()],
      token: $token
    );

    // Simulate sending (since we don't have a real notifiable model in this context)
    $message = $notification->toFcm($notifiable);
    $result = Fcm::sendToDevice($token, $message);
    $this->info("✅ Default notification sent: " . ($result['success'] ? 'Success' : 'Failed'));
    sleep(2);

    // Test 2: Data-only notification
    $this->info("2. Testing data-only mode");
    $dataNotification = (new FcmNotification(
      title: 'Data Only Title',
      body: 'Data Only Body',
      data: ['type' => 'data_only_test'],
      token: $token
    ))->dataOnly();

    $message = $dataNotification->toFcm($notifiable);
    $result = Fcm::sendToDevice($token, $message);
    $this->info("✅ Data-only notification sent: " . ($result['success'] ? 'Success' : 'Failed'));
    sleep(2);

    // Test 3: Notification-only
    $this->info("3. Testing notification-only mode");
    $notificationOnly = (new FcmNotification(
      title: 'Notification Only Test',
      body: 'This shows notification UI only',
      token: $token
    ))->notificationOnly();

    $message = $notificationOnly->toFcm($notifiable);
    $result = Fcm::sendToDevice($token, $message);
    $this->info("✅ Notification-only sent: " . ($result['success'] ? 'Success' : 'Failed'));
  }
}
