@extends('errors.layout')

@section('code', '503')
@section('title', 'Even onderhoud')
@section('message')
    <p>We zijn de website aan het bijwerken. Binnen enkele minuten zijn we terug.</p>
@endsection
@section('actions')
    <a href="{{ url('/') }}" class="button">Opnieuw proberen</a>
@endsection
