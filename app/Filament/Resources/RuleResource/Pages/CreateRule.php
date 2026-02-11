<?php

namespace App\Filament\Resources\RuleResource\Pages;

use App\Filament\Resources\RuleResource;
use App\Services\OfferBuilderService;
use Filament\Resources\Pages\CreateRecord;

class CreateRule extends CreateRecord
{
    protected static string $resource = RuleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $conditions = app(OfferBuilderService::class)->buildConditions(
            (array) ($data['rule_conditions'] ?? []),
            (string) ($data['rule_match_type'] ?? 'and')
        );

        $data['conditions'] = $conditions;
        unset($data['rule_match_type'], $data['rule_conditions']);

        return $data;
    }
}
