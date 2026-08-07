<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Database\Factories\ClientProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $business_name
 * @property string|null $business_description
 * @property string|null $owner_name
 * @property string|null $logo_path
 * @property string|null $address
 * @property string|null $city
 * @property string|null $province
 * @property string|null $phone_number
 * @property string|null $contact_email
 * @property string|null $website_url
 * @property string|null $facebook_url
 * @property string|null $permit_path
 * @property VerificationStatus $verification_status
 * @property Carbon|null $verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable([
    'team_id', 'business_name', 'business_description', 'owner_name',
    'logo_path', 'address', 'city', 'province', 'phone_number',
    'contact_email', 'website_url', 'facebook_url', 'permit_path',
    'verification_status', 'verified_at',
])]
class ClientProfile extends Model
{
    /** @use HasFactory<ClientProfileFactory> */
    use HasFactory;

    /**
     * The fields that count toward the profile completion meter, in the order
     * the design's profile screen presents them.
     *
     * @var list<string>
     */
    public const COMPLETION_FIELDS = [
        'business_name',
        'business_description',
        'owner_name',
        'logo_path',
        'address',
        'city',
        'province',
        'phone_number',
        'contact_email',
        'website_url',
        'permit_path',
    ];

    /**
     * Get the team the profile belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get how complete the business profile is, as a whole percentage.
     */
    public function completionPercentage(): int
    {
        $filled = collect(self::COMPLETION_FIELDS)
            ->filter(fn (string $field) => filled($this->{$field}))
            ->count();

        return (int) round($filled / count(self::COMPLETION_FIELDS) * 100);
    }

    /**
     * Determine if the business has been verified by an administrator.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Verified;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }
}
