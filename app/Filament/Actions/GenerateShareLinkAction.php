<?php

namespace App\Filament\Actions;

use App\Models\Integration;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GenerateShareLinkAction
{
    public static function make(string $translationNamespace, string $routeName): Action
    {
        return Action::make('generate_share_link')
            ->label(__("$translationNamespace.settings.action_share_link"))
            ->icon('heroicon-o-share')
            ->mountUsing(function (Integration $record, Schema $form): void {
                if (! $record->isShareTokenValid()) {
                    $record->generateShareToken();
                }

                $form->fill();
            })
            ->schema(fn (Integration $record) => [
                TextInput::make('share_url')
                    ->label(__("$translationNamespace.settings.share_link_field_label"))
                    ->default(fn () => route($routeName, $record->refresh()->share_token))
                    ->readOnly()
                    ->extraInputAttributes(['onclick' => 'this.select()']),
            ])
            ->modalSubmitAction(false);
    }
}
