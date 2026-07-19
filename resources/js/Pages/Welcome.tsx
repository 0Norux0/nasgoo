import { Link, usePage } from '@inertiajs/react';
import {
    Shield, BadgeCheck, Headset, Package,
    Truck, Sparkles, ArrowRight, Search,
} from 'lucide-react';
import { StorefrontLayout } from '@/Layouts/StorefrontLayout';
import { Button } from '@/Components/ui/v11/Button';
import { Card, Badge, SectionHeading, TrustBadge } from '@/Components/ui/v11/primitives';
import { ProductCard, type ProductCardProduct } from '@/Components/ui/v11/ProductCard';
import Container from '@/Components/Layout/Container';
import PersonalizedSections from '@/Components/Personalization/PersonalizedSections';  // v11B.3
import type { SharedProps } from '@/types/inertia';
import { useT } from '@/lib/i18n';

// ─── Backend prop shape (unchanged from v10.x — HomeController preserved) ───

interface FeaturedProduct {
    slug: string;
    name: string;
    price: string;
    currency: string;
    thumb: string | null;
    vendor_name: string | null;
    // Phase 10 v10.8 — promotion-aware pricing
    final_price?: string | null;
    discount?: string | null;
    promotion?: {
        id: number;
        title: string;
        type: string;
        discount_type: string;
        discount_value: string;
        badge: string;
    } | null;
}

interface WelcomeProps {
    phase: string;
    health: {
        database: boolean;
        redis: boolean;
        meilisearch: boolean;
        storage: boolean;
    };
    featured_products: FeaturedProduct[];
    // Phase 11B.3 — personalized homepage payload from PersonalizationManager
    personalization?: {
        enabled: boolean;
        sections: Array<{
            type: string;
            title_key: string;
            reason_key: string;
            items: Array<{
                id: number;
                slug: string;
                name: string;
                price: string;
                final_price: string | null;
                discount: string | null;
                promotion: {
                    id: number; title: string; type: string;
                    discount_type: string; discount_value: string; badge: string;
                } | null;
                currency: string;
                thumb: string | null;
                vendor_name: string | null;
            }>;
        }>;
    };
}

// Map backend FeaturedProduct → ProductCard expected shape
function toCard(p: FeaturedProduct): ProductCardProduct {
    return {
        id: p.slug.length, // backend already returns slug; use it as a stable React key
        slug: p.slug,
        title: p.name,
        image_url: p.thumb,
        vendor_name: p.vendor_name,
        final_price: p.final_price ?? p.price,
        original_price: p.promotion ? p.price : null,
        currency: p.currency,
        promo_label: p.promotion?.badge ?? null,
    };
}

function localizedSetting(value: unknown, locale = 'en'): string {
    if (typeof value === 'string') {
        return value;
    }

    if (value && typeof value === 'object') {
        const record = value as Record<string, unknown>;
        const localized = record[locale] ?? record.en;

        return typeof localized === 'string' ? localized : '';
    }

    return '';
}

