<?php

return [
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'scopes' => env('SHOPIFY_SCOPES', 'read_products,write_products,read_orders,write_orders'),
    'api_version' => env('SHOPIFY_API_VERSION', '2024-01'),
    'checkout_extension_secret' => env('CHECKOUT_EXTENSION_SECRET'),
];
