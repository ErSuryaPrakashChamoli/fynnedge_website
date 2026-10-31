{{-- Laravel's own minimal 404, for scanner probes and stale asset URLs (NotFoundRequests::isNoise()). --}}
{{-- errors.minimal, not errors::minimal: the errors:: namespace only exists inside Laravel's own error rendering. --}}
@extends('errors.minimal')

@section('title', __('Not Found'))
@section('code', '404')
@section('message', __('Not Found'))
