@extends('layouts.app')

@section('content')
    <!-- <x-common.page-breadcrumb pageTitle="Dashboard" /> -->

    <x-main.dashboard.greeting-clock :name="auth()->user()->name" />
@endsection
