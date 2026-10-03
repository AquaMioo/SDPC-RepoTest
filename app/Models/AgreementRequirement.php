<?php

namespace App\Models;

use App\Enums\MemorandumSection;
use Database\Factories\AgreementRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry a party added to the Memorandum of Agreement.
 *
 * Appended after the section's base wording, on screen and on the printed
 * copy. In Section VII ('services') the title is the Objective and the body
 * the Scope; elsewhere there is only the body. Only its author may change or
 * remove it, and only until somebody signs (AgreementPolicy::changeRequirement).
 *
 * @property int $id
 * @property int $agreement_id
 * @property MemorandumSection $section
 * @property string|null $title
 * @property int|null $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agreement $agreement
 * @property-read User|null $author
 */
#[Fillable(['agreement_id', 'section', 'title', 'user_id', 'body'])]
class AgreementRequirement extends Model
{
    /** @use HasFactory<AgreementRequirementFactory> */
    use HasFactory;

    /**
     * Get the agreement the requirement is part of.
     *
     * @return BelongsTo<Agreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /**
     * Get the person who added it.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
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
            'section' => MemorandumSection::class,
        ];
    }
}
