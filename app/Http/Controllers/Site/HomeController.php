<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Award;
use App\Models\Gallery;
use App\Models\InstituteCategory;
use App\Models\NewsItem;
use App\Models\Slide;
use App\Services\VideoFeed;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $ready = Schema::hasTable('news');

        return view('site.home', [
            'slides' => $ready ? Slide::published()->latest('id')->get() : collect(),
            'articles' => $ready ? Article::published()->latest('id')->limit(3)->get() : collect(),
            'news' => $ready ? NewsItem::published()->latest('id')->limit(4)->get() : collect(),
            'galleries' => $ready ? Gallery::published()->latest('id')->limit(4)->get() : collect(),
            'videos' => app(VideoFeed::class)->get()->take(4),
            'instituteCategories' => $ready ? InstituteCategory::published()->orderBy('id')->get() : collect(),
            'awards' => $ready ? Award::published()->orderBy('name')->get() : collect(),
        ]);
    }
}
