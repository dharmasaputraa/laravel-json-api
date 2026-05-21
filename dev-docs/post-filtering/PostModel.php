<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'body', 'excerpt',
        'status', 'is_featured', 'views_count', 'likes_count',
        'published_at', 'user_id', 'category_id',
    ];

    protected $casts = [
        'is_featured'  => 'boolean',
        'published_at' => 'datetime',
        'views_count'  => 'integer',
        'likes_count'  => 'integer',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }

    // ─────────────────────────────────────────────────────────────
    // Query Scopes
    // (Spatie AllowedFilter::scope() calls these automatically)
    // ─────────────────────────────────────────────────────────────

    /**
     * filter[published_from]=2024-01-01
     */
    public function scopePublishedFrom(Builder $query, string $date): Builder
    {
        return $query->whereDate('published_at', '>=', $date);
    }

    /**
     * filter[published_to]=2024-12-31
     */
    public function scopePublishedTo(Builder $query, string $date): Builder
    {
        return $query->whereDate('published_at', '<=', $date);
    }

    /**
     * filter[tags]=php,laravel   — comma-separated slugs or array
     */
    public function scopeTags(Builder $query, string|array $slugs): Builder
    {
        $list = is_array($slugs) ? $slugs : explode(',', $slugs);

        return $query->whereHas('tags', fn (Builder $q) => $q->whereIn('slug', $list));
    }

    /**
     * filter[search]=some keyword
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'LIKE', "%{$term}%")
              ->orWhere('body', 'LIKE', "%{$term}%")
              ->orWhere('excerpt', 'LIKE', "%{$term}%");
        });
    }

    /**
     * filter[min_views]=100
     */
    public function scopeMinViews(Builder $query, int $min): Builder
    {
        return $query->where('views_count', '>=', $min);
    }
}
