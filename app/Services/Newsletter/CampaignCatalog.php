<?php

namespace App\Services\Newsletter;

use App\Support\ContentRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CampaignCatalog
{
    private const TYPES = [
        'news' => ['source' => 'noticia', 'link' => 'Ler notícia'],
        'articles' => ['source' => 'artigo', 'link' => 'Ler artigo'],
        'events' => ['source' => 'evento', 'link' => 'Ver evento'],
        'newspapers' => ['source' => 'jornal', 'link' => 'Ver edição'],
        'parishes' => ['source' => 'paroquia', 'link' => 'Ver paróquia'],
        'institutes' => ['source' => 'instituto', 'link' => 'Ver instituto'],
    ];

    public static function labels(): array
    {
        return array_map(fn (array $type): string => ContentRegistry::get($type['source'])['label'], self::TYPES);
    }

    public static function linkLabels(): array
    {
        return array_column(self::TYPES, 'link', 'source');
    }

    public function browse(string $type, string $search): Builder
    {
        $query = $this->published($type);
        $title = ContentRegistry::get(self::TYPES[$type]['source'])['title'];
        $query->select(['id', $title]);
        if (filled($search)) {
            $query->where($title, 'like', '%'.addcslashes(mb_substr(trim($search), 0, 160), '%_\\').'%');
        }

        if ($type === 'events') {
            $today = now('America/Sao_Paulo')->toDateString();
            $query->addSelect('starts_on')->orderByRaw('CASE WHEN starts_on >= ? THEN 0 ELSE 1 END', [$today])
                ->orderByRaw('CASE WHEN starts_on >= ? THEN starts_on END ASC', [$today])->orderByDesc('starts_on');
        } elseif ($type === 'newspapers') {
            $query->addSelect('edition', 'year', 'month')->orderByDesc('year')->orderByDesc('month');
        } elseif (in_array($type, ['parishes', 'institutes'], true)) {
            $query->orderBy($title);
        } else {
            $query->addSelect('created_at')->orderByDesc('created_at');
        }

        return $query->orderByDesc('id');
    }

    public function resolve(array $keys): Collection
    {
        $ids = [];
        foreach ($keys as $key) {
            if (is_string($key) && preg_match('/^([a-z]+):([1-9][0-9]*)$/D', $key, $parts) && isset(self::TYPES[$parts[1]])) {
                $ids[$parts[1]][] = $parts[2];
            }
        }

        $found = [];
        foreach ($ids as $type => $group) {
            foreach ($this->published($type)->whereKey($group)->get() as $item) {
                $found[$type.':'.$item->id] = $item;
            }
        }

        $ordered = [];
        foreach ($keys as $key) {
            if (is_string($key) && isset($found[$key])) {
                $ordered[$key] = $found[$key];
            }
        }

        return collect($ordered);
    }

    private function published(string $type): Builder
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $model = ContentRegistry::model(self::TYPES[$type]['source']);

        return $model::published()->whereNotNull('slug')->where('slug', '!=', '');
    }
}
