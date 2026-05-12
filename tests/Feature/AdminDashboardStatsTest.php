<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemLoan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminDashboardStatsTest extends TestCase
{
    private string $defaultLockPath;

    private string $databasePath;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLockPath = storage_path('app/shelfvault/installed.lock');
        $this->databasePath = storage_path('framework/testing/shelfvault-dashboard.sqlite');
        $this->lockPath = storage_path('framework/testing/shelfvault-dashboard.lock');

        File::delete($this->defaultLockPath);
        File::ensureDirectoryExists(dirname($this->databasePath));
        File::ensureDirectoryExists(dirname($this->lockPath));
        File::delete($this->databasePath);
        File::delete($this->lockPath);
        File::put($this->databasePath, '');
        File::put($this->lockPath, now()->toIso8601String());

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'session.driver' => 'file',
            'session.files' => storage_path('framework/testing/sessions'),
            'shelfvault.installer.lock_path' => $this->lockPath,
        ]);

        File::ensureDirectoryExists(config('session.files'));

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->artisan('migrate', [
            '--database' => 'sqlite',
            '--force' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        User::query()->create([
            'name' => 'Admin',
            'login' => 'admin',
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery',
            'preferred_locale' => 'en',
        ]);
    }

    protected function tearDown(): void
    {
        File::delete($this->defaultLockPath);
        File::delete($this->databasePath);
        File::delete($this->lockPath);

        parent::tearDown();
    }

    public function test_dashboard_uses_real_item_and_loan_counts(): void
    {
        Item::factory()->film()->owned()->create(['title' => 'Recent Film']);
        Item::factory()->film()->loaned()->create(['title' => 'Loaned Film']);
        Item::factory()->videoGame()->owned()->create(['title' => 'Recent Game']);
        Item::factory()->boardGame()->archived()->create(['title' => 'Archived Game']);

        $oldItem = Item::factory()->boardGame()->owned()->create(['title' => 'Old Game']);
        $oldItem->forceFill([
            'created_at' => now()->subDays(45),
            'updated_at' => now()->subDays(45),
        ])->save();

        ItemLoan::factory()->active()->create([
            'item_id' => Item::film()->first()->id,
            'borrower_name' => 'Pat',
            'loaned_at' => '2026-05-01',
        ]);
        ItemLoan::factory()->returned()->create([
            'item_id' => Item::videoGame()->first()->id,
            'borrower_name' => 'Sam',
            'loaned_at' => '2026-04-15',
        ]);

        $admin = User::query()->first();
        $admin->forceFill(['preferred_locale' => 'en'])->save();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertViewHas('stats', function (array $stats): bool {
                $statsByKey = collect($stats)->keyBy('key');

                return (int) $statsByKey->get('total_items')['value'] === 5
                    && (int) $statsByKey->get('films')['value'] === 2
                    && (int) $statsByKey->get('video_games')['value'] === 1
                    && (int) $statsByKey->get('board_games')['value'] === 2
                    && (int) $statsByKey->get('loans')['value'] === 1
                    && (int) $statsByKey->get('recent_additions')['value'] === 4;
            })
            ->assertDontSee('Wishlist');
    }
}
