<?php

namespace App\Models\Wallet;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WalletTransfer extends Model
{
    protected $fillable = [
        'reference', 'from_wallet_id', 'to_wallet_id', 'from_user_id', 'to_user_id',
        'amount', 'currency', 'status', 'debit_entry_id', 'credit_entry_id',
        'idempotency_key', 'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function fromUser() { return $this->belongsTo(User::class, 'from_user_id'); }
    public function toUser() { return $this->belongsTo(User::class, 'to_user_id'); }
    public function debitEntry() { return $this->belongsTo(LedgerEntry::class, 'debit_entry_id'); }
    public function creditEntry() { return $this->belongsTo(LedgerEntry::class, 'credit_entry_id'); }
}
