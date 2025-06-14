<?php

namespace App\Filament\Resources\ModuleResource\Pages;

use App\Filament\Resources\ModuleResource;
use App\Models\Module;
use App\Models\BatteryPack; // Assuming you have a BatteryPack model
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Maatwebsite\Excel\Facades\Excel;

class ManageModules extends ManageRecords
{
    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
{
    $actions = [
        Actions\CreateAction::make(),
        Action::make('Import Modules')
            ->modal('importModules', [
                'title' => 'Import Modules',
                'width' => '2xl',
            ])->form(function () {
                return [
                    \Filament\Forms\Components\FileUpload::make('file')
                        ->label('CSV File')
                        ->required(),
                    \Filament\Forms\Components\Select::make('module_type')
                        ->label('Module Type')
                        ->options([
                            'NU' => 'NU',
                            'CINU' => 'CINU',
                        ])
                        ->required(),
                ];
            })->action(function (array $data) {
                $filePath = public_path('storage/' . $data['file']);
                Excel::import(new \App\Imports\ModulesImport($data['module_type']), $filePath);
                Notification::make()
                    ->title('Modules Imported')
                    ->success()
                    ->send();
            }),
    ];

    // Get the active battery pack filter
    $activeBatteryPackId = $this->getActiveBatteryPackId();

    // Load modules based on the active filter
    $modules = Module::where('battery_pack_id', $activeBatteryPackId)->get();

    // Check if all modules have ir_value and capacitance as 0 or null
    $canUpdateModules = $modules->every(function ($module) {
        return is_null($module->ir_value) || $module->ir_value === 0;
    }) && $modules->every(function ($module) {
        return is_null($module->capacitance) || $module->capacitance === 0;
    });

    // Add the Update Modules action only if the condition is met
    if ($canUpdateModules) {
        $actions[] = Action::make('Update Modules')
            ->modal()
            ->modalWidth('4xl')
            ->modalHeading('Update Modules')
            ->closeModalByClickingAway(false)
            ->form(function () use ($modules, $activeBatteryPackId) {
                return [
                    Forms\Components\Hidden::make('battery_pack_id')
                        ->default($activeBatteryPackId),
                    Forms\Components\Repeater::make('modules')
                        ->label('Modules')
                        ->schema([
                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\TextInput::make('serial_number')
                                        ->label('Serial Number')
                                        ->disabled()
                                        ->columnSpan(1),
                                    Forms\Components\TextInput::make('ir_value')
                                        ->label('IR Value')
                                        ->numeric()
                                        ->required()
                                        ->columnSpan(1),
                                    Forms\Components\TextInput::make('capacitance')
                                        ->label('Capacitance')
                                        ->numeric()
                                        ->required()
                                        ->columnSpan(1),
                                ]),
                        ])
                        ->columns(4)
                        ->default($modules->map(function ($module) {
                            return [
                                'id' => $module->id,
                                'serial_number' => $module->serial_number,
                                'ir_value' => $module->ir_value,
                                'capacitance' => $module->capacitance,
                            ];
                        })->toArray()),
                ];
            })
            ->action(function (array $data) {
                if (isset($data['modules']) && is_array($data['modules'])) {
                    foreach ($data['modules'] as $moduleData) {
                        if (isset($moduleData['id'])) {
                            $module = Module::find($moduleData['id']);
                            if ($module) {
                                $module->update([
                                    'ir_value' => $moduleData['ir_value'],
                                    'capacitance' => $moduleData['capacitance'],
                                ]);
                            }
                        }
                    }
                }

                Notification::make()
                    ->title('Modules Updated Successfully')
                    ->success()
                    ->send();
            });
    }

    return $actions;
}


    protected function getActiveBatteryPackId()
    {
        // Get the active filter from the table state
        $tableFilters = $this->getTableFilters();

        if (isset($tableFilters['battery_pack_id']['value']) && !empty($tableFilters['battery_pack_id']['value'])) {
            return $tableFilters['battery_pack_id']['value'];
        }

        // Fallback to the default from the resource
        return ModuleResource::getActiveBatteryPackFilter();
    }
}