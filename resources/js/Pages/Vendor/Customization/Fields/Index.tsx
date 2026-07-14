import { Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import type { SharedProps } from '@/types/inertia';

interface FieldOption { value: string; label: string; extra_fee?: number }
interface Field {
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
    options: FieldOption[] | null;
    is_active: boolean;
}
interface ProductInfo { id: number; name: string; type: string; is_customizable: boolean }

type FieldsIndexPageProps = SharedProps & {
    product: ProductInfo;
    fields: Field[];
};

const FIELD_TYPES = [
    { value: 'image',     label: 'Image upload' },
    { value: 'text',      label: 'Text input (one line)' },
    { value: 'textarea',  label: 'Textarea / instructions' },
    { value: 'color',     label: 'Color selection' },
    { value: 'font',      label: 'Font selection' },
    { value: 'placement', label: 'Placement option' },
    { value: 'dropdown',  label: 'Generic dropdown' },
    { value: 'size',      label: 'Size selection' },
    { value: 'checkbox',  label: 'Checkbox' },
];

const FILE_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'pdf'];

export default function Index() {
    const { props } = usePage<FieldsIndexPageProps>();
    const { product, fields, flash } = props;
    const [showForm, setShowForm] = useState(false);

    const empty = {
        label: '', type: 'text', required: false,
        sort_order: fields.length,
        allowed_file_types: [] as string[],
        max_file_size_kb: 2048,
        max_text_length: 200,
        extra_fee_minor: 0,
        placeholder: '',
        helper_text: '',
        options: [] as FieldOption[],
        is_active: true,
    };
    const { data, setData, post, processing, errors, reset } = useForm<typeof empty>(empty);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/vendor/products/${product.id}/customization-fields`, {
            onSuccess: () => { reset(); setShowForm(false); },
        });
    };

    const isFile  = data.type === 'image';
    const isText  = data.type === 'text' || data.type === 'textarea';
    const isOpts  = ['color','font','placement','dropdown','size'].includes(data.type);

    const addOption = () => setData('options', [...data.options, { value: '', label: '', extra_fee: 0 }]);
    const setOption = (i: number, key: keyof FieldOption, v: string | number) => {
        const next = data.options.slice();
        next[i] = { ...next[i], [key]: key === 'extra_fee' ? Number(v) : String(v) };
        setData('options', next);
    };
    const removeOption = (i: number) => setData('options', data.options.filter((_, j) => j !== i));

    const deleteField = (fieldId: number) => {
        if (!window.confirm('Delete this customization field?')) return;
        // Use Inertia's router for the DELETE call
        import('@inertiajs/react').then(({ router }) =>
            router.delete(`/vendor/products/${product.id}/customization-fields/${fieldId}`, { preserveScroll: true })
        );
    };

    return (
        <VendorLayout title={`Customization fields — ${product.name}`}>
            <div className="max-w-4xl mx-auto px-4 py-6">
                {flash?.success && <div className="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded text-sm">{flash.success}</div>}
                {flash?.error && <div className="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded text-sm">{flash.error}</div>}

                {!product.is_customizable && (
                    <div className="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded text-sm">
                        Note: this product's type is <strong>{product.type}</strong>, not <strong>custom</strong>.
                        Customization fields will only show on the storefront after you set the product type to <strong>custom</strong>.
                    </div>
                )}

                <div className="flex items-center justify-between mb-4">
                    <div>
                        <h2 className="font-medium text-slate-800">{product.name}</h2>
                        <p className="text-sm text-slate-500">{fields.length} field{fields.length === 1 ? '' : 's'} defined</p>
                    </div>
                    {!showForm && (
                        <button onClick={() => setShowForm(true)} className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-3 py-1.5 rounded">
                            + Add field
                        </button>
                    )}
                </div>

                {showForm && (
                    <form onSubmit={submit} className="bg-white border border-slate-200 rounded p-4 mb-4 space-y-3">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label className="block">
                                <span className="text-sm text-slate-700 block mb-1">Label</span>
                                <input value={data.label} onChange={(e) => setData('label', e.target.value)}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full"
                                    placeholder="e.g. Your name on the mug" />
                                {errors.label && <span className="text-xs text-rose-600">{errors.label}</span>}
                            </label>
                            <label className="block">
                                <span className="text-sm text-slate-700 block mb-1">Type</span>
                                <select value={data.type} onChange={(e) => setData('type', e.target.value)}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full">
                                    {FIELD_TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                                </select>
                            </label>
                        </div>

                        <div className="flex items-center gap-4">
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={data.required}
                                    onChange={(e) => setData('required', e.target.checked)} />
                                <span>Required</span>
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)} />
                                <span>Active (visible on storefront)</span>
                            </label>
                            <label className="text-sm flex items-center gap-2">
                                Order:
                                <input type="number" min={0} max={999} value={data.sort_order}
                                    onChange={(e) => setData('sort_order', Number(e.target.value))}
                                    className="border border-slate-300 rounded px-2 py-1 text-sm w-20" />
                            </label>
                            <label className="text-sm flex items-center gap-2">
                                Extra fee (cents):
                                <input type="number" min={0} value={data.extra_fee_minor}
                                    onChange={(e) => setData('extra_fee_minor', Number(e.target.value))}
                                    className="border border-slate-300 rounded px-2 py-1 text-sm w-28" />
                            </label>
                        </div>

                        {isFile && (
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-slate-50 border border-slate-200 rounded p-3">
                                <label className="block">
                                    <span className="text-sm text-slate-700 block mb-1">Allowed file types</span>
                                    <div className="flex flex-wrap gap-2">
                                        {FILE_EXTS.map((ext) => (
                                            <label key={ext} className="text-sm flex items-center gap-1">
                                                <input type="checkbox"
                                                    checked={data.allowed_file_types.includes(ext)}
                                                    onChange={(e) => setData('allowed_file_types',
                                                        e.target.checked
                                                            ? [...data.allowed_file_types, ext]
                                                            : data.allowed_file_types.filter((x) => x !== ext))} />
                                                {ext}
                                            </label>
                                        ))}
                                    </div>
                                </label>
                                <label className="block">
                                    <span className="text-sm text-slate-700 block mb-1">Max file size (KB)</span>
                                    <input type="number" min={1} max={51200} value={data.max_file_size_kb}
                                        onChange={(e) => setData('max_file_size_kb', Number(e.target.value))}
                                        className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                                </label>
                            </div>
                        )}

                        {isText && (
                            <label className="block">
                                <span className="text-sm text-slate-700 block mb-1">Max length (characters)</span>
                                <input type="number" min={1} max={5000} value={data.max_text_length}
                                    onChange={(e) => setData('max_text_length', Number(e.target.value))}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-40" />
                            </label>
                        )}

                        {isOpts && (
                            <div className="bg-slate-50 border border-slate-200 rounded p-3">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm text-slate-700">Options</span>
                                    <button type="button" onClick={addOption} className="text-xs px-2 py-1 bg-slate-200 hover:bg-slate-300 rounded">+ Option</button>
                                </div>
                                {data.options.length === 0 && <p className="text-xs text-slate-500">No options yet.</p>}
                                {data.options.map((o, i) => (
                                    <div key={i} className="grid grid-cols-12 gap-2 mb-2">
                                        <input placeholder="value" value={o.value}
                                            onChange={(e) => setOption(i, 'value', e.target.value)}
                                            className="border border-slate-300 rounded px-2 py-1 text-sm col-span-3" />
                                        <input placeholder="label" value={o.label}
                                            onChange={(e) => setOption(i, 'label', e.target.value)}
                                            className="border border-slate-300 rounded px-2 py-1 text-sm col-span-5" />
                                        <input type="number" min={0} placeholder="extra fee (cents)" value={o.extra_fee ?? 0}
                                            onChange={(e) => setOption(i, 'extra_fee', e.target.value)}
                                            className="border border-slate-300 rounded px-2 py-1 text-sm col-span-3" />
                                        <button type="button" onClick={() => removeOption(i)} className="text-rose-600 text-xs col-span-1">remove</button>
                                    </div>
                                ))}
                            </div>
                        )}

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label className="block">
                                <span className="text-sm text-slate-700 block mb-1">Placeholder</span>
                                <input value={data.placeholder} onChange={(e) => setData('placeholder', e.target.value)}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                            </label>
                            <label className="block">
                                <span className="text-sm text-slate-700 block mb-1">Helper text</span>
                                <input value={data.helper_text} onChange={(e) => setData('helper_text', e.target.value)}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                            </label>
                        </div>

                        <div className="flex gap-2">
                            <button type="submit" disabled={processing}
                                className="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm px-4 py-1.5 rounded">
                                {processing ? 'Saving…' : 'Save field'}
                            </button>
                            <button type="button" onClick={() => { reset(); setShowForm(false); }}
                                className="text-sm text-slate-600 px-3 py-1.5">Cancel</button>
                        </div>
                    </form>
                )}

                {/* Existing fields list */}
                <div className="bg-white border border-slate-200 rounded">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-xs uppercase text-slate-700">
                            <tr>
                                <th className="text-left px-3 py-2">Order</th>
                                <th className="text-left px-3 py-2">Label</th>
                                <th className="text-left px-3 py-2">Key</th>
                                <th className="text-left px-3 py-2">Type</th>
                                <th className="text-left px-3 py-2">Required</th>
                                <th className="text-left px-3 py-2">Active</th>
                                <th className="text-left px-3 py-2">Fee</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {fields.length === 0 && (
                                <tr><td colSpan={8} className="text-center text-slate-500 py-6">
                                    No customization fields yet. Add fields above so customers can personalize this product.
                                </td></tr>
                            )}
                            {fields.map((f) => (
                                <tr key={f.id} className="border-t border-slate-100">
                                    <td className="px-3 py-2 text-slate-500">{f.sort_order}</td>
                                    <td className="px-3 py-2 text-slate-900">{f.label}</td>
                                    <td className="px-3 py-2 font-mono text-xs text-slate-500">{f.key}</td>
                                    <td className="px-3 py-2"><span className="bg-slate-100 text-xs rounded px-1.5 py-0.5">{f.type}</span></td>
                                    <td className="px-3 py-2">{f.required ? '✓' : '—'}</td>
                                    <td className="px-3 py-2">{f.is_active ? '✓' : '—'}</td>
                                    <td className="px-3 py-2 text-slate-700">{f.extra_fee_minor > 0 ? (f.extra_fee_minor/100).toFixed(2) : '—'}</td>
                                    <td className="px-3 py-2 text-right">
                                        <button onClick={() => deleteField(f.id)} className="text-xs text-rose-600 hover:underline">Delete</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="mt-3 text-xs text-slate-500">
                    <Link href="/vendor/products" className="text-indigo-600 hover:underline">← Back to products</Link>
                </div>
            </div>
        </VendorLayout>
    );
}
