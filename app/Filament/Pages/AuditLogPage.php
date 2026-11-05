<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Bitácora de cambios del panel: quién creó, editó o borró qué (ver AppServiceProvider). */
class AuditLogPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.audit-log';

    protected static ?string $slug = 'bitacora-de-cambios';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Bitácora de cambios';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Bitácora de cambios';

    public static function canAccess(): bool
    {
        return auth()->user()?->esManager() ?? false;
    }

    public function table(Table $table): Table
    {
        $value = fn ($v) => match (true) {
            $v === null || $v === '' => '—',
            is_bool($v) => $v ? 'Sí' : 'No',
            is_array($v) => json_encode($v, JSON_UNESCAPED_UNICODE),
            default => (string) $v,
        };

        return $table
            ->query(AuditLog::query()->with('user:id,name'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/M/Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Quién')->placeholder('—'),
                TextColumn::make('action')->label('Qué hizo')->badge()
                    ->formatStateUsing(fn (string $state) => AuditLog::ACTIONS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('label')->label('Dónde')
                    ->formatStateUsing(fn (AuditLog $record) => $record->modelName().': '.$record->label)
                    ->searchable()->wrap(),
                TextColumn::make('changes')->label('Cambios')->wrap()
                    ->state(fn (AuditLog $record) => collect($record->changes ?? [])
                        ->map(fn ($v, $field) => $record->action === 'updated'
                            ? "{$field}: {$value($v['antes'] ?? null)} → {$value($v['despues'] ?? null)}"
                            : "{$field}: {$value($v)}")
                        ->implode("\n") ?: '—')
                    ->extraAttributes(['class' => 'whitespace-pre-line text-xs']),
            ])
            ->filters([
                SelectFilter::make('user_id')->label('Quién')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('action')->label('Qué hizo')->options(AuditLog::ACTIONS),
            ]);
    }
}
