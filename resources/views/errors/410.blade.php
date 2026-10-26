{{-- An old address of the Joomla site with nothing here: search engines drop it, people get a way on. --}}
@extends('errors::minimal')

@section('title', __('Not Found'))
@section('code', '410')
@section('message')
    <a href="{{ route('home') }}" style="text-decoration:underline">anahnu.club</a>
@endsection
