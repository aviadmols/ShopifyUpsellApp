import React, { useEffect, useState } from 'react';
import * as api from '../api';

export default function Placements() {
    const [list, setList] = useState([]);
    const [offers, setOffers] = useState([]);
    const [blocks, setBlocks] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ placement_type: 'checkout', config: { offer_ids: [], block_ids: [], max_offers: 3, cooldown_hours: 24 } });

    useEffect(() => {
        Promise.all([api.placements(), api.offers(), api.blocks()])
            .then(([p, o, b]) => {
                setList(p);
                setOffers(o);
                setBlocks(b);
            })
            .catch((e) => setError(e.message))
            .finally(() => setLoading(false));
    }, []);

    const save = async () => {
        setError('');
        try {
            const config = { ...form.config };
            if (form.placement_type === 'thank_you') {
                config.block_ids = Array.isArray(config.block_ids) ? config.block_ids : [];
            } else {
                config.offer_ids = Array.isArray(config.offer_ids) ? config.offer_ids : [];
                config.max_offers = parseInt(config.max_offers, 10) || 1;
                if (form.placement_type === 'post_purchase') config.cooldown_hours = parseInt(config.cooldown_hours, 10) || 24;
            }
            if (editing) {
                await api.updatePlacement(editing.id, { placement_type: form.placement_type, config });
                setList((prev) => prev.map((p) => (p.id === editing.id ? { ...p, placement_type: form.placement_type, config } : p)));
            } else {
                const created = await api.createPlacement({ placement_type: form.placement_type, config });
                setList((prev) => [...prev, created]);
            }
            setEditing(null);
            setForm({ placement_type: 'checkout', config: { offer_ids: [], block_ids: [], max_offers: 3, cooldown_hours: 24 } });
        } catch (e) {
            setError(e.message);
        }
    };

    const remove = async (id) => {
        if (!confirm('Delete this placement?')) return;
        try {
            await api.deletePlacement(id);
            setList((prev) => prev.filter((p) => p.id !== id));
            if (editing?.id === id) setEditing(null);
        } catch (e) {
            setError(e.message);
        }
    };

    const toggleOffer = (offerId) => {
        const ids = form.config.offer_ids || [];
        const next = ids.includes(offerId) ? ids.filter((i) => i !== offerId) : [...ids, offerId];
        setForm((f) => ({ ...f, config: { ...f.config, offer_ids: next } }));
    };

    const toggleBlock = (blockId) => {
        const ids = form.config.block_ids || [];
        const next = ids.includes(blockId) ? ids.filter((i) => i !== blockId) : [...ids, blockId];
        setForm((f) => ({ ...f, config: { ...f.config, block_ids: next } }));
    };

    if (loading) return <p className="text-gray-500">Loading placements…</p>;
    return (
        <div>
            <h1 className="text-2xl font-bold text-gray-900 mb-4">Placements</h1>
            {error && <p className="text-red-600 mb-4">{error}</p>}
            <div className="bg-white rounded-lg shadow p-4 mb-6">
                <h2 className="font-semibold mb-2">{editing ? 'Edit placement' : 'New placement'}</h2>
                <select className="border rounded px-2 py-1 mb-2" value={form.placement_type} onChange={(e) => setForm((f) => ({ ...f, placement_type: e.target.value }))}>
                    <option value="checkout">Checkout</option>
                    <option value="post_purchase">Post-purchase</option>
                    <option value="thank_you">Thank you</option>
                </select>
                {(form.placement_type === 'checkout' || form.placement_type === 'post_purchase') && (
                    <>
                        <label className="block mt-2">Max offers</label>
                        <input type="number" className="border rounded px-2 py-1 w-20" value={form.config.max_offers} onChange={(e) => setForm((f) => ({ ...f, config: { ...f.config, max_offers: e.target.value } }))} />
                        {form.placement_type === 'post_purchase' && (
                            <>
                                <label className="block mt-2">Cooldown (hours)</label>
                                <input type="number" className="border rounded px-2 py-1 w-20" value={form.config.cooldown_hours} onChange={(e) => setForm((f) => ({ ...f, config: { ...f.config, cooldown_hours: e.target.value } }))} />
                            </>
                        )}
                        <p className="mt-2 text-sm">Offers (order = priority):</p>
                        <div className="flex flex-wrap gap-2 mt-1">
                            {offers.map((o) => (
                                <label key={o.id} className="flex items-center gap-1">
                                    <input type="checkbox" checked={(form.config.offer_ids || []).includes(o.id)} onChange={() => toggleOffer(o.id)} />
                                    <span>{o.title}</span>
                                </label>
                            ))}
                        </div>
                    </>
                )}
                {form.placement_type === 'thank_you' && (
                    <>
                        <p className="mt-2 text-sm">Blocks (order = display order):</p>
                        <div className="flex flex-wrap gap-2 mt-1">
                            {blocks.map((b) => (
                                <label key={b.id} className="flex items-center gap-1">
                                    <input type="checkbox" checked={(form.config.block_ids || []).includes(b.id)} onChange={() => toggleBlock(b.id)} />
                                    <span>{b.type} – {b.config?.title || '—'}</span>
                                </label>
                            ))}
                        </div>
                    </>
                )}
                <div className="mt-2 flex gap-2">
                    <button type="button" className="bg-blue-600 text-white px-3 py-1 rounded" onClick={save}>Save</button>
                    {editing && <button type="button" className="bg-gray-400 text-white px-3 py-1 rounded" onClick={() => { setEditing(null); setForm({ placement_type: 'checkout', config: { offer_ids: [], block_ids: [], max_offers: 3, cooldown_hours: 24 } }); }}>Cancel</button>}
                </div>
            </div>
            <ul className="space-y-2">
                {list.map((p) => (
                    <li key={p.id} className="bg-white rounded shadow p-3 flex justify-between items-center">
                        <span>{p.placement_type}</span>
                        <div>
                            <button type="button" className="text-blue-600 mr-2" onClick={() => { setEditing(p); setForm({ placement_type: p.placement_type, config: { ...p.config } }); }}>Edit</button>
                            <button type="button" className="text-red-600" onClick={() => remove(p.id)}>Delete</button>
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}
