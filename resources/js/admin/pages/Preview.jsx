import React, { useState } from 'react';
import * as api from '../api';

const samplePayload = {
    order_id: '123',
    subtotal: 150,
    line_items: [{ product_id: 456, quantity: 1 }],
    customer: { tags: ['vip'] },
    shipping_country: 'US',
};

export default function Preview() {
    const [payloadStr, setPayloadStr] = useState(JSON.stringify(samplePayload, null, 2));
    const [placementType, setPlacementType] = useState('post_purchase');
    const [result, setResult] = useState(null);
    const [error, setError] = useState('');

    const run = async () => {
        setError('');
        setResult(null);
        let payload;
        try {
            payload = JSON.parse(payloadStr);
        } catch {
            setError('Invalid JSON');
            return;
        }
        try {
            const data = await api.previewOffer(payload, placementType);
            setResult(data);
        } catch (e) {
            setError(e.message);
        }
    };

    return (
        <div>
            <h1 className="text-2xl font-bold text-gray-900 mb-4">Preview: Which offer would render?</h1>
            <p className="text-sm text-gray-600 mb-2">Paste a sample order/cart JSON and run to see which offer would be selected.</p>
            {error && <p className="text-red-600 mb-4">{error}</p>}
            <select className="border rounded px-2 py-1 mb-2" value={placementType} onChange={(e) => setPlacementType(e.target.value)}>
                <option value="post_purchase">Post-purchase</option>
                <option value="checkout">Checkout</option>
            </select>
            <textarea className="border rounded px-2 py-1 w-full font-mono text-sm mb-2" rows={14} value={payloadStr} onChange={(e) => setPayloadStr(e.target.value)} />
            <button type="button" className="bg-blue-600 text-white px-4 py-2 rounded" onClick={run}>Run</button>
            {result && (
                <div className="mt-6 bg-white rounded shadow p-4">
                    <h2 className="font-semibold mb-2">Result</h2>
                    {result.match ? (
                        <p className="text-green-700">Match: Offer #{result.match.offerId} – {result.match.title} (variant: {result.match.variantId})</p>
                    ) : (
                        <p className="text-gray-600">No offer would render for this context.</p>
                    )}
                    <pre className="mt-2 text-xs bg-gray-100 p-2 overflow-auto">{JSON.stringify(result, null, 2)}</pre>
                </div>
            )}
        </div>
    );
}
