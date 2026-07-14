<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'slug'     => 'cod',
                'provider' => 'cod',
                'name'     => 'Cash on Delivery',
                'name_translations' => [
                    'ar' => 'الدفع عند الاستلام',
                    'ur' => 'ڈلیوری پر ادائیگی',
                ],
                'description' => 'Pay in cash when your order is delivered. No upfront charge.',
                'description_translations' => [
                    'ar' => 'ادفع نقدًا عند استلام طلبك. لا توجد رسوم مقدمة.',
                    'ur' => 'اپنا آرڈر ڈلیور ہونے پر نقد ادائیگی کریں۔ کوئی پیشگی چارج نہیں۔',
                ],
                'is_active' => true,
                'available_at_checkout' => true,
                'position'  => 1,
                'supported_currencies' => ['KWD', 'AED'],
            ],
            [
                'slug'     => 'manual_transfer',
                'provider' => 'manual_transfer',
                'name'     => 'Bank Transfer',
                'name_translations' => [
                    'ar' => 'تحويل بنكي',
                    'ur' => 'بینک ٹرانسفر',
                ],
                'description' => 'Transfer the order total to our bank account. We confirm receipt within one business day.',
                'description_translations' => [
                    'ar' => 'حوّل المبلغ إلى حساب المنصة. نؤكد الاستلام خلال يوم عمل واحد.',
                    'ur' => 'آرڈر کی رقم ہمارے بینک اکاؤنٹ میں منتقل کریں۔ ہم ایک ورکنگ ڈے میں تصدیق کرتے ہیں۔',
                ],
                'is_active' => true,
                'available_at_checkout' => true,
                'position'  => 2,
                'supported_currencies' => null, // all currencies
            ],
            [
                'slug'     => 'online_mock',
                'provider' => 'online_mock',
                'name'     => 'Card / Online (Demo)',
                'name_translations' => [
                    'ar' => 'بطاقة / إنترنت (تجريبي)',
                    'ur' => 'کارڈ / آن لائن (ڈیمو)',
                ],
                'description' => 'Demo gateway. Real card processing (MyFatoorah / Tap / Stripe) is configured in a future sub-phase.',
                'description_translations' => [
                    'ar' => 'بوابة تجريبية. سيتم تكوين معالجة البطاقات الحقيقية في مرحلة فرعية لاحقة.',
                    'ur' => 'ڈیمو گیٹ وے۔ اصل کارڈ پراسیسنگ مستقبل کے سب فیز میں ترتیب دی جائے گی۔',
                ],
                'is_active' => true,
                'available_at_checkout' => true,
                'position'  => 3,
                'config'    => ['force_outcome' => 'success'],
                'supported_currencies' => null,
            ],
        ];

        foreach ($methods as $data) {
            PaymentMethod::updateOrCreate(['slug' => $data['slug']], $data);
        }

        // Phase 9 v9.4 — $this->command can be null when this seeder runs from
        // a test/service context (not from artisan db:seed). Match the null-
        // safe pattern already used elsewhere in this seeder.
        $this->command?->info('Seeded ' . PaymentMethod::count() . ' payment methods (cod, manual_transfer, online_mock).');
    }
}
