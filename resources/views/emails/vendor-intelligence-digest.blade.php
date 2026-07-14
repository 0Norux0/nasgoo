@component('mail::message')
{{-- Phase 11B.4 v11B.4.3 Fix 2 — vendor intelligence digest template.
     PII-free: only marketplace-side aggregates + product names.
     Never renders customer names / emails / order IDs / payment data.
     v11B.4.3 audit: removed __(…, [], 'en') locale-forcing that was
     leaking English strings into Arabic emails. All __() calls now
     honor the vendor's Mail::to()->locale() setting from the job. --}}

# {{ __('vendor_intelligence.digest.greeting', ['store' => $vendor->business_name ?? 'Vendor']) }}

{{ __('vendor_intelligence.digest.intro') }}

## {{ __('vendor_intelligence.digest.summary_heading') }}

@component('mail::table')
| {{ __('vendor_intelligence.digest.metric') }} | {{ __('vendor_intelligence.digest.count') }} |
| :--- | ---: |
| {{ __('vendor_intelligence.reports.active_alerts') }} | {{ $summary['active_alerts_count'] ?? 0 }} |
| {{ __('vendor_intelligence.digest.critical') }} | {{ $summary['critical_alerts_count'] ?? 0 }} |
| {{ __('vendor_intelligence.digest.high') }} | {{ $summary['high_alerts_count'] ?? 0 }} |
| {{ __('vendor_intelligence.summary.out_of_stock') }} | {{ $summary['out_of_stock_count'] ?? 0 }} |
| {{ __('vendor_intelligence.summary.low_stock') }} | {{ $summary['low_stock_count'] ?? 0 }} |
| {{ __('vendor_intelligence.reports.quality') }} | {{ $summary['avg_product_quality'] ?? 0 }}% |
@endcomponent

@if(!empty($topAlerts))
## {{ __('vendor_intelligence.digest.top_alerts_heading') }}

@php
    // v11B.4.3 audit: safe alert-type title resolver. When the
    // localization key exists it's returned; when it doesn't, __() gives
    // back the key path (non-empty), so we detect that and fall back to
    // a humanized version of the alert_type string instead of showing
    // the raw dotted key to the vendor.
    $resolveAlertTitle = function (string $type): string {
        $key = 'vendor_intelligence.alerts.' . $type . '.title';
        $translated = __($key);
        return $translated === $key
            ? ucfirst(str_replace('_', ' ', $type))
            : $translated;
    };
@endphp

@foreach($topAlerts as $alert)
- **{{ $resolveAlertTitle($alert['alert_type']) }}** — {{ $alert['evidence']['product_name'] ?? $alert['evidence']['variant_label'] ?? $alert['evidence']['search_term'] ?? '' }}
@endforeach
@endif

@component('mail::button', ['url' => $dashboardUrl])
{{ __('vendor_intelligence.digest.cta') }}
@endcomponent

{{ __('vendor_intelligence.digest.footer_note') }}

{{ __('vendor_intelligence.digest.regards') }},<br>
{{ config('app.name') }}
@endcomponent
