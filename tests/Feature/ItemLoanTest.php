<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemLoan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ItemLoanTest extends TestCase
{
    private string $databasePath;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/shelfvault-loans.sqlite');
        $this->lockPath = storage_path('framework/testing/shelfvault-loans.lock');

        File::ensureDirectoryExists(dirname($this->databasePath));
        File::ensureDirectoryExists(dirname($this->lockPath));
        File::delete($this->databasePath);
        File::delete($this->lockPath);
        File::put($this->databasePath, '');

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
        File::delete($this->databasePath);
        File::delete($this->lockPath);

        parent::tearDown();
    }

    public function test_item_loan_belongs_to_an_item(): void
    {
        $item = Item::factory()->film()->create(['title' => 'Alien']);
        $loan = ItemLoan::factory()->active()->create([
            'item_id' => $item->id,
            'borrower_name' => 'Jamie',
            'loaned_at' => '2026-05-01',
        ]);

        $this->assertTrue($loan->item->is($item));
        $this->assertSame('Alien', $loan->item->title);
        $this->assertSame('Jamie', $loan->borrower_name);
        $this->assertTrue($loan->returned_at === null);
    }

    public function test_item_has_many_item_loans(): void
    {
        $item = Item::factory()->boardGame()->create(['title' => 'Terraforming Mars']);

        ItemLoan::factory()->active()->create([
            'item_id' => $item->id,
            'borrower_name' => 'Alex',
            'loaned_at' => '2026-05-02',
        ]);
        ItemLoan::factory()->returned()->create([
            'item_id' => $item->id,
            'borrower_name' => 'Morgan',
            'loaned_at' => '2026-04-20',
        ]);

        $this->assertCount(2, $item->itemLoans);
        $this->assertSame(1, $item->itemLoans()->active()->count());
        $this->assertSame(1, $item->itemLoans()->returned()->count());
    }

    public function test_item_loan_scopes_filter_active_and_returned_loans(): void
    {
        $item = Item::factory()->videoGame()->create(['title' => 'Zelda']);

        ItemLoan::factory()->active()->create([
            'item_id' => $item->id,
            'borrower_name' => 'Taylor',
            'loaned_at' => '2026-05-03',
        ]);
        ItemLoan::factory()->returned()->create([
            'item_id' => $item->id,
            'borrower_name' => 'Casey',
            'loaned_at' => '2026-04-10',
        ]);

        $this->assertSame(1, ItemLoan::active()->count());
        $this->assertSame(1, ItemLoan::returned()->count());
    }
}
