<?php

namespace App\Services;

use App\Models\Block;
use App\Models\CheckoutBranding;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

/**
 * Checkout branding (styling) via Shopify GraphQL Admin API.
 * Requires Shopify Plus or Development store; scopes: read_checkout_branding_settings, write_checkout_branding_settings.
 */
class CheckoutBrandingService
{
    public function __construct(
        protected ShopifyGraphQLService $graphql
    ) {}

    /**
     * Fetch checkout profiles for the shop (for profile selector in UI).
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function getCheckoutProfiles(Shop $shop): array
    {
        $query = <<<'GRAPHQL'
            query checkoutProfiles($first: Int!) {
                checkoutProfiles(first: $first) {
                    edges {
                        node {
                            id
                            name
                        }
                    }
                }
            }
        GRAPHQL;

        try {
            $data = $this->graphql->request($shop, $query, ['first' => 20]);
            $edges = $data['checkoutProfiles']['edges'] ?? [];
            $out = [];
            foreach ($edges as $edge) {
                $node = $edge['node'] ?? null;
                if ($node && ! empty($node['id'])) {
                    $out[] = [
                        'id' => (string) $node['id'],
                        'name' => (string) ($node['name'] ?? 'Unnamed'),
                    ];
                }
            }
            return $out;
        } catch (\Throwable $e) {
            Log::channel('shopify_api')->warning('Checkout branding: getCheckoutProfiles failed', [
                'shop_id' => $shop->id,
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Apply branding to the checkout profile. No-op if disabled or no profile selected.
     */
    public function applyBranding(CheckoutBranding $branding): array
    {
        $shop = $branding->shop;
        if (! $shop) {
            return ['success' => false, 'message' => 'Shop not found.'];
        }

        if (! $branding->is_enabled) {
            return ['success' => false, 'message' => 'Branding is disabled. Enable it and try again.'];
        }

        $profileId = trim((string) ($branding->checkout_profile_id ?? ''));
        if ($profileId === '') {
            return ['success' => false, 'message' => 'Select a checkout profile first.'];
        }

        if ($branding->apply_only_with_checkout_widget) {
            $hasCheckoutBlock = Block::where('shop_id', $shop->id)->where('surface', 'checkout')->exists();
            if (! $hasCheckoutBlock) {
                return [
                    'success' => false,
                    'message' => 'No checkout widget found. Add a Checkout widget first, or disable "Apply only when store has a checkout widget".',
                ];
            }
        }

        $designSystem = $branding->design_system;
        $customizations = $branding->customizations;
        $input = [];
        if (is_array($designSystem) && $designSystem !== []) {
            $input['designSystem'] = $designSystem;
        }
        if (is_array($customizations) && $customizations !== []) {
            $input['customizations'] = $customizations;
        }
        if ($input === []) {
            return ['success' => false, 'message' => 'Add at least design system or customizations to apply.'];
        }

        $mutation = <<<'GRAPHQL'
            mutation checkoutBrandingUpsert($checkoutProfileId: ID!, $checkoutBrandingInput: CheckoutBrandingInput!) {
                checkoutBrandingUpsert(checkoutProfileId: $checkoutProfileId, checkoutBrandingInput: $checkoutBrandingInput) {
                    checkoutBranding { id }
                    userErrors {
                        field
                        message
                    }
                }
            }
        GRAPHQL;

        try {
            $data = $this->graphql->request($shop, $mutation, [
                'checkoutProfileId' => $profileId,
                'checkoutBrandingInput' => $input,
            ]);
            $result = $data['checkoutBrandingUpsert'] ?? [];
            $errors = $result['userErrors'] ?? [];
            if ($errors !== []) {
                $messages = array_column($errors, 'message');
                return ['success' => false, 'message' => implode(' ', $messages)];
            }
            return ['success' => true, 'message' => 'Checkout styling applied successfully.'];
        } catch (\Throwable $e) {
            Log::channel('shopify_api')->warning('Checkout branding: applyBranding failed', [
                'shop_id' => $shop->id,
                'message' => $e->getMessage(),
            ]);
            $message = $e->getMessage();
            if (str_contains($message, '403') || str_contains($message, 'access')) {
                $message = 'Access denied. Ensure the app has checkout branding scopes and the store is on Shopify Plus (or Development).';
            }
            return ['success' => false, 'message' => $message];
        }
    }

    /**
     * Reset checkout branding to defaults for the profile.
     */
    public function resetBranding(CheckoutBranding $branding): array
    {
        $shop = $branding->shop;
        if (! $shop) {
            return ['success' => false, 'message' => 'Shop not found.'];
        }

        $profileId = trim((string) ($branding->checkout_profile_id ?? ''));
        if ($profileId === '') {
            return ['success' => false, 'message' => 'Select a checkout profile first.'];
        }

        $mutation = <<<'GRAPHQL'
            mutation checkoutBrandingUpsert($checkoutProfileId: ID!, $checkoutBrandingInput: CheckoutBrandingInput) {
                checkoutBrandingUpsert(checkoutProfileId: $checkoutProfileId, checkoutBrandingInput: $checkoutBrandingInput) {
                    checkoutBranding { id }
                    userErrors {
                        field
                        message
                    }
                }
            }
        GRAPHQL;

        try {
            $data = $this->graphql->request($shop, $mutation, [
                'checkoutProfileId' => $profileId,
                'checkoutBrandingInput' => null,
            ]);
            $result = $data['checkoutBrandingUpsert'] ?? [];
            $errors = $result['userErrors'] ?? [];
            if ($errors !== []) {
                $messages = array_column($errors, 'message');
                return ['success' => false, 'message' => implode(' ', $messages)];
            }
            return ['success' => true, 'message' => 'Checkout styling reset to default.'];
        } catch (\Throwable $e) {
            Log::channel('shopify_api')->warning('Checkout branding: resetBranding failed', [
                'shop_id' => $shop->id,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
