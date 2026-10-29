@extends('layouts.app')

@section('content')
    <x-main.master.accounts.page
        :role="__('master.auditor')"
        :description="__('account.auditorDescription')"
        route-name="master.auditors"
        :accounts="$accounts"
        :plants="$plants"
        :plant-options="$plantOptions"
        :departments="$departments"
        :summary="$summary" />
@endsection
