@extends('layouts.app')

@section('content')
    <x-main.master.accounts.page
        :role="__('master.auditee')"
        :description="__('account.auditeeDescription')"
        route-name="master.auditees"
        :accounts="$accounts"
        :plants="$plants"
        :plant-options="$plantOptions"
        :departments="$departments"
        :summary="$summary" />
@endsection
