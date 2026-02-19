<?php

namespace App\Filament\Widgets;

/**
 * Central registry for widget (block) types: surfaces, singleton, and which types
 * support inline offers/rules. Used by BlockResource and API.
 */
final class WidgetRegistry
{
    /** @var array<string, array{label: string, surfaces: array<string>, singleton: bool, has_offers: bool}> */
    private static array $types = [
        'upsell' => [
            'label' => 'Upsell (offers)',
            'surfaces' => ['checkout'],
            'singleton' => false,
            'has_offers' => true,
        ],
        'checkout_upgrade_card' => [
            'label' => 'Upgrade card (subscription / bundle)',
            'surfaces' => ['checkout'],
            'singleton' => false,
            'has_offers' => false,
        ],
        'checkout_subscription_save' => [
            'label' => 'Subscribe & Save (cart-wide, OTP only)',
            'surfaces' => ['checkout'],
            'singleton' => false,
            'has_offers' => false,
        ],
        'progress_bar' => [
            'label' => 'Progress bar',
            'surfaces' => ['checkout'],
            'singleton' => false,
            'has_offers' => false,
        ],
        'content_icon_features' => [
            'label' => 'Icon features (icon + title + description)',
            'surfaces' => ['checkout', 'thank_you'],
            'singleton' => false,
            'has_offers' => false,
        ],
        'content_banner' => [
            'label' => 'Banner (image + text + button)',
            'surfaces' => ['checkout', 'thank_you'],
            'singleton' => false,
            'has_offers' => false,
        ],
        'content_rich_text' => [
            'label' => 'Rich text',
            'surfaces' => ['checkout', 'thank_you'],
            'singleton' => false,
            'has_offers' => false,
        ],
        'content_button' => [
            'label' => 'Button / CTA',
            'surfaces' => ['checkout', 'thank_you'],
            'singleton' => false,
            'has_offers' => false,
        ],
        'content_product_card' => [
            'label' => 'Product card',
            'surfaces' => ['checkout', 'thank_you'],
            'singleton' => false,
            'has_offers' => false,
        ],
        'post_purchase_funnel' => [
            'label' => 'Post-purchase funnel',
            'surfaces' => ['post_purchase'],
            'singleton' => true,
            'has_offers' => true,
        ],
    ];

    public static function typeOptionsForSurface(?string $surface): array
    {
        if (! $surface) {
            return [];
        }
        $out = [];
        foreach (self::$types as $type => $meta) {
            if (in_array($surface, $meta['surfaces'], true)) {
                $out[$type] = $meta['label'];
            }
        }
        return $out;
    }

    public static function surfaces(): array
    {
        return ['checkout', 'thank_you', 'post_purchase'];
    }

    /** @return array<string> */
    public static function singletonTypes(): array
    {
        $out = [];
        foreach (self::$types as $type => $meta) {
            if ($meta['singleton']) {
                $out[] = $type;
            }
        }
        return $out;
    }

    public static function isSingleton(string $type): bool
    {
        return (self::$types[$type] ?? ['singleton' => false])['singleton'] ?? false;
    }

    public static function hasOffers(string $type): bool
    {
        return (self::$types[$type] ?? ['has_offers' => false])['has_offers'] ?? false;
    }

    /** @return array<string, string> type => label for all types */
    public static function allTypeLabels(): array
    {
        $out = [];
        foreach (self::$types as $type => $meta) {
            $out[$type] = $meta['label'];
        }
        return $out;
    }
}
