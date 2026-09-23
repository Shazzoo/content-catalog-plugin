<?php

namespace Shazzoo\ContentCatalogApi\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Shazzoo\ContentCatalogApi\Models\ContentCatalogApiRequestLog;
use Shazzoo\ContentCatalogApi\Models\ContentCatalogApiSettings;

final class ContentCatalogApiSettingsPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Plugins';

    protected static ?string $navigationLabel = 'Content Catalog API';

    protected static ?string $title = 'Content Catalog API';

    protected static ?int $navigationSort = 30;

    protected string $view = 'content-catalog-api::filament.pages.settings';

    public ?array $data = [];

    public ?string $newApiKey = null;

    public function mount(): void
    {
        $this->fillForm(ContentCatalogApiSettings::current());
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->getAttribute('is_admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    public function form(Schema $schema): Schema
    {
        $settings = ContentCatalogApiSettings::current();

        return $schema
            ->schema([
                Section::make('Access')
                    ->description('The API is disabled by default. Enable it only while an integration needs access.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Enable Content Catalog API')
                            ->helperText('A valid API key is always required.'),
                        Toggle::make('turn_off_after')
                            ->label('Turn off after')
                            ->live()
                            ->helperText('Starts this countdown whenever you enable the API.'),
                        TextInput::make('turn_off_after_hours')
                            ->label('Hours')
                            ->integer()
                            ->minValue(1)
                            ->numeric()
                            ->required(fn (Get $get): bool => (bool) $get('turn_off_after'))
                            ->suffix('hours')
                            ->visible(fn (Get $get): bool => (bool) $get('turn_off_after')),
                        Placeholder::make('current_key')
                            ->label('Current API key')
                            ->content($settings->api_key_last_four
                                ? 'Ends in '.$settings->api_key_last_four
                                : 'No API key has been created yet.'),
                        Placeholder::make('last_rotated_at')
                            ->label('Last rotated')
                            ->content($settings->last_rotated_at?->toDayDateTimeString() ?? 'Never'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = ContentCatalogApiSettings::current();
        $wasEnabled = $settings->enabled;

        $settings->disable_after_enabled = (bool) $data['turn_off_after'];
        $settings->disable_after_hours = $settings->disable_after_enabled
            ? (int) $data['turn_off_after_hours']
            : null;

        if (! $data['enabled']) {
            $settings->disable();
        } elseif (! $wasEnabled) {
            $settings->enable();
        }

        $settings->save();

        $this->fillForm($settings);

        Notification::make()
            ->title('Content Catalog API settings saved')
            ->success()
            ->send();
    }

    public function rotateApiKey(): void
    {
        $this->newApiKey = ContentCatalogApiSettings::current()->rotateApiKey();

        Notification::make()
            ->title('API key rotated')
            ->body('Copy the new key now. It will not be shown again.')
            ->warning()
            ->persistent()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ContentCatalogApiRequestLog::query())
            ->heading('API usage logs')
            ->description('Successful authenticated requests. Raw API keys are never stored.')
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('requested_at')->label('Used at')->dateTime()->sortable(),
                TextColumn::make('purpose')->placeholder('—')->searchable(),
                TextColumn::make('operation')->label('What')->badge(),
                TextColumn::make('api_key_last_four')->label('API key')->prefix('…')->placeholder('—'),
                TextColumn::make('ip_address')->label('IP address')->searchable(),
                TextColumn::make('user_agent')
                    ->limit(60)
                    ->tooltip(fn (ContentCatalogApiRequestLog $record): ?string => $record->user_agent),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rotateApiKey')
                ->label('Rotate API key')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription('The existing key stops working immediately.')
                ->action('rotateApiKey'),
            Action::make('save')
                ->label('Save settings')
                ->action('save')
                ->keyBindings(['command+s', 'ctrl+s']),
        ];
    }

    private function fillForm(ContentCatalogApiSettings $settings): void
    {
        $this->form->fill([
            'enabled' => $settings->enabled,
            'turn_off_after' => $settings->disable_after_enabled,
            'turn_off_after_hours' => $settings->disable_after_hours,
        ]);
    }
}
