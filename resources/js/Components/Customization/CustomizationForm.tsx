import { useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';

export interface CustomizationFieldDef {
    id: number;
    key: string;
    label: string;
    type: string;
    required: boolean;
    sort_order: number;
    allowed_file_types: string[] | null;
    max_file_size_kb: number | null;
    max_text_length: number | null;
    extra_fee_minor: number;
    placeholder: string | null;
    helper_text: string | null;
    options: { value: string; label: string; extra_fee?: number }[] | null;
}

interface Props {
    productId: number;
    variantId?: number | null;
    fields: CustomizationFieldDef[];
    currency: string;
    onSubmitted?: () => void;
}

/**
 * Phase 7 — customer-facing customization form for active fields on a
 * customizable product. Submits multipart/form-data to /cart/items/customized
 * with the proper `customizations[<key>]` payload (text values + uploaded
 * files in the same nested map).
 *
 * Server-side validation errors are surfaced inline per field via Inertia's
 * useForm errors map (keyed `customizations.{key}`).
 */
export default function CustomizationForm({ productId, variantId, fields, currency, onSubmitted }: Props) {
    const initialText: Record<string, string | boolean> = {};
    for (const f of fields) {
        if (f.type === 'checkbox') initialText[f.key] = false;
        else if (f.type !== 'image') initialText[f.key] = '';
    }

    const [textState, setTextState] = useState<Record<string, string | boolean>>(initialText);
    const [files, setFiles] = useState<Record<string, File | null>>({});
    const [quantity, setQuantity] = useState(1);

    const { setData, post, processing, errors, progress } = useForm<{
        product_id: number;
        variant_id: number | null;
        quantity: number;
        customizations: Record<string, string | boolean | File | null>;
    }>({
        product_id: productId,
        variant_id: variantId ?? null,
        quantity: 1,
        customizations: { ...initialText },
    });

    const updateText = (key: string, value: string | boolean) => {
        setTextState((prev) => ({ ...prev, [key]: value }));
    };
    const updateFile = (key: string, file: File | null) => {
        setFiles((prev) => ({ ...prev, [key]: file }));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        // Flush combined state into the form payload right before submit
        setData((prev) => ({
            ...prev,
            quantity,
            customizations: { ...textState, ...files },
        }));
        post('/cart/items/customized', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { onSubmitted?.(); },
        });
    };

    const fmtFee = (cents: number) => `+${(cents / 100).toFixed(2)} ${currency}`;
    const errOf = (key: string): string | undefined => (errors as Record<string, string>)[`customizations.${key}`];

    return (
        <form onSubmit={submit} className="bg-white border border-slate-200 rounded p-4 space-y-3" encType="multipart/form-data">
            <h3 className="font-medium text-slate-800">Customize this product</h3>

            {fields.map((f) => {
                const err = errOf(f.key);
                const wrapper = (children: ReactNode) => (
                    <label key={f.id} className="block">
                        <span className="text-sm text-slate-700 block mb-1">
                            {f.label}
                            {f.required && <span className="text-rose-600 ml-1">*</span>}
                            {f.extra_fee_minor > 0 && <span className="text-xs text-slate-500 ml-2">{fmtFee(f.extra_fee_minor)}</span>}
                        </span>
                        {children}
                        {f.helper_text && <span className="text-xs text-slate-500 block mt-0.5">{f.helper_text}</span>}
                        {err && <span className="text-xs text-rose-600 block mt-0.5">{err}</span>}
                    </label>
                );

                if (f.type === 'image') {
                    return wrapper(
                        <>
                            <input type="file"
                                accept={(f.allowed_file_types ?? ['jpg','png','webp']).map((e) => '.' + e).join(',')}
                                onChange={(e) => updateFile(f.key, e.target.files?.[0] ?? null)}
                                className="text-sm" />
                            {f.max_file_size_kb && <span className="text-xs text-slate-500 block">Max size: {f.max_file_size_kb} KB</span>}
                        </>
                    );
                }
                if (f.type === 'textarea') {
                    return wrapper(
                        <textarea value={String(textState[f.key] ?? '')}
                            onChange={(e) => updateText(f.key, e.target.value)}
                            rows={3}
                            maxLength={f.max_text_length ?? undefined}
                            placeholder={f.placeholder ?? ''}
                            className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                    );
                }
                if (f.type === 'text') {
                    return wrapper(
                        <input value={String(textState[f.key] ?? '')}
                            onChange={(e) => updateText(f.key, e.target.value)}
                            maxLength={f.max_text_length ?? undefined}
                            placeholder={f.placeholder ?? ''}
                            className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                    );
                }
                if (['color','font','placement','dropdown','size'].includes(f.type)) {
                    return wrapper(
                        <select value={String(textState[f.key] ?? '')}
                            onChange={(e) => updateText(f.key, e.target.value)}
                            className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full">
                            <option value="">— select —</option>
                            {(f.options ?? []).map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}{o.extra_fee ? ` (+${(o.extra_fee/100).toFixed(2)} ${currency})` : ''}
                                </option>
                            ))}
                        </select>
                    );
                }
                if (f.type === 'checkbox') {
                    return (
                        <label key={f.id} className="flex items-center gap-2 text-sm">
                            <input type="checkbox"
                                checked={Boolean(textState[f.key])}
                                onChange={(e) => updateText(f.key, e.target.checked)} />
                            <span>
                                {f.label}
                                {f.required && <span className="text-rose-600 ml-1">*</span>}
                                {f.extra_fee_minor > 0 && <span className="text-xs text-slate-500 ml-2">{fmtFee(f.extra_fee_minor)}</span>}
                            </span>
                            {err && <span className="text-xs text-rose-600 ml-2">{err}</span>}
                        </label>
                    );
                }
                return null;
            })}

            <div className="flex items-center gap-3 pt-2">
                <label className="text-sm flex items-center gap-2">
                    Qty:
                    <input type="number" min={1} max={100} value={quantity}
                        onChange={(e) => setQuantity(Number(e.target.value))}
                        className="border border-slate-300 rounded px-2 py-1 text-sm w-20" />
                </label>
                <button type="submit" disabled={processing}
                    className="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm px-4 py-1.5 rounded">
                    {processing ? 'Adding…' : 'Add customized item to cart'}
                </button>
                {progress && <span className="text-xs text-slate-500">{progress.percentage}%</span>}
            </div>
        </form>
    );
}
