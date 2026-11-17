@extends('errors.layout')

@section('code', '419')
@section('title', 'Deze pagina is verlopen')
@section('message')
    <p>Je bent even weggeweest, en voor je veiligheid is het formulier verlopen. Ga terug, vernieuw de pagina en probeer het opnieuw.</p>
@endsection
@section('actions')
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="button">Terug</a>
    <a href="{{ url('/') }}" class="button button-ghost">Naar de homepage</a>
@endsection
