<div>
    @isset($error)
        <p role="alert">{{ $error }}</p>
    @else
        <iframe title="Prévia da campanha" sandbox="" referrerpolicy="no-referrer" srcdoc="{{ $html }}" style="width:100%;height:55vh;border:0"></iframe>
        @if($campaign->attachments)
            <strong>Anexos</strong>
            <ul>@foreach($campaign->attachments as $path)<li>{{ $campaign->attachment_names[$path] ?? basename($path) }}</li>@endforeach</ul>
        @endif
    @endisset
</div>
