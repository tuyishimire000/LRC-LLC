<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JournalPostingService
{
    public function createDraft(array $data, array $lines, ?int $userId = null): JournalEntry
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => 'A journal entry needs at least two lines.']);
        }

        $debits = 0;
        $credits = 0;
        foreach ($lines as $line) {
            $debit = (int) round((float) ($line['debit'] ?? 0) * 100);
            $credit = (int) round((float) ($line['credit'] ?? 0) * 100);
            if ($debit < 0 || $credit < 0 || (($debit > 0) === ($credit > 0))) {
                throw ValidationException::withMessages(['lines' => 'Each journal line must contain either a positive debit or a positive credit, never both.']);
            }
            LedgerAccount::query()->whereKey($line['ledger_account_id'])->where('is_active', true)->firstOrFail();
            $debits += $debit;
            $credits += $credit;
        }
        if ($debits <= 0 || $debits !== $credits) {
            throw ValidationException::withMessages(['lines' => 'Journal debits and credits must be equal and greater than zero.']);
        }

        return DB::transaction(function () use ($data, $lines, $userId) {
            $entry = JournalEntry::query()->create([
                'entry_number' => 'JE-'.Str::upper(Str::random(12)),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'status' => 'draft',
                'created_by' => $userId,
            ]);
            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }
            return $entry->load('lines.account');
        });
    }

    public function post(JournalEntry $entry, User $poster): JournalEntry
    {
        return DB::transaction(function () use ($entry, $poster) {
            $locked = JournalEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['entry' => 'Only draft journal entries can be posted.']);
            }
            if ((int) $locked->created_by === (int) $poster->id) {
                throw ValidationException::withMessages(['entry' => 'The journal preparer cannot post their own entry.']);
            }
            $lines = $locked->lines()->lockForUpdate()->get();
            $debits = $lines->sum(fn ($line) => (int) round((float) $line->debit * 100));
            $credits = $lines->sum(fn ($line) => (int) round((float) $line->credit * 100));
            if ($lines->count() < 2 || $debits <= 0 || $debits !== $credits) {
                throw ValidationException::withMessages(['entry' => 'This journal entry is not balanced and cannot be posted.']);
            }
            $locked->update(['status' => 'posted', 'posted_by' => $poster->id, 'posted_at' => now()]);
            return $locked->fresh('lines.account');
        });
    }
}
