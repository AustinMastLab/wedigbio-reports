<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Total Transactions by Year
        </x-slot>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead>
                    <tr class="text-left text-gray-600 dark:text-gray-300">
                        <th class="px-3 py-2 font-medium">Year</th>
                        <th class="px-3 py-2 font-medium">Transactions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-3 py-2">{{ $row->year }}</td>
                            <td class="px-3 py-2">{{ number_format((int) $row->total_transactions) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-3 text-gray-500 dark:text-gray-400" colspan="2">
                                No transcription data available.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

