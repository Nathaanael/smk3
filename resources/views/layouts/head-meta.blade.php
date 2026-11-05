@php
    $pageMeta = config('page-meta.' . str_replace('.', '\.', '/' . trim(request()->path(), '/')));
    $metaTitle = isset($pageMeta['title']) ? __($pageMeta['title']) : 'Laravel ' . ($title ?? 'Dashboard') . ' | TailAdmin - Laravel Dashboard Template';
    $metaDescription = isset($pageMeta['description']) ? __($pageMeta['description']) : 'This is Laravel ' . ($title ?? 'Dashboard') . ' page for TailAdmin - Laravel Tailwind CSS Admin Dashboard Template';
@endphp
<title>{{ $metaTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
{{-- without this the browser asks the domain root, which on a subfolder install is another site's icon --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
