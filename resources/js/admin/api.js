/**
 * Base URL for Laravel API. Uses same origin when embedded.
 */
const base = (typeof window !== 'undefined' && window.location.origin) || '';

function getShop() {
    if (typeof window === 'undefined') return '';
    return new URLSearchParams(window.location.search).get('shop') || '';
}

export async function api(path, options = {}) {
    const shop = getShop();
    const url = `${base}/api${path}${path.includes('?') ? '&' : '?'}shop=${encodeURIComponent(shop)}`;
    const res = await fetch(url, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers,
        },
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.error || err.message || res.statusText);
    }
    return res.json();
}

export function offers() {
    return api('/offers').then((r) => r.data);
}
export function createOffer(body) {
    return api('/offers', { method: 'POST', body: JSON.stringify(body) }).then((r) => r.data);
}
export function updateOffer(id, body) {
    return api(`/offers/${id}`, { method: 'PUT', body: JSON.stringify(body) }).then((r) => r.data);
}
export function deleteOffer(id) {
    return api(`/offers/${id}`, { method: 'DELETE' });
}

export function rules() {
    return api('/rules').then((r) => r.data);
}
export function createRule(body) {
    return api('/rules', { method: 'POST', body: JSON.stringify(body) }).then((r) => r.data);
}
export function updateRule(id, body) {
    return api(`/rules/${id}`, { method: 'PUT', body: JSON.stringify(body) }).then((r) => r.data);
}
export function deleteRule(id) {
    return api(`/rules/${id}`, { method: 'DELETE' });
}

export function blocks() {
    return api('/blocks').then((r) => r.data);
}
export function createBlock(body) {
    return api('/blocks', { method: 'POST', body: JSON.stringify(body) }).then((r) => r.data);
}
export function updateBlock(id, body) {
    return api(`/blocks/${id}`, { method: 'PUT', body: JSON.stringify(body) }).then((r) => r.data);
}
export function deleteBlock(id) {
    return api(`/blocks/${id}`, { method: 'DELETE' });
}

export function placements() {
    return api('/placements').then((r) => r.data);
}
export function createPlacement(body) {
    return api('/placements', { method: 'POST', body: JSON.stringify(body) }).then((r) => r.data);
}
export function updatePlacement(id, body) {
    return api(`/placements/${id}`, { method: 'PUT', body: JSON.stringify(body) }).then((r) => r.data);
}
export function deletePlacement(id) {
    return api(`/placements/${id}`, { method: 'DELETE' });
}

export function previewOffer(payload, placementType = 'post_purchase') {
    const shop = getShop();
    return api('/preview/offer', {
        method: 'POST',
        body: JSON.stringify({ shop, payload, placement_type: placementType }),
    });
}