export default function Welcome({ featured_products, personalization }: WelcomeProps) {
    const { app, auth, top_categories, siteSettings } =
        usePage<SharedProps & WelcomeProps>().props as SharedProps & WelcomeProps;
    const user = auth.user;
    const t = useT();

    // Phase 11B.3 v11B.3.3 §8 — respect siteSettings.homepage.sections.*.enabled
    // so the admin's toggle in /admin/site-settings ACTUALLY hides/shows the
    // corresponding section on the live homepage. Default enabled=true
    // preserves pre-v11B.3.3 behavior for any section not in settings.
    //
    // Full reordering by section_order is deferred (documented in v11B.3.3
    // report's Limitations section) — this release makes enable/disable work,
    // which is the immediately-useful admin capability.
    const homepageSections = (siteSettings?.homepage?.sections as Record<string, ({ enabled?: boolean } & Record<string, unknown>)> | undefined) ?? {};
    const isSectionEnabled = (key: string): boolean =>
        (homepageSections[key]?.enabled ?? true) === true;
    const heroSettings = homepageSections.hero ?? {};
    const customBannerSettings = homepageSections.custom_banner ?? {};
    const heroHeading = localizedSetting(heroSettings.heading, app.locale) || t('home.hero.title_line1');
    const heroSubheading = localizedSetting(heroSettings.subheading, app.locale) || t('home.hero.subtitle', { name: app.name });
    const heroCtaLabel = localizedSetting(heroSettings.cta_label, app.locale) || t('home.hero.cta_shop');
    const heroCtaUrl = typeof heroSettings.cta_url === 'string' && heroSettings.cta_url !== ''
        ? heroSettings.cta_url
        : '/products';
    const heroImageUrl = typeof heroSettings.image_url === 'string' && heroSettings.image_url !== ''
        ? heroSettings.image_url
        : null;
    const heroCardImages = Array.isArray(heroSettings.card_images)
        ? heroSettings.card_images
            .filter((image): image is string => typeof image === 'string' && image.trim() !== '')
            .slice(0, 4)
        : [];
    const customBannerImage = typeof customBannerSettings.image_url === 'string' && customBannerSettings.image_url !== ''
        ? customBannerSettings.image_url
        : null;
    const customBannerHeading = localizedSetting(customBannerSettings.heading, app.locale);
    const customBannerCtaUrl = typeof customBannerSettings.cta_url === 'string' && customBannerSettings.cta_url !== ''
        ? customBannerSettings.cta_url
        : null;

    return (
        <StorefrontLayout title="Welcome">
            {/* ─────────── HERO ─────────── */}
            <section
                className="relative bg-gradient-to-br from-brand-800 via-brand-900 to-brand-ink overflow-hidden"
                data-testid="homepage-hero"
            >
                {/* Decorative blobs (purely visual, aria-hidden) */}
                <div className="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
                    <div className="absolute -top-24 -end-24 size-96 rounded-full bg-accent-500/20 blur-3xl" />
                    <div className="absolute -bottom-32 -start-24 size-96 rounded-full bg-brand-500/30 blur-3xl" />
                </div>

                <Container className="relative py-16 sm:py-20 lg:py-28">
                    <div className="grid lg:grid-cols-12 gap-12 items-center">
                        <div className="lg:col-span-7">
                            <Badge variant="trust" size="sm" className="mb-5 bg-white/10 text-accent-200 border-accent-300/30">
                                <Sparkles size={12} aria-hidden="true" />
                                {featured_products.length > 0 ? t('home.hero.eyebrow_featured', { count: featured_products.length }) : t('home.hero.eyebrow_verified')}
                            </Badge>

                            <h1 className="font-display text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight tracking-tight">
                                {heroHeading}<br />
                                <span className="text-accent-300">{t('home.hero.title_line2')}</span>
                            </h1>

                            <p className="mt-5 text-lg lg:text-xl text-brand-100 max-w-2xl leading-relaxed">
                                {heroSubheading}
                            </p>

                            <div className="mt-8 flex flex-wrap gap-3">
                                <Button href={heroCtaUrl} variant="accent" size="lg" trailingIcon={<ArrowRight size={18} aria-hidden="true" />}>
                                    {heroCtaLabel}
                                </Button>
                                <Button
                                    href="/services"
                                    variant="secondary"
                                    size="lg"
                                    className="bg-white/10 border-white/20 text-white hover:bg-white/20 hover:border-white/30"
                                >
                                    {t('home.hero.cta_services')}
                                </Button>
                            </div>

                            {/* Trust micro-row inside hero */}
                            <div className="mt-10 grid grid-cols-2 sm:grid-cols-3 gap-6 max-w-xl">
                                <div className="flex items-center gap-2.5">
                                    <Shield size={20} className="text-accent-300 shrink-0" aria-hidden="true" />
                                    <span className="text-sm text-brand-100">{t('home.hero.trust_secure')}</span>
                                </div>
                                <div className="flex items-center gap-2.5">
                                    <BadgeCheck size={20} className="text-accent-300 shrink-0" aria-hidden="true" />
                                    <span className="text-sm text-brand-100">{t('home.hero.trust_verified')}</span>
                                </div>
                                <div className="flex items-center gap-2.5">
                                    <Headset size={20} className="text-accent-300 shrink-0" aria-hidden="true" />
                                    <span className="text-sm text-brand-100">{t('home.hero.trust_support')}</span>
                                </div>
                            </div>
                        </div>

                        {/* Hero media */}
                        <div className="hidden lg:block lg:col-span-5">
                            <div className="relative">
                                <div className="absolute inset-0 bg-gradient-to-br from-accent-400/20 to-transparent rounded-3xl blur-2xl" aria-hidden="true" />
                                <Card variant="elevated" padding="lg" className="relative shadow-hero rotate-2 hover:rotate-0 transition-transform duration-300">
                                    {heroImageUrl ? (
                                        <img
                                            src={heroImageUrl}
                                            alt=""
                                            className="aspect-[4/3] w-full rounded-xl object-cover"
                                            data-testid="homepage-hero-image"
                                        />
                                    ) : (
                                        <>
                                            <div className="flex items-center gap-3 mb-4">
                                                <div className="size-12 rounded-xl bg-accent-100 grid place-items-center">
                                                    <Package size={22} className="text-accent-700" aria-hidden="true" />
                                                </div>
                                                <div>
                                                    <p className="text-xs text-slate-600">{t('home.hero.featured_today')}</p>
                                                    <p className="font-semibold text-slate-900">{t('home.hero.premium_selection')}</p>
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                {[0, 1, 2, 3].map((index) => {
                                                    const image = heroCardImages[index];

                                                    return image ? (
                                                        <img
                                                            key={index}
                                                            src={image}
                                                            alt=""
                                                            className="aspect-square w-full rounded-xl object-cover"
                                                            data-testid="homepage-hero-card-image"
                                                        />
                                                    ) : (
                                                        <div
                                                            key={index}
                                                            className="aspect-square rounded-xl bg-gradient-to-br from-slate-100 to-slate-200"
                                                            aria-hidden="true"
                                                        />
                                                    );
                                                })}
                                            </div>
                                            <div className="mt-4 flex items-center justify-between text-sm">
                                                <span className="text-slate-600">{t('home.hero.across_categories', { count: top_categories.length })}</span>
                                                <span className="font-semibold text-accent-700">{t('home.hero.view_all')}</span>
                                            </div>
                                        </>
                                    )}
                                </Card>
                            </div>
                        </div>
                    </div>
                </Container>
            </section>

            {isSectionEnabled('custom_banner') && customBannerImage && (
                <section className="bg-white" data-testid="homepage-custom-banner">
                    <Container className="py-8">
                        <Link
                            href={customBannerCtaUrl ?? '#'}
                            className="relative block overflow-hidden rounded-2xl border border-slate-200"
                        >
                            <img src={customBannerImage} alt="" className="h-56 w-full object-cover sm:h-72" />
                            {customBannerHeading && (
                                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-6">
                                    <p className="max-w-2xl text-2xl font-bold text-white">{customBannerHeading}</p>
                                </div>
                            )}
                        </Link>
                    </Container>
                </section>
            )}

            {/* ─────────── TRUST INDICATORS ─────────── */}
            <section
                className="bg-white border-b border-slate-100"
                data-testid="homepage-trust"
            >
                <Container className="py-10 lg:py-14">
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-10">
                        <TrustBadge
                            icon={<Shield size={22} aria-hidden="true" />}
                            title={t('home.trust.secure_title')}
                            body={t('home.trust.secure_body')}
                        />
                        <TrustBadge
                            icon={<BadgeCheck size={22} aria-hidden="true" />}
                            title={t('home.trust.verified_title')}
                            body={t('home.trust.verified_body')}
                        />
                        <TrustBadge
                            icon={<Truck size={22} aria-hidden="true" />}
                            title={t('home.trust.delivery_title')}
                            body={t('home.trust.delivery_body')}
                        />
                        <TrustBadge
                            icon={<Headset size={22} aria-hidden="true" />}
                            title={t('home.trust.support_title')}
                            body={t('home.trust.support_body')}
                        />
                    </div>
                </Container>
            </section>

            {/* ─────────── PHASE 11B.3 PERSONALIZED SECTIONS ─────────── */}
            {personalization && (
                <PersonalizedSections
                    enabled={personalization.enabled}
                    sections={personalization.sections}
                />
            )}

            {/* ─────────── FEATURED CATEGORIES ─────────── */}
            {isSectionEnabled('categories') && top_categories.length > 0 && (
                <section className="bg-slate-50" data-testid="homepage-categories">
                    <Container className="py-12 lg:py-16">
                        <SectionHeading
                            eyebrow={t('home.categories.eyebrow')}
                            title={t('home.categories.title')}
                            subtitle={t('home.categories.subtitle', { count: top_categories.length })}
                            cta={
                                <Button href="/products" variant="ghost" size="sm" trailingIcon={<ArrowRight size={16} aria-hidden="true" />}>
                                    {t('home.categories.view_all')}
                                </Button>
                            }
                        />

                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                            {top_categories.slice(0, 12).map((c) => (
                                <Link
                                    key={c.slug}
                                    href={`/products?category=${c.slug}`}
                                    className="group block"
                                    data-testid="homepage-category-tile"
                                >
                                    <Card
                                        variant="interactive"
                                        padding="md"
                                        className="text-center hover:border-brand-300 hover:bg-brand-50 transition-colors"
                                    >
                                        <div className="size-12 mx-auto rounded-xl bg-brand-50 grid place-items-center text-brand-700 group-hover:bg-white transition-colors">
                                            <Package size={22} aria-hidden="true" />
                                        </div>
                                        <p className="mt-3 font-semibold text-sm text-slate-900 group-hover:text-brand-800 line-clamp-2">
                                            {c.name}
                                        </p>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    </Container>
                </section>
            )}

            {/* ─────────── FEATURED PRODUCTS ─────────── */}
            {isSectionEnabled('featured') && (
            <section className="bg-white" data-testid="homepage-featured-products">
                <Container className="py-12 lg:py-16">
                    <SectionHeading
                        eyebrow={t('home.featured.eyebrow')}
                        title={featured_products.length > 0 ? t('home.featured.title') : t('home.featured.title_empty')}
                        subtitle={
                            featured_products.length > 0
                                ? t('home.featured.subtitle')
                                : t('home.featured.subtitle_empty')
                        }
                        cta={
                            <Button href="/products" variant="primary" size="sm" trailingIcon={<ArrowRight size={16} aria-hidden="true" />}>
                                See all products
                            </Button>
                        }
                    />

                    {featured_products.length > 0 ? (
                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                            {featured_products.slice(0, 8).map((p) => (
                                <ProductCard key={p.slug} product={toCard(p)} />
                            ))}
                        </div>
                    ) : (
                        <Card variant="default" padding="lg" className="text-center">
                            <Search size={36} className="mx-auto text-slate-400" aria-hidden="true" />
                            <p className="mt-3 font-semibold text-slate-900">
                                {t('home.featured.empty_title')}
                            </p>
                            <p className="mt-1 text-sm text-slate-600">
                                {t('home.featured.empty_body')}
                            </p>
                            <div className="mt-5">
                                <Button href="/products" variant="primary">{t('home.featured.empty_cta')}</Button>
                            </div>
                        </Card>
                    )}
                </Container>
            </section>
            )}

            {/* ─────────── DEALS BANNER ─────────── */}
            <section
                className="bg-gradient-to-r from-gold-500 to-gold-600"
                data-testid="homepage-deals-banner"
            >
                <Container className="py-10 lg:py-14">
                    <div className="flex flex-col lg:flex-row items-center justify-between gap-6">
                        <div className="text-center lg:text-start">
                            <div className="inline-flex items-center gap-2 text-gold-900 font-semibold text-sm uppercase tracking-widest mb-2">
                                <Sparkles size={16} aria-hidden="true" />
                                {t('home.deals.eyebrow')}
                            </div>
                            <h2 className="font-display text-2xl sm:text-3xl lg:text-4xl font-bold text-gold-950">
                                {t('home.deals.title')}
                            </h2>
                            <p className="mt-2 text-gold-900 max-w-xl">
                                {t('home.deals.subtitle')}
                            </p>
                        </div>
                        <Button
                            href="/deals"
                            variant="primary"
                            size="lg"
                            className="bg-gold-950 hover:bg-gold-900 text-white shrink-0"
                            trailingIcon={<ArrowRight size={18} aria-hidden="true" />}
                        >
                            {t('home.deals.cta')}
                        </Button>
                    </div>
                </Container>
            </section>

            {/* ─────────── SERVICES ─────────── */}
            {isSectionEnabled('services') && (
            <section className="bg-slate-50" data-testid="homepage-services">
                <Container className="py-12 lg:py-16">
                    <SectionHeading
                        eyebrow={t('home.services.eyebrow')}
                        title={t('home.services.title')}
                        subtitle={t('home.services.subtitle')}
                        cta={
                            <Button href="/services" variant="primary" size="sm" trailingIcon={<ArrowRight size={16} aria-hidden="true" />}>
                                Browse services
                            </Button>
                        }
                    />

                    <Card variant="elevated" padding="lg" className="overflow-hidden">
                        <div className="grid lg:grid-cols-2 gap-8 items-center">
                            <div>
                                <Badge variant="trust" size="md">Services marketplace</Badge>
                                <h3 className="mt-3 font-display text-2xl font-bold text-slate-900">
                                    Find a provider, book a time, get it done
                                </h3>
                                <p className="mt-2 text-slate-600 leading-relaxed">
                                    Our service vendors are reviewed and ready to take on your project. Browse providers, check availability, and book confirmed slots — all without leaving the marketplace.
                                </p>
                                <ul className="mt-5 space-y-2.5 text-sm">
                                    {[
                                        'See real availability calendars',
                                        'Read verified customer reviews',
                                        'Confirm booking with secure payment',
                                        'Track your bookings in one place',
                                    ].map((item) => (
                                        <li key={item} className="flex items-start gap-2">
                                            <BadgeCheck size={18} className="text-accent-600 shrink-0 mt-0.5" aria-hidden="true" />
                                            <span className="text-slate-700">{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            <div className="aspect-video lg:aspect-square rounded-2xl bg-gradient-to-br from-accent-100 via-accent-50 to-brand-50 grid place-items-center" aria-hidden="true">
                                <div className="size-32 rounded-3xl bg-white/80 shadow-card grid place-items-center">
                                    <Headset size={56} className="text-accent-700" />
                                </div>
                            </div>
                        </div>
                    </Card>
                </Container>
            </section>
            )}

            {/* ─────────── HOW IT WORKS ─────────── */}
            <section className="bg-white" data-testid="homepage-how-it-works">
                <Container className="py-12 lg:py-16">
                    <SectionHeading
                        eyebrow={t('home.howit.eyebrow')}
                        title={t('home.howit.title')}
                        subtitle={t('home.howit.subtitle')}
                        align="center"
                    />

                    <div className="grid sm:grid-cols-3 gap-6 lg:gap-8 max-w-5xl mx-auto">
                        {[
                            {
                                step: '01',
                                title: 'Browse with confidence',
                                body: 'Search across products and services from verified vendors. Filter by category, price, and rating.',
                                icon: <Search size={22} aria-hidden="true" />,
                            },
                            {
                                step: '02',
                                title: 'Buy or book securely',
                                body: 'Add to cart, apply coupons, and pay through encrypted payment gateways. Your details stay protected.',
                                icon: <Shield size={22} aria-hidden="true" />,
                            },
                            {
                                step: '03',
                                title: 'Get it delivered',
                                body: 'Track your orders and bookings, message vendors, and leave reviews to help the community.',
                                icon: <Truck size={22} aria-hidden="true" />,
                            },
                        ].map((s) => (
                            <Card key={s.step} variant="default" padding="lg" className="text-center">
                                <div className="size-14 mx-auto rounded-2xl bg-brand-50 grid place-items-center text-brand-700">
                                    {s.icon}
                                </div>
                                <p className="mt-3 text-xs font-semibold uppercase tracking-widest text-brand-700">
                                    Step {s.step}
                                </p>
                                <h3 className="mt-1 font-display text-lg font-bold text-slate-900">
                                    {s.title}
                                </h3>
                                <p className="mt-2 text-sm text-slate-600 leading-relaxed">
                                    {s.body}
                                </p>
                            </Card>
                        ))}
                    </div>
                </Container>
            </section>

            {/* ─────────── BECOME A VENDOR CTA ─────────── */}
            {!user?.roles.includes('vendor') && (
                <section className="bg-brand-50 border-y border-brand-100" data-testid="homepage-vendor-cta">
                    <Container className="py-12 lg:py-16">
                        <Card variant="elevated" padding="lg" className="bg-gradient-to-br from-brand-800 to-brand-ink border-0">
                            <div className="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 text-white">
                                <div>
                                    <Badge variant="trust" size="md" className="bg-white/10 text-accent-200 border-accent-300/30">
                                        For vendors
                                    </Badge>
                                    <h2 className="mt-3 font-display text-2xl sm:text-3xl font-bold">
                                        {t('home.vendor.title', { name: app.name })}
                                    </h2>
                                    <p className="mt-2 text-brand-100 max-w-2xl">
                                        Join the marketplace, reach new customers, and grow your business. Apply in minutes; approval typically within a few business days.
                                    </p>
                                </div>
                                <Button
                                    href={user ? '/vendor/apply' : '/login?redirect=/vendor/apply'}
                                    variant="accent"
                                    size="lg"
                                    className="shrink-0"
                                    trailingIcon={<ArrowRight size={18} aria-hidden="true" />}
                                >
                                    Become a vendor
                                </Button>
                            </div>
                        </Card>
                    </Container>
                </section>
            )}
        </StorefrontLayout>
    );
}
