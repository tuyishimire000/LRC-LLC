<?php

namespace Tests\Feature;

use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JournalPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::query()->create([
            'name' => 'Accounting staff',
            'email' => $email,
            'password' => 'a-secure-test-password',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function it_posts_a_balanced_journal_only_after_a_second_user_approves_it(): void
    {
        $cash = LedgerAccount::query()->create([
            'account_code' => 'T100', 'name' => 'Test cash', 'account_type' => 'asset',
            'normal_balance' => 'debit', 'is_active' => true,
        ]);
        $income = LedgerAccount::query()->create([
            'account_code' => 'T400', 'name' => 'Test income', 'account_type' => 'income',
            'normal_balance' => 'credit', 'is_active' => true,
        ]);
        $preparer = $this->user('journal-preparer@example.test');
        $poster = $this->user('journal-poster@example.test');
        $service = app(JournalPostingService::class);

        $entry = $service->createDraft([
            'entry_date' => '2026-11-11',
            'description' => 'Test balanced journal',
        ], [
            ['ledger_account_id' => $cash->id, 'debit' => '25000.00', 'credit' => '0.00'],
            ['ledger_account_id' => $income->id, 'debit' => '0.00', 'credit' => '25000.00'],
        ], $preparer->id);

        $this->expectException(ValidationException::class);
        try {
            $service->post($entry, $preparer);
        } catch (ValidationException $exception) {
            $this->assertSame('draft', $entry->fresh()->status);
            $posted = $service->post($entry, $poster);
            $this->assertSame('posted', $posted->status);
            throw $exception;
        }
    }

    #[Test]
    public function it_rejects_unbalanced_journals(): void
    {
        $account = LedgerAccount::query()->create([
            'account_code' => 'T200', 'name' => 'Test liability', 'account_type' => 'liability',
            'normal_balance' => 'credit', 'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        app(JournalPostingService::class)->createDraft([
            'entry_date' => '2026-11-11',
            'description' => 'Unbalanced test',
        ], [
            ['ledger_account_id' => $account->id, 'debit' => '100.00', 'credit' => '0.00'],
            ['ledger_account_id' => $account->id, 'debit' => '0.00', 'credit' => '90.00'],
        ]);
    }
}
