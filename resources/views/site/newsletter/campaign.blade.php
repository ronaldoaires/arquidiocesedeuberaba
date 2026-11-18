<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $campaign->subject }}</title></head>
<body style="margin:0;background:#f7f5f2;color:#312b29;font-family:Arial,sans-serif;line-height:1.55">
    <div style="max-width:600px;margin:0 auto;background:#fff;padding:28px">
        <p style="margin:0;color:#8f3234;font-weight:bold">Arquidiocese de Uberaba</p>
        <h1 style="font-size:26px;line-height:1.25">{{ $campaign->subject }}</h1>
        <div>{!! $body !!}</div>
        @foreach($items as $item)
            <div style="border-top:1px solid #ded8d3;padding:18px 0">
                @if($item->image_url)<img src="{{ $item->image_url }}" alt="" width="544" style="max-width:100%;height:auto">@endif
                <h2 style="font-size:20px;margin:10px 0">{{ $item->display_title }}</h2>
                @if($item instanceof \App\Models\Event)
                    <p>{{ collect([$item->starts_on?->format('d/m/Y'), $item->location])->filter()->implode(' · ') }}</p>
                @endif
                @if($item instanceof \App\Models\Newspaper)
                    @if(filled($item->edition) || filled($item->publication_period))
                        <p>{{ collect([filled($item->edition) ? 'Edição '.$item->edition : null, $item->publication_period])->filter()->implode(' · ') }}</p>
                    @endif
                @endif
                @if(filled($item->body_html))
                    <p>{{ Str::limit(Str::squish(html_entity_decode(strip_tags($item->body_html), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 220) }}</p>
                @endif
                <a style="color:#8f3234" href="{{ $item->public_url }}">{{ $linkLabels[$item::$sourceTable] }}</a>
            </div>
        @endforeach
    </div>
</body></html>
