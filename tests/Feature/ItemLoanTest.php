<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\ItemLoan;
use App\Models\User;
use App\Services\Library\LibrarySettings;
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

    public function test_admin_can_view_active_and_returned_loans(): void
    {
        $activeItem = Item::factory()->film()->loaned()->create(['title' => 'Alien']);
        $returnedItem = Item::factory()->boardGame()->owned()->create(['title' => 'Azul']);

        ItemLoan::factory()->active()->create([
            'item_id' => $activeItem->id,
            'borrower_name' => 'Jamie',
            'loaned_at' => '2026-05-01',
            'expected_return_at' => now()->subDay()->toDateString(),
        ]);
        ItemLoan::factory()->returned()->create([
            'item_id' => $returnedItem->id,
            'borrower_name' => 'Morgan',
            'loaned_at' => '2026-04-20',
            'returned_at' => '2026-04-27',
        ]);

        $this->actingAs(User::query()->first())
            ->get(route('admin.loans.index'))
            ->assertOk()
            ->assertSee('Alien')
            ->assertSee('Jamie')
            ->assertSee(__('admin.loans.statuses.overdue'))
            ->assertSee('Azul')
            ->assertSee('Morgan')
            ->assertSee(__('admin.loans.statuses.returned'));
    }

    public function test_admin_can_create_a_loan(): void
    {
        $item = Item::factory()->film()->owned()->create(['title' => 'The Matrix']);

        $this->actingAs(User::query()->first())
            ->post(route('admin.loans.store'), [
                'item_id' => $item->id,
                'borrower_name' => 'Alex',
                'loaned_at' => '2026-05-12',
                'expected_return_at' => '2026-05-30',
                'notes' => 'Blu-ray case included.',
            ])
            ->assertRedirect(route('admin.loans.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('item_loans', [
            'item_id' => $item->id,
            'borrower_name' => 'Alex',
            'loaned_at' => '2026-05-12 00:00:00',
            'expected_return_at' => '2026-05-30 00:00:00',
            'returned_at' => null,
        ]);
        $this->assertSame(ItemStatus::Loaned, $item->refresh()->status);
    }

    public function test_admin_can_mark_a_loan_as_returned(): void
    {
        $item = Item::factory()->film()->loaned()->create(['title' => 'Blade Runner']);
        $loan = ItemLoan::factory()->active()->create([
            'item_id' => $item->id,
            'borrower_name' => 'Casey',
        ]);

        $this->actingAs(User::query()->first())
            ->patch(route('admin.loans.return', $loan))
            ->assertRedirect(route('admin.loans.index'))
            ->assertSessionHas('status');

        $this->assertNotNull($loan->refresh()->returned_at);
        $this->assertSame(ItemStatus::Owned, $item->refresh()->status);
    }

    public function test_admin_cannot_create_two_active_loans_for_the_same_item(): void
    {
        $item = Item::factory()->film()->loaned()->create(['title' => 'Dune']);

        ItemLoan::factory()->active()->create([
            'item_id' => $item->id,
            'borrower_name' => 'Taylor',
        ]);

        $this->actingAs(User::query()->first())
            ->from(route('admin.loans.index'))
            ->post(route('admin.loans.store'), [
                'item_id' => $item->id,
                'borrower_name' => 'Alex',
                'loaned_at' => '2026-05-12',
            ])
            ->assertRedirect(route('admin.loans.index'))
            ->assertSessionHasErrors('item_id');

        $this->assertSame(1, $item->itemLoans()->active()->count());
    }

    public function test_existing_loans_are_kept_when_loans_feature_is_disabled(): void
    {
        $item = Item::factory()->film()->loaned()->create(['title' => 'Tenet']);
        $loan = ItemLoan::factory()->active()->create([
            'item_id' => $item->id,
            'borrower_name' => 'Sam',
        ]);

        app(LibrarySettings::class)->setLoansEnabled(false);

        $this->actingAs(User::query()->first())
            ->get(route('admin.loans.index'))
            ->assertNotFound();

        $this->actingAs(User::query()->first())
            ->get(route('admin'))
            ->assertOk()
            ->assertDontSee(route('admin.loans.index'), false);

        $this->assertDatabaseHas('item_loans', [
            'id' => $loan->id,
            'item_id' => $item->id,
            'borrower_name' => 'Sam',
        ]);
    }
}
