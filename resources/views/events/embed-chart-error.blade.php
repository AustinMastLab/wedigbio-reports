<!DOCTYPE html>
<html lang="en">
<head>
    @include('events.partials.embed-head', ['title' => 'WeDigBio Chart Embed — Error'])
</head>
<body class="antialiased bg-slate-50 text-slate-900">

<div class="flex min-h-screen items-center justify-center p-6">
    <div class="max-w-[400px] text-center">
        @include('events.partials.embed.header', [
            'title' => '⚠️ No Live Event Found',
            'containerClass' => 'mb-2',
            'innerClass' => '',
            'titleClass' => 'm-0 text-3xl font-bold text-slate-800',
        ])
        @if (trim((string) $message) !== 'No live event found.')
            <p class="m-0 text-sm leading-6 text-slate-500">
                {{ $message }}
            </p>
        @endif
    </div>
</div>

</body>
</html>
