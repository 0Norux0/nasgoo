<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <label class="text-sm font-medium">Lookback (days):</label>
            <select wire:model.live="days" class="rounded-md border-gray-300 text-sm">
                <option value="7">7</option>
                <option value="30">30</option>
                <option value="90">90</option>
            </select>
        </div>

        {{-- Per-type performance --}}
        <div class="bg-white rounded-lg border p-4">
            <h2 class="text-lg font-semibold mb-3">Recommendation performance (aggregated, no PII)</h2>
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left p-2">Section type</th>
                        <th class="text-right p-2">Impressions</th>
                        <th class="text-right p-2">Clicks</th>
                        <th class="text-right p-2">CTR %</th>
                        <th class="text-right p-2">Add-to-cart</th>
                        <th class="text-right p-2">A2C %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->metrics as $row)
                        <tr class="border-t" wire:key="metric-{{ $row['type'] }}">
                            <td class="p-2 font-medium">{{ $row['type'] }}</td>
                            <td class="p-2 text-right">{{ number_format($row['impressions']) }}</td>
                            <td class="p-2 text-right">{{ number_format($row['clicks']) }}</td>
                            <td class="p-2 text-right">{{ $row['ctr'] }}%</td>
                            <td class="p-2 text-right">{{ number_format($row['add_to_cart']) }}</td>
                            <td class="p-2 text-right">{{ $row['a2c_rate'] }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4 text-center text-slate-500">No events recorded in this window.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Top recommended products --}}
        <div class="bg-white rounded-lg border p-4">
            <h2 class="text-lg font-semibold mb-3">Top recommended products (by add-to-cart)</h2>
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left p-2">Product</th>
                        <th class="text-right p-2">Impressions</th>
                        <th class="text-right p-2">Clicks</th>
                        <th class="text-right p-2">Add-to-cart</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->topProducts as $row)
                        <tr class="border-t" wire:key="top-{{ $row['id'] }}">
                            <td class="p-2">{{ $row['name'] }}</td>
                            <td class="p-2 text-right">{{ number_format($row['impressions']) }}</td>
                            <td class="p-2 text-right">{{ number_format($row['clicks']) }}</td>
                            <td class="p-2 text-right">{{ number_format($row['add_to_cart']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-4 text-center text-slate-500">No product-level data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
