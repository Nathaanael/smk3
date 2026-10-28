@extends('layouts.app')

@section('content')
    <!-- <x-common.page-breadcrumb pageTitle="Dashboard" /> -->

    <x-main.dashboard.admin.greeting-clock :name="auth()->user()->name" />
@endsection
