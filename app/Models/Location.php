<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One city or municipality, and the province it sits in.
 *
 * @property int $id
 * @property string $province
 * @property string $city
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['province', 'city', 'slug'])]
class Location extends Model
{
    /**
     * Every province with its cities, ready for two linked selects.
     *
     * @return list<array{province: string, cities: list<string>}>
     */
    public static function groupedByProvince(): array
    {
        return self::query()
            ->orderBy('province')
            ->orderBy('city')
            ->get(['province', 'city'])
            ->groupBy('province')
            ->map(fn (Collection $rows, string $province): array => [
                'province' => $province,
                'cities' => $rows->pluck('city')->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Whether this exact province and city pair is one we know about.
     *
     * Both halves are checked together on purpose: each is a real value on its
     * own, so "Bulacan / Cebu City" would pass two separate checks while being
     * a place that does not exist.
     */
    public static function pairExists(?string $province, ?string $city): bool
    {
        if (blank($province) || blank($city)) {
            return false;
        }

        return self::query()
            ->where('province', $province)
            ->where('city', $city)
            ->exists();
    }
}
