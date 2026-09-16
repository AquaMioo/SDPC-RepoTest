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
     * The barangays of every served city, ready for a select that follows the
     * chosen province and city.
     *
     * @return list<array{province: string, city: string, barangays: list<string>}>
     */
    public static function groupedByLocation(): array
    {
        return Location::query()
            ->whereHas('barangays')
            ->with(['barangays' => fn ($query) => $query->orderBy('name')])
            ->orderBy('province')
            ->orderBy('city')
            ->get()
            ->map(fn (Location $location): array => [
                'province' => $location->province,
                'city' => $location->city,
                'barangays' => $location->barangays->pluck('name')->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Whether a barangay of that name belongs to that province and city.
     *
     * Checked as one question for the same reason Location::pairExists() is:
     * "Muzon" is a real barangay, but not of every city.
     */
    public static function existsIn(?string $province, ?string $city, ?string $name): bool
    {
        if (blank($province) || blank($city) || blank($name)) {
            return false;
        }

        return self::query()
            ->where('name', $name)
            ->whereHas('location', fn ($query) => $query
                ->where('province', $province)
                ->where('city', $city))
            ->exists();
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
