<?php

/**
 * Laravel FCM Notifications - Token Validation Command
 * 
 * Console command to validate and clean up FCM tokens that have sender ID mismatches
 * or other issues.
 * 
 * @package LaravelFcmNotifications
 * @author Neto Santos <netostt91@gmail.com>
 * @license MIT
 */

namespace LaravelFcmNotifications\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LaravelFcmNotifications\Services\FcmService;
use Exception;

/**
 * Console command to validate FCM tokens and clean up invalid ones
 */
class ValidateFcmTokensCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'fcm:validate-tokens 
                         {--dry-run : Show what would be cleaned up without actually deleting}
                         {--table=notification_tokens : Table name containing FCM tokens}
                         {--token-column=token : Column name containing FCM tokens}
                         {--limit=100 : Maximum number of tokens to validate per batch}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Validate FCM tokens and clean up invalid ones (including sender ID mismatches)';

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
    $tableName = $this->option('table');
    $tokenColumn = $this->option('token-column');
    $limit = (int) $this->option('limit');
    $isDryRun = $this->option('dry-run');

    $this->info('🔍 Starting FCM token validation...');
    $this->newLine();

    // Check if table exists
    if (!$this->tableExists($tableName)) {
      $this->error("❌ Table '{$tableName}' does not exist.");
      $this->comment("💡 Use --table option to specify the correct table name.");
      return 1;
    }

    // Get total token count
    $totalTokens = DB::table($tableName)->whereNotNull($tokenColumn)->count();

    if ($totalTokens === 0) {
      $this->info('✅ No FCM tokens found to validate.');
      return 0;
    }

    $this->info("📊 Found {$totalTokens} tokens to validate");
    $this->newLine();

    $validated = 0;
    $invalidTokens = [];
    $senderMismatchTokens = [];

    // Process tokens in batches
    DB::table($tableName)
      ->whereNotNull($tokenColumn)
      ->orderBy('id')
      ->chunk($limit, function ($tokens) use (&$validated, &$invalidTokens, &$senderMismatchTokens, $tokenColumn) {
        $tokenList = $tokens->pluck($tokenColumn)->toArray();

        $this->info("🔄 Validating batch of " . count($tokenList) . " tokens...");

        $results = $this->fcmService->validateTokens($tokenList);

        foreach ($results as $result) {
          $validated++;

          if (!$result['valid']) {
            $invalidTokens[] = $result;

            if (isset($result['error_type']) && $result['error_type'] === 'sender_mismatch') {
              $senderMismatchTokens[] = $result['token'];
            }
          }
        }

        $this->comment("✓ Validated {$validated} tokens so far...");
      });

    $this->newLine();

    // Display results
    $validTokens = $validated - count($invalidTokens);
    $this->info("📈 Validation Results:");
    $this->table(
      ['Status', 'Count', 'Percentage'],
      [
        ['✅ Valid tokens', $validTokens, round(($validTokens / $validated) * 100, 1) . '%'],
        ['❌ Invalid tokens', count($invalidTokens), round((count($invalidTokens) / $validated) * 100, 1) . '%'],
        ['🔄 Sender ID mismatches', count($senderMismatchTokens), round((count($senderMismatchTokens) / $validated) * 100, 1) . '%'],
      ]
    );

    $this->newLine();

    if (!empty($invalidTokens)) {
      $this->warn("🚨 Found " . count($invalidTokens) . " invalid tokens:");

      // Group by error type
      $errorGroups = [];
      foreach ($invalidTokens as $invalid) {
        $errorType = $invalid['error_type'] ?? 'unknown';
        $errorGroups[$errorType] = ($errorGroups[$errorType] ?? 0) + 1;
      }

      foreach ($errorGroups as $errorType => $count) {
        $this->comment("   • {$errorType}: {$count} tokens");
      }

      $this->newLine();

      if (!$isDryRun) {
        if ($this->confirm('🗑️  Do you want to delete all invalid tokens?', false)) {
          $this->cleanupInvalidTokens($tableName, $tokenColumn, $invalidTokens);
        }
      } else {
        $this->comment("🔍 DRY RUN: Would delete " . count($invalidTokens) . " invalid tokens");
      }
    } else {
      $this->info("🎉 All tokens are valid!");
    }

    return 0;
  }

  /**
   * Check if the specified table exists
   */
  protected function tableExists(string $tableName): bool
  {
    try {
      return DB::getSchemaBuilder()->hasTable($tableName);
    } catch (\Exception $e) {
      return false;
    }
  }

  /**
   * Clean up invalid tokens from the database
   */
  protected function cleanupInvalidTokens(string $tableName, string $tokenColumn, array $invalidTokens): void
  {
    $tokenList = array_column($invalidTokens, 'token');

    $deletedCount = DB::table($tableName)
      ->whereIn($tokenColumn, $tokenList)
      ->delete();

    $this->info("🗑️  Deleted {$deletedCount} invalid tokens from database");

    if (count($invalidTokens) > 0) {
      $this->comment("💡 Your mobile app users will need to re-register for push notifications");
      $this->comment("💡 Make sure your Firebase configuration is correct to prevent future sender ID mismatches");
    }
  }
}
