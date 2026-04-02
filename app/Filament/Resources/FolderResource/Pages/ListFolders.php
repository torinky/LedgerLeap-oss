<?php

namespace App\Filament\Resources\FolderResource\Pages;

use App\Filament\Resources\FolderResource;
use App\Models\Folder;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class ListFolders extends ListRecords
{
    protected static string $resource = FolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus'),
            Actions\Action::make('tree_view')
                ->label(__('ledger.views.tree'))
                ->color('info')
                ->icon('heroicon-o-share')
                ->url(FolderResource::getUrl('tree')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Folder::withDepth()->orderBy('_lft'))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('フォルダ名')
                    ->searchable(query: function (EloquentBuilder $query, string $search): EloquentBuilder {
                        return $query->where('title', 'like', "%{$search}%");
                    })
                    ->getStateUsing(function (Folder $record) {
                        return str_repeat('・', $record->depth).' '.$record->title;
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->label('説明')
                    ->limit(50),
                Tables\Columns\TextColumn::make('parent.title')
                    ->label('親フォルダ')
                    ->searchable(query: fn (EloquentBuilder $query, string $search): EloquentBuilder => $query->whereRelation('parent', 'title', 'like', "%{$search}%")),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('ロール')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日時')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新日時')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('削除日時')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),

                // 既存の親フォルダフィルタ
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('親フォルダ')
                    ->relationship('parent', 'title')
                    ->searchable()
                    ->preload(),

                // 新しく追加する「フォルダで絞り込み」フィルタ
                Tables\Filters\SelectFilter::make('folder_scope') // フィルタを識別する一意なキー
                    ->label('フォルダで絞り込み')
                    ->options(Folder::pluck('title', 'id')->all()) // 全てのフォルダを選択肢に
                    ->query(function (EloquentBuilder $query, array $data): EloquentBuilder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        $folder = Folder::find($data['value']);

                        // フォルダが見つからない場合は何もしない
                        if (! $folder) {
                            return $query;
                        }

                        // 選択されたフォルダ自身とその子孫を対象にする
                        return $query->whereIsOrDescendantOf($folder);
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                //                \Filament\Actions\ActionGroup::make([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
                \Filament\Actions\ForceDeleteAction::make(),
                \Filament\Actions\RestoreAction::make(),
                //                ]),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }
}
