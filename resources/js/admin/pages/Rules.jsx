import React, { useEffect, useState } from 'react';
import * as api from '../api';

const defaultConditions = { and: [{ order_subtotal_gte: 100 }] };

export default function Rules() {
    const [list, setList] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ name: '', conditions: JSON.stringify(defaultConditions, null, 2) });

    useEffect(() => {
        api.rules().then(setList).catch((e) => setError(e.message)).finally(() => setLoading(false));
    }, []);

    const parseConditions = (str) => {
        try {
            return JSON.parse(str);
        } catch {
            return null;
        }
    };

    const save = async () => {
        setError('');
        const conditions = parseConditions(form.conditions);
        if (!conditions) {
            setError('Invalid JSON for conditions');
            return;
        }
        try {
            if (editing) {
                await api.updateRule(editing.id, { name: form.name, conditions });
                setList((prev) => prev.map((r) => (r.id === editing.id ? { ...r, name: form.name, conditions } : r)));
            } else {
                const created = await api.createRule({ name: form.name, conditions });
                setList((prev) => [...prev, created]);
            }
            setEditing(null);
            setForm({ name: '', conditions: JSON.stringify(defaultConditions, null, 2) });
        } catch (e) {
            setError(e.message);
        }
    };

    const remove = async (id) => {
        if (!confirm('Delete this rule?')) return;
        try {
            await api.deleteRule(id);
            setList((prev) => prev.filter((r) => r.id !== id));
            if (editing?.id === id) setEditing(null);
        } catch (e) {
            setError(e.message);
        }
    };

    if (loading) return <p className="text-gray-500">Loading rules…</p>;
    return (
        <div>
            <h1 className="text-2xl font-bold text-gray-900 mb-4">Rules</h1>
            <p className="text-sm text-gray-600 mb-2">Conditions JSON: use keys like order_subtotal_gte, line_items_has_any_product_id, customer_has_tag, shipping_country_in. Wrap in and/or arrays.</p>
            {error && <p className="text-red-600 mb-4">{error}</p>}
            <div className="bg-white rounded-lg shadow p-4 mb-6">
                <h2 className="font-semibold mb-2">{editing ? 'Edit rule' : 'New rule'}</h2>
                <input className="border rounded px-2 py-1 w-full max-w-md mb-2" placeholder="Name" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
                <textarea className="border rounded px-2 py-1 w-full font-mono text-sm" rows={8} placeholder="Conditions JSON" value={form.conditions} onChange={(e) => setForm((f) => ({ ...f, conditions: e.target.value }))} />
                <div className="mt-2 flex gap-2">
                    <button type="button" className="bg-blue-600 text-white px-3 py-1 rounded" onClick={save}>Save</button>
                    {editing && <button type="button" className="bg-gray-400 text-white px-3 py-1 rounded" onClick={() => { setEditing(null); setForm({ name: '', conditions: JSON.stringify(defaultConditions, null, 2) }); }}>Cancel</button>}
                </div>
            </div>
            <ul className="space-y-2">
                {list.map((r) => (
                    <li key={r.id} className="bg-white rounded shadow p-3 flex justify-between items-center">
                        <span>{r.name}</span>
                        <div>
                            <button type="button" className="text-blue-600 mr-2" onClick={() => { setEditing(r); setForm({ name: r.name, conditions: JSON.stringify(r.conditions, null, 2) }); }}>Edit</button>
                            <button type="button" className="text-red-600" onClick={() => remove(r.id)}>Delete</button>
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}
