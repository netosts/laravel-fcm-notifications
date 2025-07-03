<?php

/**
 * Laravel FCM Notifications - Token Cleanup Test Command
 * 
 * Test command for FCM token cleanup functionality.
 * 
 * @package LaravelFcmNotifications
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 */

namespace LaravelFcmNotifications\Console\Commands;

use LaravelFcmNotifications\Events\UnregisteredFcmTokenDetected;
use LaravelFcmNotifications\Facades\Fcm;
use Illuminate\Console\Command;

/**
 * FCM Token Cleanup Test Command
 * 
 * Tests the automatic cleanup functionality for unregistered FCM tokens.
 */
class TestFcmCleanupCommand extends Command
{
  /**
   * The name and signature of the console command.
   */
  protected $signature = 'fcm:test-cleanup {token?}';

  /**
   * The console command description.
   */
  protected $description = 'Test FCM token cleanup functionality';

  /**
   * Execute the console command.
   * 
   * @return int Command exit code
   */
  public function handle(): int
  {
    $testToken = $this->argument('token') ?? 'test_unregistered_token_12345';

    $this->info("Testing FCM token cleanup with token: " . substr($testToken, 0, 20) . '...');

    try {
      // Test token validation
      $this->info("1. Testing token validation...");
      $isValid = Fcm::validateToken($testToken);
      $this->info("Token validation result: " . ($isValid ? 'Valid' : 'Invalid'));

      // Test batch validation
      $testTokens = [
        $testToken,
        'another_test_token_67890',
        'invalid_token_short'
      ];

      $validationResult = Fcm::validateTokens($testTokens);
      $this->info("Batch validation results:");
      $this->info("Valid tokens: " . count($validationResult['valid']));
      $this->info("Invalid tokens: " . count($validationResult['invalid']));

      // Test unregistered token event dispatch
      $this->info("2. Testing unregistered token event dispatch...");
      UnregisteredFcmTokenDetected::dispatch($testToken);

      $this->info("✅ Successfully dispatched unregistered token event");
      $this->info("Check your logs to see if the cleanup listener was executed");
      $this->info("The listener should attempt to remove this token from the database");

      return Command::SUCCESS;
    } catch (\Exception $e) {
      $this->error("❌ Failed to test token cleanup: " . $e->getMessage());
      return Command::FAILURE;
    }
  }
}
