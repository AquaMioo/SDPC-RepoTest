<?php

namespace App\Models;

use App\Enums\SiteContentKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property SiteContentKey $key
 * @property string|null $body
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $editor
 */
#[Fillable(['key', 'body', 'updated_by'])]
class SiteContent extends Model
{
    /**
     * Get every block keyed by its name, including ones never saved.
     *
     * The screen renders all three fields whether or not they exist yet, so a
     * missing row reads as empty copy rather than a missing key.
     *
     * @return array<string, string|null>
     */
    public static function allKeyed(): array
    {
        $stored = self::query()->pluck('body', 'key')->all();

        $blocks = [];

        foreach (SiteContentKey::cases() as $key) {
            $blocks[$key->value] = $stored[$key->value] ?? null;
        }

        return $blocks;
    }

    /**
     * Get the administrator who last saved this block.
     *
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => SiteContentKey::class,
        ];
    }
}
