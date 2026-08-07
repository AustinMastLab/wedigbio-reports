<x-filament-panels::page>
    <div class="space-y-10">
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">WeDigBio</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Press play to watch the WeDigBio video.</p>
            </div>

            <div class="p-6">
                <video controls preload="metadata" class="w-full rounded-lg bg-black">
                    <source src="{{ asset('storage/wedigbio.mp4') }}" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
        </section>

        <div aria-hidden="true" style="height: 2rem;"></div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">WeDigBio Reports</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Press play to watch the WeDigBio Reports video.</p>
            </div>

            <div class="p-6">
                <video controls preload="metadata" class="w-full rounded-lg bg-black">
                    <source src="{{ asset('storage/wedigbio-reports.mp4') }}" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
        </section>
    </div>
</x-filament-panels::page>

