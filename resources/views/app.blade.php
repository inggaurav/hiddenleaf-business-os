<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='16' fill='%237c3aed'/%3E%3Cpath d='M18 45V19h7v10h14V19h7v26h-7V35H25v10z' fill='white'/%3E%3C/svg%3E">
    @php
        $activeOrgId = session('active_organization_id');
        $activeOrg = $activeOrgId ? \App\Models\Organization::find($activeOrgId) : null;
        $systemBrandName = config('settings.titleText')
            ?: (config('settings.app_name') ?: config('app.name', 'HiddenLeaf BusinessOS'));
        $resolvedBrandName = ($activeOrg && $activeOrg->brand_name) ? $activeOrg->brand_name : $systemBrandName;
        $serverBrandColor = $activeOrg ? $activeOrg->brand_primary_color : null;
    @endphp
    <meta name="brand-name" content="{{ $resolvedBrandName }}">
    <title inertia>{{ $resolvedBrandName }}</title>
    @if(!empty($serverBrandColor))
        <style>:root { --brand-primary: {{ $serverBrandColor }}; }</style>
    @endif
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-100 min-h-screen">
    @inertia
</body>
</html>
