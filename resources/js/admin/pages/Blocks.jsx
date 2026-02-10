import React, { useEffect, useState } from 'react';
import * as api from '../api';

const defaultConfig = { title: '', body: '', image_url: '', button_url: '', product_id: '' };

export default function Blocks() {
    const [list, setList] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ type: 'text', config: defaultConfig });

    useEffect(() => {
        api.blocks().then(setList).catch((e) => setError(e.message)).finally(() => setLoading(false));
    }, []);

    const save = async () => {
        setError('');
        try {
            if (editing) {
                await api.updateBlock(editing.id, { type: form.type, config: form.config });
                setList((prev) => prev.map((b) => (b.id === editing.id ? { ...b, type: form.type, config: form.config } : b)));
            } else {
                const created = await api.createBlock({ type: form.type, config: form.config, sort_order: list.length });
                setList((prev) => [...prev, created]);
            }
            setEditing(null);
            setForm({ type: 'text', config: { ...defaultConfig } });
        } catch (e) {
            setError(e.message);
        }
    };

    const remove = async (id) => {
        if (!confirm('Delete this block?')) return;
        try {
            await api.deleteBlock(id);
            setList((prev) => prev.filter((b) => b.id !== id));
            if (editing?.id === id) setEditing(null);
        } catch (e) {
            setError(e.message);
        }
    };

    if (loading) return <p className="text-gray-500">Loading blocks…</p>;
    return (
        <div>
            <h1 className="text-2xl font-bold text-gray-900 mb-4">Thank You Blocks</h1>
            {error && <p className="text-red-600 mb-4">{error}</p>}
            <div className="bg-white rounded-lg shadow p-4 mb-6">
                <h2 className="font-semibold mb-2">{editing ? 'Edit block' : 'New block'}</h2>
                <select className="border rounded px-2 py-1 mb-2" value={form.type} onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}>
                    <option value="banner">Banner</option>
                    <option value="text">Text</option>
                    <option value="button">Button</option>
                    <option value="product_card">Product card</option>
                </select>
                <div className="grid grid-cols-1 gap-2 max-w-xl">
                    <input className="border rounded px-2 py-1" placeholder="Title" value={form.config.title || ''} onChange={(e) => setForm((f) => ({ ...f, config: { ...f.config, title: e.target.value } }))} />
                    <input className="border rounded px-2 py-1" placeholder="Body / label" value={form.config.body || ''} onChange={(e) => setForm((f) => ({ ...f, config: { ...f.config, body: e.target.value } }))} />
                    <input className="border rounded px-2 py-1" placeholder="Image URL" value={form.config.image_url || ''} onChange={(e) => setForm((f) => ({ ...f, config: { ...f.config, image_url: e.target.value } }))} />
                    <input className="border rounded px-2 py-1" placeholder="Button URL" value={form.config.button_url || ''} onChange={(e) => setForm((f) => ({ ...f, config: { ...f.config, button_url: e.target.value } }))} />
                    <input className="border rounded px-2 py-1" placeholder="Product ID (for product_card)" value={form.config.product_id || ''} onChange={(e) => setForm((f) => ({ ...f, config: { ...f.config, product_id: e.target.value } }))} />
                </div>
                <div className="mt-2 flex gap-2">
                    <button type="button" className="bg-blue-600 text-white px-3 py-1 rounded" onClick={save}>Save</button>
                    {editing && <button type="button" className="bg-gray-400 text-white px-3 py-1 rounded" onClick={() => { setEditing(null); setForm({ type: 'text', config: { ...defaultConfig } }); }}>Cancel</button>}
                </div>
            </div>
            <ul className="space-y-2">
                {list.map((b) => (
                    <li key={b.id} className="bg-white rounded shadow p-3 flex justify-between items-center">
                        <span>{b.type} – {b.config?.title || b.config?.body || '—'}</span>
                        <div>
                            <button type="button" className="text-blue-600 mr-2" onClick={() => { setEditing(b); setForm({ type: b.type, config: { ...defaultConfig, ...b.config } }); }}>Edit</button>
                            <button type="button" className="text-red-600" onClick={() => remove(b.id)}>Delete</button>
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}
