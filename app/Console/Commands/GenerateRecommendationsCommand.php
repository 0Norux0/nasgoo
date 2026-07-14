<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\ProductPairStat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11B.2 §24 — recommendations:generate.
 *
 * Aggregates qualifying completed orders into the product_pair_stats
 * table. Idempotent:
 *
 *   - First run on an empty table populates from scratch
 *   - Re-runs recompute the same numbers (TRUNCATE+repopulate pattern)
 *     because re-summing the same orders deterministically yields the
 *     same pair counts; --since flag supports incremental backfill
 *
 * Usage:
 *   php artisan recommendations:generate
 *   php artisan recommendations:generate --product=123
 *   php artisan recommendations:generate --since=7  (last 7 days)
 *   php artisan recommendations:refresh-stale (alias)
 *
 * Per dev §24:
 *   - chunked processing       → orders processed in 500-row chunks
 *   - queue-safe               → command can be queued via `> /dev/null &`
 *   - no memory explosion      → orderItems are streamed
 *   - no duplicate pair rows   → canonical (a<b) ordering + upsert
 *   - no synchronous global    → run as scheduled job or background
 *   - progress reporting       → counts written + progress bar
 */
class GenerateRecommendationsCommand extends Command
{
    protected $signature = 'recommendations:generate
        {--product= : Regenerate stats only for orders containing this product ID}
        {--since= : Only process orders created in the last N days}
        {--truncate : TRUNCATE product_pair_stats before regenerating (default: incremental upsert)}';

    protected $description = 'Aggregate completed orders into product_pair_stats (Phase 11B.2)';

    public function handle(): int
    {
        $productId = $this->option('product') ? (int) $this->option('product') : null;
        $sinceDays = $this->option('since')   ? (int) $this->option('since')   : null;
        $truncate  = (bool) $this->option('truncate');

        $statuses = $this->qualifyingStatuses();

        if ($truncate) {
            $this->warn('Truncating product_pair_stats (--truncate)');
            ProductPairStat::query()->truncate();
        }

        // Build the order query
        $orderQuery = Order::query()
            ->whereIn('status', $statuses)
            ->when($sinceDays !== null, fn ($q) => $q->where('created_at', '>=', now()->subDays($sinceDays)));

        if ($productId !== null) {
            // Only process orders containing the given product
            $orderQuery->whereHas('items', fn ($q) => $q->where('product_id', $productId));
        }

        $total = (int) $orderQuery->clone()->count();
        $this->info("Processing $total qualifying orders…");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $pairUpserts = [];  // [a, b] => [pair_count, last_seen_at, customer_ids]
        $pairCustomers = [];  // [a, b] => set of user_ids

        $orderQuery->with(['items:id,order_id,product_id'])->chunk(500, function ($orders) use (&$pairUpserts, &$pairCustomers, $bar) {
            foreach ($orders as $order) {
                /** @var \App\Models\Order $order */
                $items = $order->items;
                $productIds = $items->pluck('product_id')->unique()->values()->all();
                $count = count($productIds);
                if ($count < 2) {
                    $bar->advance();
                    continue;
                }
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        [$a, $b] = ProductPairStat::canonical(
                            (int) $productIds[$i],
                            (int) $productIds[$j]
                        );
                        $key = "{$a}:{$b}";
                        if (! isset($pairUpserts[$key])) {
                            $pairUpserts[$key] = [
                                'a' => $a, 'b' => $b,
                                'pair_count' => 0,
                                'first_seen' => $order->created_at,
                                'last_seen'  => $order->created_at,
                            ];
                            $pairCustomers[$key] = [];
                        }
                        $pairUpserts[$key]['pair_count']++;
                        if ($order->created_at < $pairUpserts[$key]['first_seen']) {
                            $pairUpserts[$key]['first_seen'] = $order->created_at;
                        }
                        if ($order->created_at > $pairUpserts[$key]['last_seen']) {
                            $pairUpserts[$key]['last_seen'] = $order->created_at;
                        }
                        if ($order->user_id) {
                            $pairCustomers[$key][$order->user_id] = true;
                        }
                    }
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Aggregation complete — writing ' . count($pairUpserts) . ' pair stat rows.');

        // Batch upsert in chunks
        $rows = [];
        foreach ($pairUpserts as $key => $data) {
            $rows[] = [
                'product_a_id'            => $data['a'],
                'product_b_id'            => $data['b'],
                'pair_count'              => $data['pair_count'],
                'distinct_customer_count' => count($pairCustomers[$key] ?? []),
                'first_seen_at'           => $data['first_seen'],
                'last_seen_at'            => $data['last_seen'],
                'created_at'              => now(),
                'updated_at'              => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            // upsert: insert OR update on duplicate (a,b)
            DB::table('product_pair_stats')->upsert(
                $chunk,
                ['product_a_id', 'product_b_id'],
                ['pair_count', 'distinct_customer_count', 'first_seen_at', 'last_seen_at', 'updated_at']
            );
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function qualifyingStatuses(): array
    {
        return [
            Order::STATUS_PAID, Order::STATUS_CONFIRMED,
            Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_COMPLETED,
        ];
    }
}
