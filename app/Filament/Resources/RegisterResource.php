<?php

namespace App\Filament\Resources;

use App\Enums\allStatus;
use App\Enums\registerCurrentStatus;
use App\Enums\registerStatus;
use App\Filament\Resources\MarketerResource\RelationManagers\RegistersRelationManager;
use App\Filament\Resources\RegisterResource\Pages;
use App\Filament\Resources\RegisterResource\RelationManagers;
use App\Models\Campaign;
use App\Models\Clinic;
use App\Models\Customer;
use App\Models\FollowUpStatus;
use App\Models\Marketer;
use App\Models\Register;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Infolists\Components\Group as ComponentsGroup;
use Filament\Infolists\Components\Section as ComponentsSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class RegisterResource extends Resource
{
    protected static ?string $model = Register::class;
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $recordTitleAttribute='register_name';
    public static function getNavigationLabel(): string
    {
        return __('objects.registers');
    }
    public  static function getGloballySearchableAttributes(): array
    {
        return ['register_name']; // TODO: Change the autogenerated stub
    }
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'campaign'=>$record->campaign->campaign_name
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
                Hidden::make('updated_at')
            ->default(Carbon::now()),
                TextArea ::make('note')
                    ->label(__('labels.note'))
                    ->placeholder(__('more_extra'))
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('labels.id'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault:true),
                TextColumn::make('register_name')
                    ->label(__('labels.name'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('doctor_name')
                    ->label(__('labels.doctor'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('register_phone')
                    ->label(__('labels.phone_number'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('register_service')
                    ->label(__('labels.service'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('registration_source')
                    ->label(__('labels.source'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault:true),
                TextColumn::make('campaign.campaign_name')
                    ->label(__('labels.campaign'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('marketer.name')
                    ->label(__('labels.marketer'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault:true),
                TextColumn::make('campaign.clinic.name')
                    ->label(__('labels.clinic'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault:true),
                TextColumn::make('campaign.clinic.customer.name')
                    ->label(__('labels.customer'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault:true),
                TextColumn::make('register_information')
                    ->label(__('labels.moore_information'))
                    ->limit(60)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        if (strlen($state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        // Only render the tooltip if the column content exceeds the length limit.
                        return $state;
                    })
                    ->toggleable(isToggledHiddenByDefault:true),
                TextColumn::make('note')
                    ->label(__('labels.note'))
                    ->limit(60)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        if (strlen($state) <= $column->getCharacterLimit()) {
                            return null;
                        }
                        // Only render the tooltip if the column content exceeds the length limit.
                        return $state;
                    })
                    ->toggleable(isToggledHiddenByDefault:true),
                TextColumn::make('current_status')
                    ->label(__('labels.status'))
                    ->badge()
                    ->color(function ($record){
                        return Color::hex($record->latestStatus->follow_up_status_id['status_theme']);
                    })
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('FollowUpStatus.note')
                    ->label(__('labels.note'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('labels.created_at'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault:true),
                TextColumn::make('updated_at')
                    ->label(__('labels.updated_at'))
                    ->sortable(true)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault:true),
            ])
            ->filters([
                Filter::make('Customer')
                    ->form([
                        Select::make('customer')
                            ->label(__('labels.customer'))
                            ->native(false)
                            ->options(
                                Customer::all()->pluck('name', 'id')->toArray()
                            )
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('clinic', null))
                            ->hidden(!isAdmin()),
                        Select::make('clinic')
                            ->label(__('labels.clinic'))
                            ->options(fn (Forms\Get $get):array=>
                            (isAdmin())?Clinic::query()->where('customer_id', $get('customer'))->pluck('name', 'id')->toArray():Clinic::query()->where('customer_id',auth()->user()->accountable_id)->pluck('name', 'id')->toArray()
                            )
                            ->native(false)
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('campaign', null))
                            ->hidden(!(isAdmin()||isCustomer())),
                        Select::make('campaign')
                            ->label(__('labels.campaign'))
                            ->options(fn (Forms\Get $get):array=>
                            (isAdmin()|| isCustomer())? Campaign::where('clinic_id', $get('clinic'))->pluck('campaign_name', 'id')->toArray():Campaign::query()->pluck('campaign_name', 'id')->toArray()
                            )
                            ->native(false)
                            ->preload()
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['customer'],
                                fn (Builder $query, $data): Builder => $query->whereRelation('campaign.clinic','customer_id','=',$data),
                            )
                            ->when(
                                $data['clinic'],
                                fn (Builder $query, $data): Builder => $query->whereRelation('campaign','clinic_id','=',$data),
                            )
                            ->when(
                                $data['campaign'],
                                fn (Builder $query, $data): Builder => $query->where('campaign_id',$data),
                            );
                    })
                    ->hiddenOn([RegistersRelationManager::class,CampaignResource\RelationManagers\RegistersRelationManager::class]),
                SelectFilter::make('marketer')
                    ->label(__('labels.marketer'))
                    ->relationship('marketer', 'name')
                    ->searchable()
                    ->preload()
                    ->visibleOn(RegisterResource::class),
                Filter::make('created_at')
                    ->label(__('labels.created_at'))
                    ->form([
                        DatePicker::make('created_from'),
                        DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                Tables\Filters\TrashedFilter::make()
            ])
            ->actions([
                ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                    ->label(__('labels.drop_ToRecycleBin')),
                    ExportBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make()
                ]),
            ])
            ->emptyStateActions([
            ]);
    }
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ComponentsSection::make('registers')
                    ->description('register Data')
                    ->schema([
                        ComponentsGroup::make()
                            ->schema([
                                ComponentsSection::make()
                                    ->description('register Data')
                                    ->schema([
                                        TextEntry::make('register_name')
                                            ->label(__('register name')),
                                        TextEntry::make('id')
                                            ->label(__('register id')),
                                        TextEntry::make('register_phone')
                                            ->label(__('register phone')),
                                        TextEntry::make('doctor_name')
                                            ->label(__('doctor')),
                                    ])->columns(2),
                                ComponentsSection::make('register')
                                    ->schema([
                                        TextEntry::make('campaign.clinic.customer.name')
                                            ->label(__('customer ')),
                                        TextEntry::make('campaign.clinic.name')
                                            ->label(__('clinic')),
                                        TextEntry::make('campaign.campaign_name')
                                            ->label(__('campaign')),
                                        TextEntry::make('marketer.name')
                                            ->label(__('marketer')),
                                    ])->columns(2),
                            ]),
                        ComponentsGroup::make()
                            ->schema([
                                ComponentsSection::make('Data')
                                    ->schema([

                                        TextEntry::make('registration_source')
                                            ->label(__('source')),
                                        TextEntry::make('register_service')
                                            ->label(__('service')),
                                    ])->columns(2),
                                ComponentsSection::make('Date')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('registered at'),
                                        TextEntry::make('updated_at')
                                    ])->columns(2),
                                ComponentsSection::make('register')
                                    ->schema([
                                        TextEntry::make('current_status')
                                            ->label(__('labels.status'))
                                            ->badge()
                                            ->color(function ($record){
                                                return Color::hex($record->latestStatus->follow_up_status_id['status_theme']);
                                            })
                                    ]),
                            ]),
                        ComponentsSection::make('more information')
                            ->schema([
                                TextEntry::make('register_information')
                                    ->label(__('information')),
                                TextEntry::make('note')
                                    ->label(__('note')),
                            ])->columns(2),
                    ])->columns(2),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderBy('id','DESC')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
    public static function getRelations(): array
    {
        return [
            RelationManagers\FollowUpStatusRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegisters::route('/'),
            'view' => Pages\ViewRegister::route('/{record}'),
            'edit' => Pages\EditRegister::route('/{record}/edit')
        ];
    }
}
