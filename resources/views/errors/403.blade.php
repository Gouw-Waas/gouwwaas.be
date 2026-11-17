@extends('errors.layout')

@section('code', '403')
@section('title', 'Hier mag je niet binnen')
@section('message')
    <p>Deze pagina is alleen voor medewerkers met de juiste rechten. Denk je dat je hier wel toegang zou moeten hebben, mail dan naar <a href="mailto:gouwbureau@gouwwaas.be">gouwbureau@gouwwaas.be</a>.</p>
@endsection
@section('actions')
    <a href="{{ url('/') }}" class="button">Naar de homepage</a>
    <a href="{{ url('/login') }}" class="button button-ghost">Inloggen</a>
@endsection
