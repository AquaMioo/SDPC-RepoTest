<?php

namespace App\Models;

use App\Enums\AddendumPaymentStatus;
use Database\Factories\AddendumPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One of the addendum's two milestones, and how it was paid.
 *
 * Milestone 1 is the down payment, Milestone 2 the final balance. Amounts are
 * centavos. Once paid the row is the audit trail: SDPC's invoice number beside
 * the gateway's payment id (pay_xxxx).
 *
 * @property int $id
 * @property int $addendum_id
 * @property int $milestone
 * @property int $percentage
 * @property int $amount
 * @property AddendumPaymentStatus $status
 * @property string $invoice_number
 * @property string|null $gateway
 * @property string|null $checkout_session_id
 * @property string|null $provider_payment_id
 * @property string|null $payment_method
 * @property int|null $paid_by
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Addendum $addendum
 * @property-read User|null $payer
 */
#[Fillable([
    'addendum_id', 'milestone', 'percentage', 'amount', 'status', 'invoice_number',
    'gateway', 'checkout_session_id', 'provider_payment_id', 'payment_method',
    'paid_by', 'paid_at',
])]
class AddendumPayment extends Model
{
    /** @use HasFactory<AddendumPaymentFactory> */
    use HasFactory;

    /**
     * Get the addendum the milestone belongs to.
     *
     * @return BelongsTo<Addendum, $this>
     */
    public function addendum(): BelongsTo
    {
        return $this->belongsTo(Addendum::class);
    }

    /**
     * Get the client account that paid it.
     *
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Get the milestone's name, as Section IV prints it.
     */
    public function label(): string
    {
        return $this->milestone === 1
            ? __('Milestone 1 · Mobilization Downpayment')
            : __('Milestone 2 · Full Payment (Final Balance)');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AddendumPaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }
}
