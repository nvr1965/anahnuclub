{{-- An old address of the Joomla site with nothing here: search engines drop it, people get a way on (members' pages are
     there after signing in). --}}
@extends('errors::minimal')

@section('title', __('Not Found'))
@section('code', '410')
@section('message')
    <a href="{{ route('home') }}" style="text-decoration:underline">anahnu.club</a>
    @guest
        · <a href="{{ route('login') }}" style="text-decoration:underline">{{ __('Log in') }}</a>
    @endguest
@endsection
