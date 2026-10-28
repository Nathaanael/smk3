@php
    $pageMeta = config('page-meta.' . str_replace('.', '\.', '/' . trim(request()->path(), '/')));
    $metaTitle = isset($pageMeta['title']) ? __($pageMeta['title']) : 'Laravel ' . ($title ?? 'Dashboard') . ' | TailAdmin - Laravel Dashboard Template';
    $metaDescription = isset($pageMeta['description']) ? __($pageMeta['description']) : 'This is Laravel ' . ($title ?? 'Dashboard') . ' page for TailAdmin - Laravel Tailwind CSS Admin Dashboard Template';
@endphp
<title>{{ $metaTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
