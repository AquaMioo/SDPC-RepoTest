<?php

namespace App\Enums;

enum PaymentWallet: string
{
    /** Chosen but not yet named — the ledger records the amount regardless. */
    case Unset = 'unset';

    case GCash = 'gcash';
    case Maya = 'maya';
    case BankTransfer = 'bank_transfer';

    /**
     * Get the display label for the wallet.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unset => 'Not specified',
            self::GCash => 'GCash',
            self::Maya => 'Maya',
            self::BankTransfer => 'Bank transfer',
        };
    }

    /**
     * Get every wallet value, e.g. for validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
