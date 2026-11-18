<?php

namespace App\Models;

use App\Services\MediaLifecycle;
use App\Services\MediaTracker;
use App\Support\ContentRegistry;
use App\Support\HtmlContent;
use App\Support\SlugGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class ContentModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public static string $sourceTable;

    protected static function booted(): void
    {
        static::saving(function (ContentModel $record): void {
            $module = ContentRegistry::get($record::$sourceTable);
            $fields = array_column($module['columns'], 'name');
            if ($record->exists && $module['route'] && $record->isDirty('slug') && filled($record->getRawOriginal('slug'))) {
                DB::table('content_redirects')->updateOrInsert(
                    ['old_path' => '/'.$module['route'].'/'.$record->getRawOriginal('slug')],
                    ['source_table' => static::$sourceTable, 'record_id' => $record->id, 'created_at' => now(), 'updated_at' => now()],
                );
            }
            if (static::$sourceTable === 'pastoral_galeria_foto' && $record->gallery_id) {
                $record->pastoral_group_id = PastoralGallery::find($record->gallery_id)?->pastoral_group_id ?: $record->pastoral_group_id;
            }
            if (! $record->exists) {
                if (in_array('is_active', $fields, true) && $record->getAttribute('is_active') === null) {
                    $record->is_active = true;
                }
                foreach (['visits', 'downloads'] as $counter) {
                    if (in_array($counter, $fields, true) && $record->getAttribute($counter) === null) {
                        $record->setAttribute($counter, 0);
                    }
                }
            }
            if (in_array('slug', $fields, true) && blank($record->slug)) {
                $record->slug = SlugGenerator::unique(static::class, (string) ($record->getAttribute($module['title']) ?: Str::random(12)), $record);
            }
            if (in_array('created_by', $fields, true) && ! $record->exists && ! $record->created_by) {
                $record->created_by = auth()->id();
            }
        });
        static::updated(fn (ContentModel $record) => app(MediaLifecycle::class)->changed($record));
        static::deleting(fn (ContentModel $record) => app(MediaLifecycle::class)->deleteGalleryPhotos($record));
        static::deleted(fn (ContentModel $record) => app(MediaLifecycle::class)->deleted($record));
        static::restored(fn (ContentModel $record) => DB::connection($record->getConnectionName())->afterCommit(fn () => app(MediaLifecycle::class)->restored($record)));
        static::saved(fn (ContentModel $record) => app(MediaTracker::class)->track($record));
    }

    public function scopePublished(Builder $query): Builder
    {
        $fields = array_column(ContentRegistry::get(static::$sourceTable)['columns'], 'name');
        if (in_array('is_active', $fields, true)) {
            $query->where('is_active', true);
        }
        if (static::$sourceTable === 'noticia') {
            $query->where('created_at', '<=', now());
        }

        return $query;
    }

    public function getDisplayTitleAttribute(): string
    {
        $field = ContentRegistry::get(static::$sourceTable)['title'];

        return (string) ($this->getAttribute($field) ?: 'Registro '.$this->getKey());
    }

    public function getPublicUrlAttribute(): ?string
    {
        $route = ContentRegistry::get(static::$sourceTable)['route'];

        return $route && $this->slug ? url($route.'/'.$this->slug) : null;
    }

    public function getImageUrlAttribute(): ?string
    {
        $path = $this->getAttribute('cover_path') ?: $this->getAttribute('photo_path');
        if (! $path) {
            return null;
        }

        return url('storage/'.$path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        $path = $this->getAttribute('cover_path') ?: $this->getAttribute('photo_path');
        if (! $path) {
            return null;
        }
        $thumbnail = dirname($path).'/thumbs/'.basename($path);

        return Storage::disk('public')->exists($thumbnail) ? url('storage/'.$thumbnail) : $this->image_url;
    }

    public function getSafeBodyAttribute(): string
    {
        return app(HtmlContent::class)->render((string) $this->getAttribute('body_html'));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date', 'last_visited_at' => 'datetime'];
    }
}
