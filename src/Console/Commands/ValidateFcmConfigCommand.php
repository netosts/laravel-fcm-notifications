<?php

/**
 * Laravel FCM Notifications - Configuration Validation Command
 * 
 * Console command to validate FCM configuration and help debug setup issues.
 * 
 * @package LaravelFcmNotifications
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 */

namespace LaravelFcmNotifications\Console\Commands;

use Illuminate\Console\Command;
use LaravelFcmNotifications\Services\FcmService;
use Exception;

/**
 * Console command to validate FCM configuration
 */
class ValidateFcmConfigCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'fcm:validate-config {--show-sensitive : Show sensitive configuration values (DANGEROUS)}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Validate FCM configuration and test connection';

  /**
   * FCM Service instance
   */
  protected FcmService $fcmService;

  /**
   * Create a new command instance.
   */
  public function __construct(FcmService $fcmService)
  {
    parent::__construct();
    $this->fcmService = $fcmService;
  }

  /**
   * Execute the console command.
   */
  public function handle(): int
  {
    $this->info('🔍 Validating FCM Configuration...');
    $this->newLine();

    $configValid = true;

    // Check environment variables
    $this->info('📋 Checking Environment Variables:');
    $envVars = [
      'FCM_PROJECT_ID' => env('FCM_PROJECT_ID'),
      'FCM_CLIENT_EMAIL' => env('FCM_CLIENT_EMAIL'),
      'FCM_PRIVATE_KEY' => env('FCM_PRIVATE_KEY'),
    ];

    foreach ($envVars as $key => $value) {
      if (empty($value)) {
        $this->error("   ❌ {$key} is not set");
        $configValid = false;
      } else {
        if ($this->option('show-sensitive')) {
          $displayValue = $key === 'FCM_PRIVATE_KEY' ? $this->maskPrivateKey($value) : $value;
          $this->info("   ✅ {$key} = {$displayValue}");
        } else {
          $this->info("   ✅ {$key} is set");
        }
      }
    }

    $this->newLine();

    // Check configuration values
    $this->info('⚙️  Checking Configuration Values:');
    $configValues = [
      'project_id' => config('fcm-notifications.project_id'),
      'client_email' => config('fcm-notifications.client_email'),
      'private_key' => config('fcm-notifications.private_key'),
      'base_url' => config('fcm-notifications.base_url'),
      'oauth_url' => config('fcm-notifications.oauth_url'),
      'timeout' => config('fcm-notifications.timeout'),
      'auto_cleanup_tokens' => config('fcm-notifications.auto_cleanup_tokens'),
    ];

    foreach ($configValues as $key => $value) {
      if (empty($value) && !in_array($key, ['timeout', 'auto_cleanup_tokens'])) {
        $this->error("   ❌ fcm-notifications.{$key} is not configured");
        $configValid = false;
      } else {
        $displayValue = $value;
        if (in_array($key, ['private_key']) && !$this->option('show-sensitive')) {
          $displayValue = '[HIDDEN]';
        } elseif ($key === 'private_key' && $this->option('show-sensitive')) {
          $displayValue = $this->maskPrivateKey($value);
        } elseif (is_bool($value)) {
          $displayValue = $value ? 'true' : 'false';
        }
        $this->info("   ✅ fcm-notifications.{$key} = {$displayValue}");
      }
    }

    $this->newLine();

    if (!$configValid) {
      $this->error('❌ Configuration validation failed!');
      $this->newLine();
      $this->comment('💡 Make sure to set all required environment variables:');
      $this->comment('   - FCM_PROJECT_ID (your Firebase project ID)');
      $this->comment('   - FCM_CLIENT_EMAIL (service account email)');
      $this->comment('   - FCM_PRIVATE_KEY (service account private key)');
      $this->newLine();
      $this->comment('💡 You can get these values from your Firebase service account JSON file.');
      return 1;
    }

    // Test private key format
    $this->info('🔐 Validating Private Key Format:');
    try {
      $privateKey = config('fcm-notifications.private_key');
      if (!openssl_pkey_get_private($privateKey)) {
        $this->error('   ❌ Private key format is invalid');
        $this->comment('   💡 Make sure the private key includes the full PEM format with headers');
        $configValid = false;
      } else {
        $this->info('   ✅ Private key format is valid');
      }
    } catch (Exception $e) {
      $this->error('   ❌ Private key validation failed: ' . $e->getMessage());
      $configValid = false;
    }

    $this->newLine();

    // Test email format
    $this->info('📧 Validating Client Email Format:');
    $clientEmail = config('fcm-notifications.client_email');
    if (!filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
      $this->error('   ❌ Client email format is invalid');
      $configValid = false;
    } else {
      $this->info('   ✅ Client email format is valid');
    }

    $this->newLine();

    // Test project ID format
    $this->info('🆔 Validating Project ID Format:');
    $projectId = config('fcm-notifications.project_id');
    if (!preg_match('/^[a-z0-9-]+$/', $projectId)) {
      $this->error('   ❌ Project ID format is invalid (should be lowercase alphanumeric with hyphens)');
      $configValid = false;
    } else {
      $this->info('   ✅ Project ID format is valid');
    }

    $this->newLine();

    if (!$configValid) {
      $this->error('❌ Configuration validation failed!');
      return 1;
    }

    // Test connection to FCM
    $this->info('🌐 Testing FCM Connection:');
    try {
      // Create a test message
      $testMessage = new \LaravelFcmNotifications\Services\FcmMessage();
      $testMessage->setData(['test' => 'config_validation']);

      // Try to validate with a dummy token (this will fail, but we can check the error type)
      $result = $this->fcmService->validateToken('dummy_token_for_config_test');

      if (isset($result['error_type'])) {
        // If we get a specific error type, it means the connection worked
        if (in_array($result['error_type'], ['invalid_token', 'invalid_argument'])) {
          $this->info('   ✅ Successfully connected to FCM API');
          $this->comment('   💡 Authentication is working (tested with dummy token)');
        } else {
          $this->warn('   ⚠️  Connected to FCM but got unexpected error: ' . $result['error_type']);
        }
      } else {
        $this->warn('   ⚠️  Could not determine connection status');
      }
    } catch (Exception $e) {
      $this->error('   ❌ Failed to connect to FCM: ' . $e->getMessage());

      if (strpos($e->getMessage(), 'authentication') !== false || strpos($e->getMessage(), 'unauthorized') !== false) {
        $this->comment('   💡 This looks like an authentication issue. Check your service account credentials.');
      } elseif (strpos($e->getMessage(), 'network') !== false || strpos($e->getMessage(), 'timeout') !== false) {
        $this->comment('   💡 This looks like a network issue. Check your internet connection.');
      }

      $configValid = false;
    }

    $this->newLine();

    if ($configValid) {
      $this->info('🎉 FCM Configuration is valid and working!');
      $this->newLine();
      $this->comment('💡 You can now use the FCM notification service.');
      $this->comment('💡 Test sending a notification with: php artisan fcm:test');
      return 0;
    } else {
      $this->error('❌ FCM Configuration validation failed!');
      return 1;
    }
  }

  /**
   * Mask private key for display
   */
  protected function maskPrivateKey(string $privateKey): string
  {
    $lines = explode("\n", $privateKey);
    $maskedLines = [];

    foreach ($lines as $line) {
      if (strpos($line, '-----') === 0) {
        // Keep header/footer lines
        $maskedLines[] = $line;
      } elseif (!empty(trim($line))) {
        // Mask content lines
        $maskedLines[] = substr($line, 0, 10) . str_repeat('*', max(0, strlen($line) - 20)) . substr($line, -10);
      } else {
        $maskedLines[] = $line;
      }
    }

    return implode("\n", $maskedLines);
  }
}
