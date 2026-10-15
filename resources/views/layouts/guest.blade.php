<!DOCTYPE html>
<html lang="id">
<head>
    @php($organizationSettings = \App\Models\OrganizationSetting::current())
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=2">
    <title>{{ $organizationSettings->application_name }} · {{ $organizationSettings->foundation_name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased">
    {{ $slot }}
    @livewireScripts
</body>
</html>
