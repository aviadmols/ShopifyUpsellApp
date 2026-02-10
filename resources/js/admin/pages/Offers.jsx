import React, { useEffect, useState } from 'react';
import * as api from '../api';

export default function Offers() {
    const [list, setList] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ title: '', description: '', product_variant_id: '', discount_type: 'none', discount_value: '', image_url: '', rule_id: '' });

    useEffect(() => {
        api.offers().then(setList).catch((e) => setError(e.message)).finally(() => setLoading(false));
    }, []);

    const save = async () => {
        setError('');
        const body = { ...form, discount_value: form.discount_value ? parseFloat(form.discount_value) : null, rule_id: form.rule_id ? parseInt(form.rule_id, 10) : null };
        try {
            if (editing) {
                await api.updateOffer(editing.id, body);
                setList((prev) => prev.map((o) => (o.id === editing.id ? { ...o, ...body } : o)));
            } else {
                const created = await api.createOffer(body);
                setList((prev) => [...prev, created]);
            }
            setEditing(null);
            setForm({ title: '', description: '', product_variant_id: '', discount_type: 'none', discount_value: '', image_url: '', rule_id: '' });
        } catch (e) {
            setError(e.message);
        }
    };

    const remove = async (id) => {
        if (!confirm('Delete this offer?')) return;
        try {
            await api.deleteOffer(id);
            setList((prev) => prev.filter((o) => o.id !== id));
            if (editing?.id === id) setEditing(null);
        } catch (e) {
            setError(e.message);
        }
    };

    if (loading) return <p className="text-gray-500">Loading offers…</p>;
    return (
        <div>
            <h1 className="text-2xl font-bold text-gray-900 mb-4">Offers</h1>
            {error && <p className="text-red-600 mb-4">{error}</p>}
            <div className="bg-white rounded-lg shadow p-4 mb-6">
                <h2 className="font-semibold mb-2">{editing ? 'Edit offer' : 'New offer'}</h2>
                <div className="grid grid-cols-1 gap-2 max-w-xl">
                    <input className="border rounded px-2 py-1" placeholder="Title" value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} />
                    <textarea className="border rounded px-2 py-1" placeholder="Description" value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} />
                    <input className="border rounded px-2 py-1" placeholder="Product variant ID (GID)" value={form.product_variant_id} onChange={(e) => setForm((f) => ({ ...f, product_variant_id: e.target.value }))} />
                    <select className="border rounded px-2 py-1" value={form.discount_type} onChange={(e) => setForm((f) => ({ ...f, discount_type: e.target.value }))}>
                        <option value="none">None</option>
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed</option>
                    </select>
                    <input type="number" className="border rounded px-2 py-1" placeholder="Discount value" value={form.discount_value} onChange={(e) => setForm((f) => ({ ...f, discount_value: e.target.value }))} />
                    <input className="border rounded px-2 py-1" placeholder="Image URL" value={form.image_url} onChange={(e) => setForm((f) => ({ ...f, image_url: e.target.value }))} />
                    <input className="border rounded px-2 py-1" placeholder="Rule ID (optional)" value={form.rule_id} onChange={(e) => setForm((f) => ({ ...f, rule_id: e.target.value }))} />
                </div>
                <div className="mt-2 flex gap-2">
                    <button type="button" className="bg-blue-600 text-white px-3 py-1 rounded" onClick={save}>Save</button>
                    {editing && <button type="button" className="bg-gray-400 text-white px-3 py-1 rounded" onClick={() => { setEditing(null); setForm({ title: '', description: '', product_variant_id: '', discount_type: 'none', discount_value: '', image_url: '', rule_id: '' }); }}>Cancel</button>}
                </div>
            </div>
            <ul className="space-y-2">
                {list.map((o) => (
                    <li key={o.id} className="bg-white rounded shadow p-3 flex justify-between items-center">
                        <span>{o.title} – {o.product_variant_id}</span>
                        <div>
                            <button type="button" className="text-blue-600 mr-2" onClick={() => { setEditing(o); setForm({ title: o.title, description: o.description || '', product_variant_id: o.product_variant_id, discount_type: o.discount_type, discount_value: o.discount_value ?? '', image_url: o.image_url || '', rule_id: o.rule_id ?? '' }); }}>Edit</button>
                            <button type="button" className="text-red-600" onClick={() => remove(o.id)}>Delete</button>
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}
