@extends('site.layouts.app')
@section('content')
@if($kind==='contact')
    @include('site.partials.contact-form')
@else
    @include('site.partials.support-form')
@endif
@endsection
