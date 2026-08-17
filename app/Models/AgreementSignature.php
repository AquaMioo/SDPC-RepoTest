<?php

namespace App\Models;

use App\Enums\AgreementParty;
use Database\Factories\AgreementSignatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person putting their name to one version of an agreement.
 *
 * Append-only. Nothing in the application updates or deletes a signature —
 * a change request supersedes the agreement and the new version collects its
 * own, which keeps the log readable as history.
 *
 * @property int $id
 * @property int $agreement_id
 * @property int $user_id
 * @property AgreementParty $party
 * @property string $signed_name
 * @property list<string> $acknowledgements
 * @property Carbon $signed_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agreement $agreement
 * @property-read User $signatory
 */
#[Fillable([
    'agreement_id', 'user_id', 'party', 'signed_name', 'acknowledgements',
    'signed_at', 'ip_address', 'user_agent',
])]
class AgreementSignature extends Model
{
    /** @use HasFactory<AgreementSignatureFactory> */
    use HasFactory;

    /**
     * Get the agreement this signature is against.
     *
     * @return BelongsTo<Agreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /**
     * Get the person who signed.
     *
     * @return BelongsTo<User, $this>
     */
    public function signatory(): BelongsTo
    {
        /* Named for the role it plays, so the column has to be spelled out. */
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'party' => AgreementParty::class,
            'acknowledgements' => 'array',
            'signed_at' => 'datetime',
        ];
    }
}
