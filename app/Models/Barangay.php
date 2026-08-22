<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One barangay of a served city.
 *
 * @property int $id
 * @property int $location_id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Location $location
 */
#[Fillable(['location_id', 'name', 'slug'])]
class Barangay extends Model
{
    /**
     * The city this barangay belongs to.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Every barangay name, alphabetically, for a select.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return self::query()
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();
    }
}
