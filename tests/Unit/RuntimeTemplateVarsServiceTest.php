<?php

namespace Tests\Unit;

use App\Services\RuntimeTemplateVarsService;
use PHPUnit\Framework\TestCase;

class RuntimeTemplateVarsServiceTest extends TestCase
{
    public function test_plural_message_from_property_singular_and_plural(): void
    {
        $svc = new RuntimeTemplateVarsService();

        $defs = [
            'dog_names_message' => [
                'type' => 'plural_message_from_property',
                'property' => 'Dog Name',
                'singular' => 'Your dog ({value}) deserves the best',
                'plural' => 'Your dogs ({values}) deserve the best',
                'empty' => '',
                'separator' => ', ',
            ],
        ];

        $context1 = [
            'line_items' => [
                ['properties' => ['Dog Name' => 'Rex']],
            ],
        ];
        $out1 = $svc->compute($defs, $context1);
        $this->assertSame('Your dog (Rex) deserves the best', $out1['dog_names_message'] ?? null);

        $context2 = [
            'line_items' => [
                ['properties' => ['Dog Name' => 'Rex']],
                ['properties' => ['Dog Name' => 'Bella']],
            ],
        ];
        $out2 = $svc->compute($defs, $context2);
        $this->assertSame('Your dogs (Rex, Bella) deserve the best', $out2['dog_names_message'] ?? null);
    }

    public function test_unique_line_item_property_values_dedupes_case_insensitive(): void
    {
        $svc = new RuntimeTemplateVarsService();

        $defs = [
            'names' => [
                'type' => 'unique_line_item_property_values',
                'property' => 'Dog Name',
                'separator' => ', ',
                'case_insensitive_unique' => true,
            ],
        ];

        $context = [
            'line_items' => [
                ['properties' => ['Dog Name' => 'Rex']],
                ['properties' => ['Dog Name' => 'rex']],
                ['properties' => ['Dog Name' => 'Bella']],
            ],
        ];
        $out = $svc->compute($defs, $context);
        $this->assertSame('Rex, Bella', $out['names'] ?? null);
    }
}

