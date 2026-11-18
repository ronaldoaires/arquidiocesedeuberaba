<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Event;
use App\Services\VideoFeed;
use App\Services\YouTubeVideoMetadata;
use App\Support\ContentRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ContentController extends Controller
{
    public function index(Request $request): View
    {
        $module = ContentRegistry::get($request->route('module'));
        $class = ContentRegistry::model($module['source']);
        $query = $class::published();
        $title = $module['label'];
        if ($parent = $request->route('parent_module')) {
            $parentClass = ContentRegistry::model($parent);
            $record = $parentClass::published()->where('slug', $request->route('slug'))->firstOrFail();
            $query->where($request->route('parent_key'), $record->id);
            $title = $record->display_title;
        }
        $page = max(1, (int) ($request->route('page') ?? $request->query('page', 1)));
        if ($module['source'] === 'clero' || ($module['source'] === 'paroquia' && $parent !== 'paroquia_forania')) {
            $query->orderBy('name');
        } elseif ($module['source'] === 'noticia' && $parent) {
            $query->orderByDesc('created_at');
        } elseif (in_array($module['source'], ['instituto', 'paroquia'], true)) {
            $query->orderBy('id');
        } else {
            $query->orderByDesc('id');
        }
        $records = $query->paginate(20, ['*'], 'page', $page)->withQueryString();
        if ($module['source'] === 'paroquia' && $parent === 'paroquia_cidade') {
            $title = 'Paróquias '.$title;
        }

        $cities = $module['source'] === 'paroquia' ? City::published()->orderBy('name')->get() : collect();

        return view('site.index', compact('module', 'records', 'title', 'cities'));
    }

    public function show(Request $request): View|RedirectResponse
    {
        $module = ContentRegistry::get($request->route('module'));
        $class = ContentRegistry::model($module['source']);
        $record = $class::published()->where('slug', $request->route('slug'))->first();
        if (! $record) {
            $redirect = DB::table('content_redirects')->where('old_path', $request->getPathInfo())->first();
            if ($redirect && $redirect->source_table === $module['source']) {
                $target = $class::published()->find($redirect->record_id);
                if ($target?->public_url) {
                    return redirect($target->public_url, 301);
                }
            }
            abort(404);
        }
        $related = [];
        if ($module['source'] === 'galeria') {
            $photos = $record->photos()->published()
                ->whereNotNull('photo_path')->where('photo_path', '!=', '')
                ->orderBy('id')->paginate(24, ['id', 'title', 'photo_path'])
                ->fragment('fotos');
            abort_if($photos->currentPage() > $photos->lastPage(), 404);
            $related['photos'] = $photos;
        }
        foreach (match ($module['source']) {
            'pagina' => ['sections'], 'pastoral' => ['downloads', 'videos', 'galleries'],
            'paroquia','clero' => ['assignments'], 'radio' => ['programs', 'tracks'], default => [],
        } as $relation) {
            $query = $record->{$relation}();
            if ($module['source'] === 'radio' && $relation === 'programs') {
                $query->where('day', now('America/Sao_Paulo')->dayOfWeek);
            }
            if ($relation !== 'assignments') {
                $query->published();
            } else {
                $query->with(['parish', 'clergyMember']);
                if ($module['source'] === 'paroquia') {
                    $query->whereHas('clergyMember', fn ($members) => $members->published());
                }
            }
            $related[$relation] = $query->orderBy('id')->get();
            if ($module['source'] === 'paroquia' && $relation === 'assignments') {
                $priorities = array_flip(['Pároco', 'Administrador Paroquial', 'Capelão', 'Vigário Paroquial', 'Diácono Permanente']);
                $related[$relation] = $related[$relation]->sortBy(fn ($assignment): array => [
                    $assignment->role_text === null ? 1 : 0,
                    $priorities[$assignment->role_text] ?? 5,
                    $assignment->role_text ?? $assignment->clergyMember?->role_1_text ?? $assignment->clergyMember?->role_2_text ?? $assignment->clergyMember?->role_3_text ?? '',
                    $assignment->id,
                ])->values();
            }
        }
        if ($module['source'] === 'pastoral') {
            app(YouTubeVideoMetadata::class)->prime($related['videos']->pluck('video_id')->all());
        }
        if ($module['source'] !== 'jornal' && config('migration.record_visits', true) && ! app()->runningUnitTests() && $record->getAttribute('visits') !== null) {
            $record->newQuery()->whereKey($record->id)->increment('visits', 1, in_array('last_visited_at', array_column($module['columns'], 'name'), true) ? ['last_visited_at' => now()] : []);
        }

        return view('site.show', ['module' => $module, 'record' => $record, 'title' => $record->display_title, 'related' => $related]);
    }

    public function agenda(Request $request): View
    {
        $year = (int) ($request->route('year') ?? now('America/Sao_Paulo')->year);
        $month = $request->route('month');
        abort_if($year < 1900 || $year > 2200 || ($month && ((int) $month < 1 || (int) $month > 12)), 404);
        $query = Event::published()->whereYear('starts_on', $year);
        if ($month) {
            $query->whereMonth('starts_on', $month);
        } else {
            $query->where(fn ($query) => $query->whereDate('starts_on', '>=', today('America/Sao_Paulo'))->orWhereDate('ends_on', '>=', today('America/Sao_Paulo')));
        }

        $records = $query->orderBy('starts_on')->get();
        $years = Event::published()->whereDate('starts_on', '>=', today('America/Sao_Paulo'))->get(['starts_on'])
            ->map(fn (Event $event): int => $event->starts_on->year)->unique()->sort()->values();

        return view('site.agenda', compact('records', 'year', 'month', 'years') + ['title' => 'Agenda Pastoral']);
    }

    public function special(Request $request): View
    {
        $page = $request->route('special');
        $titles = ['media' => 'Central de Mídias', 'liturgy' => 'Liturgia Diária', 'videos' => 'Galeria de Vídeos'];

        return view('site.special', ['special' => $page, 'title' => $titles[$page], 'videos' => $page === 'videos' ? new LengthAwarePaginator(app(VideoFeed::class)->get(), app(VideoFeed::class)->get()->count(), 20) : collect()]);
    }
}
