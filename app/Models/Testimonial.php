<?php

namespace App\Models;

use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A client's own words about working with a student team.
 *
 * Written by the business, shown on the landing page, and kept until the
 * business takes it down. Nothing else writes to this table — there is no
 * seeded or sample testimonial anywhere in the application.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string $body
 * @property string|null $author_title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $author
 */
#[Fillable(['team_id', 'user_id', 'body', 'author_title'])]
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    /**
     * Get the business the testimonial speaks for.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the staff member who wrote it, if they still have an account.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        /* Named for the role it plays, so the column has to be spelled out. */
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope to the testimonials the landing page may show.
     *
     * A business whose profile has been taken down or was never filled in has
     * no name to attribute the quote to, so it does not appear.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function publishable(Builder $query): void
    {
        $query->whereHas('team.clientProfile');
    }
}
