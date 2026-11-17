@extends('layouts.admin')
@section('content')
    <livewire:cms.page-form-grafik :id="$id ?? null" :template="$template ?? null" />
@endsection

@push('scripts')
    @vite('resources/js/grafik.js')
@endpush
