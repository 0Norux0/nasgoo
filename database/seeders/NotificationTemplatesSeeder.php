<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // event_key, subject (en), body (en), placeholders
            ['user.registered',         'Welcome to {{ site_name }}',     'Hi {{ name }}, welcome aboard. Please verify your email to activate your account.',                                    ['site_name', 'name']],
            ['user.email_verification', 'Verify your email',              'Hi {{ name }}, please click the link to verify your email: {{ verification_url }}',                                  ['name', 'verification_url']],
            ['password.reset',          'Reset your password',            'Hi {{ name }}, use this link to reset your password: {{ reset_url }} (expires in 60 minutes).',                       ['name', 'reset_url']],
            ['vendor.approved',         'Your vendor account is approved','Hi {{ business_name }}, your vendor application has been approved. You can now upload products.',                    ['business_name']],
            ['vendor.rejected',         'Vendor application rejected',    'Hi {{ business_name }}, unfortunately we cannot approve your application. Reason: {{ reason }}',                     ['business_name', 'reason']],
            ['product.approved',        'Product approved: {{ title }}',  'Your product "{{ title }}" has been approved and is now live on the marketplace.',                                   ['title']],
            ['product.rejected',        'Product rejected: {{ title }}',  'Your product "{{ title }}" was not approved. Reason: {{ reason }}',                                                 ['title', 'reason']],
            ['order.placed',            'Order #{{ order_number }} placed','Hi {{ name }}, your order #{{ order_number }} totaling {{ total }} has been placed. We will notify you of updates.', ['name', 'order_number', 'total']],
            ['order.confirmed',         'Order #{{ order_number }} confirmed', 'Your order #{{ order_number }} is confirmed and is being prepared for shipment.',                              ['order_number']],
            ['order.shipped',           'Order #{{ order_number }} shipped',   'Your order #{{ order_number }} has shipped. Track it here: {{ tracking_url }}',                                ['order_number', 'tracking_url']],
            ['order.delivered',         'Order #{{ order_number }} delivered', 'Your order #{{ order_number }} has been delivered. Thanks for shopping with us!',                              ['order_number']],
            ['booking.created',         'Booking #{{ booking_number }} received','Hi {{ name }}, your booking for {{ service_name }} on {{ scheduled_at }} has been received.',                ['name', 'booking_number', 'service_name', 'scheduled_at']],
            ['booking.confirmed',       'Booking #{{ booking_number }} confirmed','Your booking for {{ service_name }} on {{ scheduled_at }} is confirmed.',                                   ['booking_number', 'service_name', 'scheduled_at']],
            ['payout.requested',        'Payout request submitted',       'Your payout request of {{ amount }} has been submitted and is pending review.',                                     ['amount']],
            ['payout.approved',         'Payout approved',                'Your payout of {{ amount }} has been approved and will be processed within 1-3 business days.',                     ['amount']],

            // Phase 2 — vendor system events
            ['vendor.application_submitted', 'Vendor application received', 'Hi {{ business_name }}, we received your vendor application. Our team will review it shortly.',                   ['business_name']],
            ['vendor.suspended',             'Vendor account suspended',   'Hi {{ business_name }}, your vendor account has been suspended. Reason: {{ reason }}',                            ['business_name', 'reason']],
            ['vendor.subscription_activated','Subscription activated',     'Hi {{ business_name }}, your {{ package }} subscription is now active until {{ ends_at }}.',                       ['business_name', 'package', 'ends_at']],
            ['vendor.package_changed',       'Vendor package changed',     'Hi {{ business_name }}, your package has been changed to {{ package }}.',                                          ['business_name', 'package']],
            ['vendor.commission_changed',    'Vendor commission updated',  'Hi {{ business_name }}, your commission rule has been updated. New rate: {{ rate }}.',                             ['business_name', 'rate']],
        ];

        // Arabic versions — short translations to demonstrate i18n structure
        $arabic = [
            'user.registered'         => ['أهلاً بك في {{ site_name }}', 'مرحباً {{ name }}، يُرجى تأكيد بريدك الإلكتروني لتفعيل حسابك.'],
            'user.email_verification' => ['تأكيد بريدك الإلكتروني',      'مرحباً {{ name }}، اضغط الرابط للتأكيد: {{ verification_url }}'],
            'password.reset'          => ['إعادة تعيين كلمة المرور',     'مرحباً {{ name }}، استخدم الرابط: {{ reset_url }} (صالح لمدة 60 دقيقة).'],
            'vendor.approved'         => ['تمت الموافقة على حسابك',      'مرحباً {{ business_name }}، تمت الموافقة على طلبك ويمكنك الآن رفع المنتجات.'],
            'vendor.rejected'         => ['رفض طلب البائع',              'مرحباً {{ business_name }}، لم نتمكن من قبول طلبك. السبب: {{ reason }}'],
            'product.approved'        => ['تمت الموافقة على المنتج',     'تمت الموافقة على منتجك "{{ title }}" وأصبح متاحاً الآن.'],
            'product.rejected'        => ['رفض المنتج',                  'لم تتم الموافقة على منتجك "{{ title }}". السبب: {{ reason }}'],
            'order.placed'            => ['تم استلام طلبك #{{ order_number }}', 'مرحباً {{ name }}، تم استلام طلبك #{{ order_number }} بمبلغ {{ total }}.'],
            'order.confirmed'         => ['تم تأكيد الطلب #{{ order_number }}', 'تم تأكيد طلبك #{{ order_number }} وجارٍ تجهيزه.'],
            'order.shipped'           => ['شحن الطلب #{{ order_number }}',     'تم شحن طلبك. تتبع الشحنة: {{ tracking_url }}'],
            'order.delivered'         => ['تم تسليم الطلب',              'تم تسليم طلبك #{{ order_number }}. شكراً لك!'],
            'booking.created'         => ['حجز جديد #{{ booking_number }}', 'مرحباً {{ name }}، تم استلام حجزك لخدمة {{ service_name }}.'],
            'booking.confirmed'       => ['تأكيد الحجز',                 'تم تأكيد حجزك لخدمة {{ service_name }} في {{ scheduled_at }}.'],
            'payout.requested'        => ['طلب سحب جديد',                'تم استلام طلب السحب بمبلغ {{ amount }} وهو قيد المراجعة.'],
            'payout.approved'         => ['تمت الموافقة على السحب',      'تمت الموافقة على سحب {{ amount }} وسيُنفذ خلال 1-3 أيام عمل.'],
            'vendor.application_submitted' => ['تم استلام طلب البائع', 'مرحباً {{ business_name }}، تم استلام طلبك وسيتم مراجعته قريباً.'],
            'vendor.suspended'             => ['تعليق حساب البائع',   'مرحباً {{ business_name }}، تم تعليق حسابك. السبب: {{ reason }}'],
            'vendor.subscription_activated'=> ['تفعيل الاشتراك',       'مرحباً {{ business_name }}، تم تفعيل اشتراك {{ package }} حتى {{ ends_at }}.'],
            'vendor.package_changed'       => ['تغيير الباقة',         'مرحباً {{ business_name }}، تم تغيير باقتك إلى {{ package }}.'],
            'vendor.commission_changed'    => ['تحديث عمولة البائع',   'مرحباً {{ business_name }}، تم تحديث عمولتك. المعدل الجديد: {{ rate }}.'],
        ];

        foreach ($templates as [$eventKey, $subjectEn, $bodyEn, $placeholders]) {
            // English mail
            NotificationTemplate::updateOrCreate(
                ['event_key' => $eventKey, 'channel' => 'mail', 'locale' => 'en'],
                [
                    'subject'      => $subjectEn,
                    'body'         => $bodyEn,
                    'placeholders' => $placeholders,
                    'is_active'    => true,
                ],
            );

            // English database (in-app)
            NotificationTemplate::updateOrCreate(
                ['event_key' => $eventKey, 'channel' => 'database', 'locale' => 'en'],
                [
                    'subject'      => $subjectEn,
                    'body'         => $bodyEn,
                    'placeholders' => $placeholders,
                    'is_active'    => true,
                ],
            );

            // Arabic mail
            if (isset($arabic[$eventKey])) {
                [$subjectAr, $bodyAr] = $arabic[$eventKey];
                NotificationTemplate::updateOrCreate(
                    ['event_key' => $eventKey, 'channel' => 'mail', 'locale' => 'ar'],
                    [
                        'subject'      => $subjectAr,
                        'body'         => $bodyAr,
                        'placeholders' => $placeholders,
                        'is_active'    => true,
                    ],
                );
            }
        }

        $count = NotificationTemplate::count();
        $this->command?->info("Seeded {$count} notification templates (15 events × en/ar locales × mail/database channels).");
    }
}
