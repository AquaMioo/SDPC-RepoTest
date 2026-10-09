<?php

namespace App\Models;

use Database\Factories\AddendumServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One extended service in the addendum's Section II: an Objective and its
 * Scope, written by either party and changed only by its author.
 *
 * @property int $id
 * @property int $addendum_id
 * @property int|null $user_id
 * @property string $objective
 * @property string $scope
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Addendum $addendum
 * @property-read User|null $author
 */
#[Fillable(['addendum_id', 'user_id', 'objective', 'scope'])]
class AddendumService extends Model
{
    /** @use HasFactory<AddendumServiceFactory> */
    use HasFactory;

    /**
     * Get the addendum the service belongs to.
     *
     * @return BelongsTo<Addendum, $this>
     */
    public function addendum(): BelongsTo
    {
        return $this->belongsTo(Addendum::class);
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
}
