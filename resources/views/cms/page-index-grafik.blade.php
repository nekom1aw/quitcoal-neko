@extends('layouts.admin')
@section('content')
    <livewire:cms.page-index-grafik />
@endsection

@push('scripts')
    @vite('resources/js/grafik.js')
@endpush
