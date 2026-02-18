<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealImage extends Model
{
    /** @use HasFactory<\Database\Factories\MealImageFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'meal_images';

    /**
     * Maximum number of images per meal.
     *
     * BR-198: Maximum 3 images per meal.
     */
    public const MAX_IMAGES = 3;

    /**
     * Maximum file size in kilobytes (2MB).
     *
     * BR-200: Maximum file size: 2MB per image.
     */
    public const MAX_FILE_SIZE_KB = 2048;

    /**
     * Accepted MIME types.
     *
     * BR-199: Accepted formats: jpg/jpeg, png, webp.
     */
    public const ACCEPTED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Accepted file extensions for validation messages.
     */
    public const ACCEPTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Maximum dimensions for processed images.
     *
     * BR-201: Images are resized/optimized on upload (maintain aspect ratio).
     */
    public const MAX_WIDTH = 1200;

    public const MAX_HEIGHT = 900;

    /**
     * Thumbnail dimensions.
     *
     * BR-202: A thumbnail version is generated for meal cards.
     */
    public const THUMB_WIDTH = 400;

    public const THUMB_HEIGHT = 300;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'meal_id',
        'path',
        'thumbnail_path',
        'position',
        'original_filename',
        'mime_type',
        'file_size',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'file_size' => 'integer',
        ];
    }

    /**
     * Get the meal that owns this image.
     */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    /**
     * Scope: order by position ascending.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }

    /**
     * Get the full URL for the processed image.
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->path);
    }

    /**
     * Get the full URL for the thumbnail.
     */
    public function getThumbnailUrlAttribute(): string
    {
        if ($this->thumbnail_path) {
            return asset('storage/'.$this->thumbnail_path);
        }

        return $this->url;
    }

    /**
     * Get the human-readable file size.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
