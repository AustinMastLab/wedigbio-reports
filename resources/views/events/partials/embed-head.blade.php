<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'WeDigBio Chart Embed' }}</title>
<!-- Force light mode for embeds -->
<script>
    document.documentElement.classList.remove('dark');
</script>
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endif

