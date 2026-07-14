<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Phase 11B.1 v11B.1.1 §5 — idempotent Arabic backfill for demo products.
 *
 * Per dev §5:
 *   - "do not overwrite administrator/vendor-entered Arabic values"  → uses
 *      empty() guard on the existing 'ar' value
 *   - "do not alter production data automatically"                   → matches
 *      only known demo product slugs; production products are untouched
 *   - "clearly document which test products received Arabic content"
 *   - "include at least several representative products across categories"
 *   - "Use accurate Modern Standard Arabic."
 *
 * Run via:    php artisan db:seed --class=ArabicProductContentSeeder
 * Or:         php artisan migrate:fresh --seed   (chained from DatabaseSeeder)
 *
 * The seeder is safe to re-run; each row's update is gated by empty($existing['ar']).
 */
class ArabicProductContentSeeder extends Seeder
{
    /**
     * Target products and their Arabic translations.
     * Keyed by slug → only known demo products receive translations.
     */
    private const TRANSLATIONS = [
        // Demo Trading Co products (slug pattern: 'wireless-bluetooth-headphones' etc.
        // depends on whether DemoSeeder uses createSlug or static — match by name)
        'wireless-bluetooth-headphones' => [
            'name_ar'              => 'سماعات لاسلكية بتقنية البلوتوث',
            'short_description_ar' => 'سماعات لاسلكية عالية الجودة مع ميكروفون مدمج، عمر بطارية طويل، ومناسبة للاستخدام اليومي.',
            'description_ar'       => "سماعات لاسلكية بتقنية البلوتوث 5.0 توفر صوتًا واضحًا وعمر بطارية يصل إلى 30 ساعة. تأتي مع ميكروفون مدمج للمكالمات، أزرار تحكم سهلة الاستخدام، وتصميم مريح يدوم لساعات طويلة من الاستماع.\n\nالميزات الرئيسية:\n• تقنية بلوتوث 5.0\n• عمر بطارية حتى 30 ساعة\n• ميكروفون مدمج\n• تصميم قابل للطي",
        ],
        'cotton-t-shirt-classic-fit' => [
            'name_ar'              => 'قميص قطني — قصة كلاسيكية',
            'short_description_ar' => 'قميص قطني ١٠٠٪، قصة كلاسيكية مريحة مناسبة للاستخدام اليومي.',
            'description_ar'       => "قميص مصنوع من القطن الخالص ١٠٠٪، بقصة كلاسيكية مريحة. يتميز بنعومة الملمس وجودة الخياطة، ومناسب لمختلف المناسبات اليومية.\n\nالمواصفات:\n• قطن خالص ١٠٠٪\n• قصة كلاسيكية\n• متوفر بعدة مقاسات\n• قابل للغسل الآلي",
        ],
        'stainless-steel-water-bottle' => [
            'name_ar'              => 'زجاجة ماء من الفولاذ المقاوم للصدأ',
            'short_description_ar' => 'زجاجة ماء معزولة من الفولاذ المقاوم للصدأ تحافظ على درجة حرارة الشراب لساعات.',
            'description_ar'       => "زجاجة ماء عالية الجودة مصنوعة من الفولاذ المقاوم للصدأ بطبقة عازلة مزدوجة. تحافظ على درجة حرارة الشراب الباردة لمدة ٢٤ ساعة والساخنة لمدة ١٢ ساعة.\n\nالميزات:\n• فولاذ مقاوم للصدأ من الدرجة الغذائية\n• عزل مزدوج\n• خالية من مادة BPA\n• سعة ٧٥٠ مل",
        ],
        'handwoven-beach-towel' => [
            'name_ar'              => 'منشفة شاطئ منسوجة يدويًا',
            'short_description_ar' => 'منشفة شاطئ منسوجة يدويًا من القطن المصري، خفيفة الوزن وسريعة الجفاف.',
            'description_ar'       => "منشفة شاطئ فاخرة منسوجة يدويًا من القطن المصري عالي الجودة. خفيفة الوزن وسريعة الجفاف، مع تصميمات عصرية تجمع بين الأناقة والوظيفية.\n\nالمميزات:\n• قطن مصري ١٠٠٪\n• منسوجة يدويًا\n• خفيفة وسريعة الجفاف\n• مقاس كبير ١٧٠×٩٠ سم",
        ],
    ];

    /**
     * Idempotent update — only writes Arabic values when the existing
     * translation for that field is empty. Admin/vendor edits are preserved.
     *
     * @var list<string>
     */
    private array $updatedSlugs = [];

    public function run(): void
    {
        $appliedCount   = 0;
        $skippedCount   = 0;
        $missingCount   = 0;

        foreach (self::TRANSLATIONS as $slug => $ar) {
            $product = Product::where('slug', $slug)->first();

            if (! $product) {
                $missingCount++;
                continue;
            }

            $changed = false;

            // Name translation (idempotent)
            $existingName = $product->name_translations ?? [];
            if (empty($existingName['ar'])) {
                $existingName['ar'] = $ar['name_ar'];
                $product->name_translations = $existingName;
                $changed = true;
            }

            // Short description translation (idempotent)
            $existingShort = $product->short_description_translations ?? [];
            if (empty($existingShort['ar'])) {
                $existingShort['ar'] = $ar['short_description_ar'];
                $product->short_description_translations = $existingShort;
                $changed = true;
            }

            // Full description translation (idempotent)
            $existingFull = $product->description_translations ?? [];
            if (empty($existingFull['ar'])) {
                $existingFull['ar'] = $ar['description_ar'];
                $product->description_translations = $existingFull;
                $changed = true;
            }

            if ($changed) {
                $product->saveQuietly();  // no events / observers
                $appliedCount++;
                $this->updatedSlugs[] = $slug;
            } else {
                $skippedCount++;
            }
        }

        $this->command?->info(sprintf(
            'Arabic content seeded: %d applied, %d skipped (already had ar), %d missing slug.',
            $appliedCount,
            $skippedCount,
            $missingCount
        ));

        if (! empty($this->updatedSlugs)) {
            $this->command?->line('Updated products:');
            foreach ($this->updatedSlugs as $slug) {
                $this->command?->line("  - $slug");
            }
        }
    }
}
