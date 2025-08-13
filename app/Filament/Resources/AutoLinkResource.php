<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AutoLinkResource\Pages;
use App\Models\AutoLink;
use App\Rules\ValidAutoLinkPattern;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class AutoLinkResource extends Resource
{
    protected static ?string $model = AutoLink::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    public static bool $shouldRegisterNavigation = false;
    public static function getLabel(): string
    {
        return __('ledger.settings.auto_link');
    }

    public static function getModelLabel(): string
    {
        return __('ledger.settings.auto_link');
    }

    public static function getPluralLabel(): string
    {
        return __('ledger.settings.auto_link');
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()->schema([
                    Select::make('template')
                        ->label(__('auto_links.fields.template'))
                        ->options([
                            'redmine_ticket' => __('auto_links.templates.redmine_ticket'),
                            'gitlab_mr' => __('auto_links.templates.gitlab_mr'),
                            'jira_ticket' => __('auto_links.templates.jira_ticket'),
                            'spec_id' => __('auto_links.templates.spec_id'),
                        ])
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state) {
                            match ($state) {
                                'redmine_ticket' => $set('pattern', '/#(\d+)/') && $set('url_template', 'https://your-redmine/issues/$1'),
                                'gitlab_mr' => $set('pattern', '/(?:merge_requests|mr)s?\
/!(\d+)/') && $set('url_template', 'https://your-gitlab/project/-/merge_requests/$1'),
                                'jira_ticket' => $set('pattern', '/([A-Z]+-\d+)/') && $set('url_template', 'https://your-jira/browse/$1'),
                                'spec_id' => $set('pattern', '/([A-Z]{4}-\d{3})/') && $set('url_template', '/ledgers?query=$1'),
                                default => null,
                            };
                        }),
                    TextInput::make('label')
                        ->label(__('auto_links.fields.label'))
                        ->required()
                        ->unique(ignoreRecord: true),
                    Textarea::make('description')
                        ->label(__('auto_links.fields.description')),
                    TextInput::make('pattern')
                        ->label(__('auto_links.fields.pattern'))
                        ->required()
                        ->live(onBlur: true)
                        ->rule(new ValidAutoLinkPattern()),
                    TextInput::make('url_template')
                        ->label(__('auto_links.fields.url_template'))
                        ->required()
                        ->live(onBlur: true)
                        ->helperText(__('auto_links.helps.url_template')),
                    Section::make(__('auto_links.sections.scope'))
                        ->description(__('auto_links.helps.scope_description'))
                        ->schema([
                            SelectTree::make('folders')
                                ->label(__('auto_links.fields.folders'))
                                ->relationship(
                                    relationship: 'folders', // リレーション名
                                    titleAttribute: 'title', // 表示するカラム名
                                    parentAttribute: 'parent_id' // 親を識別するカラム名
                                )
                                ->enableBranchNode() // 親ノード（フォルダ）も選択可能にする
                                ->defaultOpenLevel(1)
                                ->multiple() // 複数のフォルダを選択可能にする
                                ->searchable()
                                ->placeholder(__('auto_links.placeholders.folders')),
                            // 必要に応じて、台帳定義を適用範囲にするコンポーネントもここに追加
                        ])
                        ->collapsible(),
                ])->columnSpan(2),
                Section::make()->schema([
                    TextInput::make('priority')
                        ->label(__('auto_links.fields.priority'))
                        ->numeric()
                        ->required()
                        ->default(0),
                    Toggle::make('is_enabled')
                        ->label(__('auto_links.fields.is_enabled'))
                        ->default(true),
                    Toggle::make('open_in_new_tab')
                        ->label(__('auto_links.fields.open_in_new_tab'))
                        ->live() // Add live to update preview
                        ->default(true),

                    Select::make('link_type')
                        ->label(__('auto_links.fields.link_type'))
                        ->helperText(__('auto_links.helps.link_type_helper'))
                        ->options(
                            collect(config('ledgerleap.auto_links.link_types'))
                                ->mapWithKeys(function ($type, $key) {
                                    $label = __($type['label_key']);
                                    $icon = $type['icon'];
                                    return [$key => Blade::render("<x-mary-icon name='{$icon}' class='inline-block h-4 w-4' /> {$label}")];
                                })
                                ->all()
                        )
                        ->allowHtml()
                        ->default('default')
                        ->live() // リアルタイム更新を有効にする
                        ->afterStateUpdated(function ( $get,  $set) {
                            // プレビューを更新するために、ダミーの値をセットするなどしてフォームを再描画させる
                            // または、Placeholderのcontent()がgetState()を直接参照するようにする
                        }),

                    Placeholder::make('icon_preview')
                        ->label(__('auto_links.fields.icon_preview')) // 新しい翻訳キー
                        ->content(function (Get $get) {
                            $linkType = $get('link_type') ?? 'default'; // デフォルト値を考慮
                            $iconName = config('ledgerleap.auto_links.link_types.'.$linkType.'.icon', 'o-link');
                            $labelKey = config('ledgerleap.auto_links.link_types.'.$linkType.'.label_key', 'auto_links.link_types.default');
                            $label = __($labelKey);

                            return new HtmlString(Blade::render(<<<HTML
                                <div class="flex items-center space-x-2">
                                    <x-mary-icon name="{$iconName}" class="h-6 w-6 text-primary-500" />
                                    <span class="text-gray-600 dark:text-gray-400">{$label}</span>
                                </div>
                            HTML));
                        })
                        ->columnSpanFull(), // 全幅を使用

                    Placeholder::make('created_at')
                        ->label(__('auto_links.fields.created_at'))
                        ->content(fn (?AutoLink $record): string => $record?->created_at?->diffForHumans() ?? '- '),
                    Placeholder::make('creator.name')
                        ->label(__('auto_links.fields.creator'))
                        ->content(fn (?AutoLink $record): string => $record?->creator?->name ?? '- '),
                    Placeholder::make('updated_at')
                        ->label(__('auto_links.fields.updated_at'))
                        ->content(fn (?AutoLink $record): string => $record?->updated_at?->diffForHumans() ?? '- '),
                    Placeholder::make('modifier.name')
                        ->label(__('auto_links.fields.modifier'))
                        ->content(fn (?AutoLink $record): string => $record?->modifier?->name ?? '- '),
                ])->columnSpan(1),
                Section::make('Preview')->schema([
                    TextInput::make('preview_text')
                        ->label(__('auto_links.fields.preview_text'))
                        ->live(),
                    Placeholder::make('preview_output')
                        ->label(__('auto_links.fields.preview_output'))
                        ->content(function (Get $get) { // Use content() and return HtmlString
                            $pattern = $get('pattern');
                            $template = $get('url_template');
                            $text = $get('preview_text');

                            if (empty($pattern) || empty($text) || empty($template)) {
                                return '';
                            }

                            if (@preg_match($pattern, null) === false) {
                                return new HtmlString('<span class="text-danger-500">'.__('auto_links.validations.invalid_regex').'</span>');
                            }

                            preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

                            if (empty($matches)) {
                                return new HtmlString(e($text) . '<p class="text-sm text-gray-500 mt-2">' . __('auto_links.validations.no_matches') . '</p>');
                            }
                            
                            $openInNewTab = $get('open_in_new_tab');
                            $target = $openInNewTab ? ' target="_blank"' : '';

                            $replacedHtml = preg_replace_callback($pattern, function ($match) use ($template, $target) {
                                $url = $template;
                                foreach ($match as $key => $value) {
                                    // URLエンコードしてから置換
                                    $url = str_replace('$' . $key, urlencode($value), $url);
                                }
                                return '<a href="' . e($url) . '"' . $target . ' class="font-bold text-primary-500 hover:underline">' . e($match[0]) . '</a>';
                            }, $text);

                            $tableHtml = '<table class="w-full mt-4 text-sm text-left text-gray-500 dark:text-gray-400"><tbody>';
                            foreach ($matches as $matchIndex => $match) {
                                $tableHtml .= '<tr class="border-b bg-white dark:border-gray-700 dark:bg-gray-800"><th colspan="2" class="px-6 py-2 font-medium text-gray-900 dark:text-white">Match ' . ($matchIndex + 1) . '</th></tr>';
                                foreach ($match as $groupIndex => $groupValue) {
                                    $tableHtml .= '<tr class="border-b bg-white dark:border-gray-700 dark:bg-gray-800"><th class="px-6 py-2 font-medium text-gray-900 dark:text-white">$' . e($groupIndex) . '</th><td class="px-6 py-2">' . e($groupValue) . '</td></tr>';
                                }
                            }
                            $tableHtml .= '</tbody></table>';
                            
                            // Add HTML source view
                            $sourceHtml = '<div class="mt-4"><h4 class="font-bold">'.__('auto_links.labels.generated_html').':</h4><pre class="p-2 mt-2 text-sm text-gray-500 bg-gray-100 rounded-md dark:bg-gray-900 dark:text-gray-400 overflow-x-auto"><code>' . e($replacedHtml) . '</code></pre></div>';

                            return new HtmlString($replacedHtml . $tableHtml . $sourceHtml);
                        }),
                ])->columnSpan(2),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label(__('auto_links.fields.label'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pattern')
                    ->label(__('auto_links.fields.pattern'))
                    ->searchable(),
                TextColumn::make('url_template')
                    ->label(__('auto_links.fields.url_template'))
                    ->searchable(),
                ToggleColumn::make('is_enabled')
                    ->label(__('auto_links.fields.is_enabled')),
                TextColumn::make('priority')
                    ->label(__('auto_links.fields.priority'))
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label(__('auto_links.fields.creator'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('modifier.name')
                    ->label(__('auto_links.fields.modifier'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('auto_links.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('auto_links.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()
                    ->beforeReplicaSaved(function (AutoLink $replica) {
                        $originalLabel = $replica->label;
                        $copyCount = 1;
                        while (AutoLink::where('label', $originalLabel . ' (' . $copyCount . ')')->exists()) {
                            $copyCount++;
                        }
                        $replica->label = $originalLabel . ' (' . $copyCount . ')';
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAutoLinks::route('/'),
            'create' => Pages\CreateAutoLink::route('/create'),
            'edit' => Pages\EditAutoLink::route('/{record}/edit'),
        ];
    }
}
