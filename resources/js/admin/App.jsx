import React from 'react';
import { Routes, Route, Link } from 'react-router-dom';
import Offers from './pages/Offers';
import Rules from './pages/Rules';
import Blocks from './pages/Blocks';
import Placements from './pages/Placements';
import Preview from './pages/Preview';

function App() {
    const shop = new URLSearchParams(window.location.search).get('shop') || '';

    return (
        <div className="min-h-screen bg-gray-50">
            <nav className="bg-white shadow border-b">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex gap-6 h-16 items-center">
                        <Link to="/" className="font-semibold text-gray-800">Upsell App</Link>
                        <Link to={`/offers?shop=${shop}`} className="text-gray-600 hover:text-gray-900">Offers</Link>
                        <Link to={`/rules?shop=${shop}`} className="text-gray-600 hover:text-gray-900">Rules</Link>
                        <Link to={`/blocks?shop=${shop}`} className="text-gray-600 hover:text-gray-900">Blocks</Link>
                        <Link to={`/placements?shop=${shop}`} className="text-gray-600 hover:text-gray-900">Placements</Link>
                        <Link to={`/preview?shop=${shop}`} className="text-gray-600 hover:text-gray-900">Preview</Link>
                    </div>
                </div>
            </nav>
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <Routes>
                    <Route path="/" element={<Home shop={shop} />} />
                    <Route path="/offers" element={<Offers />} />
                    <Route path="/rules" element={<Rules />} />
                    <Route path="/blocks" element={<Blocks />} />
                    <Route path="/placements" element={<Placements />} />
                    <Route path="/preview" element={<Preview />} />
                </Routes>
            </main>
        </div>
    );
}

function Home({ shop }) {
    return (
        <div>
            <h1 className="text-2xl font-bold text-gray-900 mb-2">Upsell Admin</h1>
            <p className="text-gray-600">
                Use the navigation to manage Offers, Rules, Thank You Blocks, and Placements.
                {shop && <span className="block mt-2 text-sm">Shop: <code>{shop}</code></span>}
            </p>
        </div>
    );
}

export default App;
