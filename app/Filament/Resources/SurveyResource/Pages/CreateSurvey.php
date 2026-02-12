<?php

namespace App\Filament\Resources\SurveyResource\Pages;

use App\Filament\Forms\Components\RuleBuilder;
use App\Filament\Resources\SurveyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSurvey extends CreateRecord
{
    protected static string $resource = SurveyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['conditions'] = RuleBuilder::buildConditionsFromState($data);

        return $data;
    }
}

