<?php

namespace App\Filament\Resources\RuleResource\Pages;

use App\Filament\Resources\RuleResource;
use App\Services\OfferBuilderService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRule extends EditRecord
{
    protected static string $resource = RuleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $state = app(OfferBuilderService::class)->ruleFormStateFromConditions((array) ($data['conditions'] ?? []));
        $data['rule_match_type'] = $state['match_type'];
        $data['rule_conditions'] = $state['rows'];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['conditions'] = app(OfferBuilderService::class)->buildConditions(
            (array) ($data['rule_conditions'] ?? []),
            (string) ($data['rule_match_type'] ?? 'and')
        );

        unset($data['rule_match_type'], $data['rule_conditions']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
