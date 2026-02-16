<?php

namespace Tests\Feature;

use App\Filament\Resources\BlockResource\Pages\EditBlock;
use App\Models\Block;
use App\Models\Rule;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class EditBlockRefineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('openrouter_api_key', 'sk-test');
        Setting::set('openrouter_model', 'openai/gpt-4o-mini');
    }

    public function test_run_refine_preview_sets_error_when_openrouter_not_configured(): void
    {
        Setting::set('openrouter_api_key', '');
        $shop = Shop::create([
            'shop_domain' => 'test.myshopify.com',
            'access_token' => 'token',
            'scope' => null,
            'installed_at' => now(),
            'uninstalled_at' => null,
        ]);
        $block = Block::create([
            'shop_id' => $shop->id,
            'surface' => 'checkout',
            'type' => 'upsell',
            'name' => 'Test',
            'ai_generated_php' => '// snippet',
            'ai_generated_description' => 'Desc',
            'config' => [],
        ]);
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditBlock::class, ['record' => $block->getKey()])
            ->set('refinePrompt', 'Simplify the rule')
            ->call('runRefinePreview')
            ->assertSet('refineError', fn ($v) => $v !== null && str_contains($v, 'not configured'))
            ->assertSet('refinePreview', null);
    }

    public function test_run_refine_preview_sets_preview_when_ai_returns_result(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'updated_rule_conditions' => ['and' => [['line_items_has_product_id' => 123]]],
                                'updated_php_snippet' => '// refined snippet',
                                'updated_text_fields' => ['title' => 'New Title'],
                                'explanation' => 'Updated.',
                                'warnings' => [],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $shop = Shop::create([
            'shop_domain' => 'test.myshopify.com',
            'access_token' => 'token',
            'scope' => null,
            'installed_at' => now(),
            'uninstalled_at' => null,
        ]);
        $block = Block::create([
            'shop_id' => $shop->id,
            'surface' => 'checkout',
            'type' => 'upsell',
            'name' => 'Test',
            'ai_generated_php' => '// old',
            'ai_generated_description' => 'Desc',
            'config' => [],
        ]);
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditBlock::class, ['record' => $block->getKey()])
            ->set('refinePrompt', 'Only show for product 123')
            ->call('runRefinePreview')
            ->assertSet('refineError', null)
            ->assertSet('refinePreview.updated_rule_conditions', ['and' => [['line_items_has_product_id' => 123]]])
            ->assertSet('refinePreview.updated_php_snippet', '// refined snippet');
    }

    public function test_apply_refine_preview_updates_block_and_rule(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'test.myshopify.com',
            'access_token' => 'token',
            'scope' => null,
            'installed_at' => now(),
            'uninstalled_at' => null,
        ]);
        $rule = Rule::create([
            'shop_id' => $shop->id,
            'name' => 'Old rule',
            'conditions' => ['and' => [['subtotal_gte' => 50]]],
        ]);
        $block = Block::create([
            'shop_id' => $shop->id,
            'surface' => 'checkout',
            'type' => 'upsell',
            'name' => 'Test',
            'rule_id' => $rule->id,
            'ai_generated_php' => '// old snippet',
            'config' => ['title' => 'Old'],
        ]);
        $user = User::factory()->create();

        $refinePreview = [
            'updated_rule_conditions' => ['and' => [['line_items_has_product_id' => 999]]],
            'updated_php_snippet' => '// new snippet',
            'updated_text_fields' => ['title' => 'Refined Title'],
            'explanation' => 'Done',
            'warnings' => [],
        ];

        Livewire::actingAs($user)
            ->test(EditBlock::class, ['record' => $block->getKey()])
            ->set('refinePreview', $refinePreview)
            ->call('applyRefinePreview');

        $block->refresh();
        $rule->refresh();
        $this->assertSame('// new snippet', $block->ai_generated_php);
        $this->assertSame('Refined Title', $block->config['title'] ?? null);
        $this->assertSame([['line_items_has_product_id' => 999]], $rule->conditions['and'] ?? []);
    }
}
