<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartLineAction extends Model
{
    public const ACTION_REPLACE_WITH_VARIANT = 'replace_with_variant';
    public const ACTION_ADD_VARIANT = 'add_variant';
    public const ACTION_REMOVE_LINE = 'remove_line';
    public const ACTION_UPDATE_QUANTITY = 'update_quantity';
    public const ACTION_SWITCH_TO_SUBSCRIPTION = 'switch_to_subscription';
    public const ACTION_SWITCH_TO_ONE_TIME = 'switch_to_one_time';

    public const RULE_MODE_ALL = 'all';
    public const RULE_MODE_INCLUDE = 'include';
    public const RULE_MODE_EXCLUDE = 'exclude';

    protected $fillable = [
        'checkout_experience_id',
        'name',
        'label',
        'message',
        'action_type',
        'target_variant_gid',
        'target_quantity',
        'target_selling_plan_id',
        'sort_order',
        'rule_mode',
        'include_product_ids',
        'exclude_product_ids',
        'include_collection_ids',
        'exclude_collection_ids',
        'include_tags',
        'exclude_tags',
        'include_vendors',
        'exclude_vendors',
        'include_product_types',
        'exclude_product_types',
        'require_subscription_state',
        'min_subtotal',
        'max_subtotal',
        'min_cart_items',
        'max_cart_items',
    ];

    protected function casts(): array
    {
        return [
            'target_quantity' => 'integer',
            'sort_order' => 'integer',
            'include_product_ids' => 'array',
            'exclude_product_ids' => 'array',
            'include_collection_ids' => 'array',
            'exclude_collection_ids' => 'array',
            'include_tags' => 'array',
            'exclude_tags' => 'array',
            'include_vendors' => 'array',
            'exclude_vendors' => 'array',
            'include_product_types' => 'array',
            'exclude_product_types' => 'array',
            'min_subtotal' => 'decimal:2',
            'max_subtotal' => 'decimal:2',
            'min_cart_items' => 'integer',
            'max_cart_items' => 'integer',
        ];
    }

    public function checkoutExperience(): BelongsTo
    {
        return $this->belongsTo(CheckoutExperience::class);
    }

    public static function actionTypes(): array
    {
        return [
            self::ACTION_REPLACE_WITH_VARIANT => 'Replace with variant (bundle/other product)',
            self::ACTION_ADD_VARIANT => 'Add variant to cart',
            self::ACTION_REMOVE_LINE => 'Remove line',
            self::ACTION_UPDATE_QUANTITY => 'Update quantity',
            self::ACTION_SWITCH_TO_SUBSCRIPTION => 'Switch to subscription',
            self::ACTION_SWITCH_TO_ONE_TIME => 'Switch to one-time purchase',
        ];
    }
}
