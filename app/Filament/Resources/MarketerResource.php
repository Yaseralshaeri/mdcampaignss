<?php

namespace App\Filament\Resources;

use App\Enums\allStatus;
use App\Filament\Resources\ClinicResource\RelationManagers\MarketersRelationManager;
use App\Filament\Resources\MarketerResource\Pages;
use App\Filament\Resources\MarketerResource\RelationManagers;
use App\Models\Clinic;
use App\Models\Coordinator;
use App\Models\Customer;
use App\Models\Marketer;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as ComponentsSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class MarketerResource extends Resource
{
    protected static ?string $model = Marketer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    public static function getNavigationGroup(): ?string
    {
        return __('objects.users');
    }
    public static function getNavigationLabel(): string
    {
        return __('objects.marketers');
    }
    protected static ?string $recordTitleAttribute='name';
    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name;
    }
    public static function getGlobalSearchResultDetails(Model $record): array
    { // TODO: Change the autogenerated stub
        return [
            'clinic'=>$record->clinic->name
        ] ;
    }
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();// TODO: Change the autogenerated stub
    }
    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getModel()::count() == 0 ? 'warning' : 'success';// TODO: Change the autogenerated stub
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('labels.marketer'))
                    ->description(__('labels.marketer_data'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('labels.name'))
                            ->required()
                            ->maxLength(255)
                            ->minLength(3),
                        Select::make('clinic_id')
                            ->label(__('labels.clinic'))
                            ->relationship('clinic','name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false),
                        \Filament\Forms\Components\Fieldset::make('Account Data')
                            ->hiddenOn('edit')
                    ->label(__('labels.account_data'))
                            ->relationship('account')
                            ->schema([
                                TextInput::make('email')
                                    ->label(__('labels.email'))
                                    ->email()
                                    ->unique(ignoreRecord: true),
                                TextInput::make('password')
                                    ->label(__('labels.password'))
                                    ->required()
                                    ->password()
                                    ->confirmed()
                                    ->default('')
                                    ->regex("^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$^"),
                                TextInput::make('password_confirmation')
                                    ->label(__('labels.passwordConf'))
                                    ->required()
                                    ->columnSpanFull(),
                            ]),
                        Hidden::make('created_at')
                            ->hiddenOn('edit'),
                        Hidden::make('updated_at')
                            ->hiddenOn('create')
                    ])->columns(2),
            ]);    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('labels.id'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault:true),
                TextColumn::make('name')
                    ->label(__('labels.name'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('clinic.customer.name')
                    ->label(__('labels.customer'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('account.email')
                    ->label(__('labels.email'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('clinic.name')
                    ->label(__('labels.clinic'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable()
                    ->hiddenOn(MarketersRelationManager::class),
                TextColumn::make('registers_count')
                    ->counts('registers')
                    ->label(__('labels.registers_count'))
                    ->suffix('   '.__('objects.registers'))
                    ->color(function ($record)
                    {
                        if($record->registers_count==0){
                            return 'warning';
                        }
                        else{
                            return ($record->registers_count>20)?'success':'info';
                        }
                    })
                    ->icon(function ($record)
                    {
                        return ($record->registers_count>3)?'heroicon-m-arrow-trending-up':'heroicon-m-arrow-trending-down';
                    })
                    ->sortable(true)
                    ->toggleable(),
                TextColumn::make('account.status')
                    ->label(__('labels.status'))
                    ->badge()
                    ->sortable(true)
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('is_blocked')
                    ->label(__('actions.is_blocked'))
                    ->query(
                        function ($query){
                            return $query->where('account.status',false);
                        }
                    ),
                Filter::make('Customer')
                    ->form([
                        Select::make('customer')
                            ->label(__('labels.customer'))
                            ->options(Customer::query()->pluck('name', 'id'))
                            ->live()
                            ->searchable()
                            ->preload()
                            ->hidden(!isAdmin())
                            ->native(false)
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('clinic', null)),
                        Select::make('clinic')
                            ->label(__('labels.clinic'))
                            ->options(fn (Forms\Get $get): \Illuminate\Support\Collection =>
                            (isAdmin())?Clinic::query()->where('customer_id', $get('customer'))->pluck('name', 'id'):Clinic::query()->where('customer_id',auth()->user()->accountable_id)->pluck('name', 'id')
                            )
                            ->preload()
                            ->native(false)
                            ->hidden(isClinic()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['customer'],
                                fn (Builder $query, $data): Builder => $query->whereRelation('clinic','customer_id','=',$data),
                            )
                            ->when(
                                $data['clinic'],
                                fn (Builder $query, $data): Builder => $query->where('clinic_id',$data),
                            );
                    })
                ->hiddenOn(MarketersRelationManager::class)
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('block')
                        ->label(__('labels.status_toggle'))
                        ->action(function (Collection $records){
                            $records->each(function ($record){
                                ($record->account->status==allStatus::Active)?$record->account->status=allStatus::Blocked:$record->account->status=allStatus::Active;
                                 toggleStatus($record->account->id,$record->account->status);

                            });
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->modalIcon('heroicon-o-x-circle'),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ComponentsSection::make(__('labels.marketer_data'))
                    ->icon('heroicon-m-user')
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('labels.name'))
                            ->weight(FontWeight::SemiBold),
                        TextEntry::make('account.email')
                            ->label(__('labels.email'))
                            ->weight(FontWeight::SemiBold),
                        TextEntry::make('clinic.name')
                            ->label(__('labels.clinic'))
                            ->weight(FontWeight::SemiBold),
                        TextEntry::make('created_at')
                            ->label(__('labels.created_at'))
                            ->weight(FontWeight::SemiBold),
                        TextEntry::make('updated_at')
                            ->label(__('labels.updated_at'))
                            ->weight(FontWeight::SemiBold),


                    ])->columns(2),
            ]);
    }
    public static function getRelations(): array
    {
        return [
            RelationManagers\RegistersRelationManager::class
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderBy('id','DESC')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketers::route('/'),
            'create' => Pages\CreateMarketer::route('/create'),
            'view' => Pages\ViewMarketer::route('/{record}'),
            'edit' => Pages\EditMarketer::route('/{record}/edit'),
        ];
    }
    /**
     * @param string|null $modelLabel
     */
    public static function getModelLabel(): string
    {
        return __('labels.marketer');
    }

    /**
     * @param string|null $pluralModelLabel
     */
    public static function getPluralModelLabel(): string
    {
        return __('objects.marketers');
    }
}
