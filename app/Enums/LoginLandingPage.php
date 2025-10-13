<?php

namespace App\Enums;

// backed enum を使う (string or int)
enum LoginLandingPage: string
{
    case MyPortal = 'my_portal';
    case Ledgers = 'ledgers'; // 台帳/フォルダ一覧画面を示す値

    // (オプション) 日本語ラベルを取得するメソッド
    public static function options(): array
    {

        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }

    // (オプション) Enum の全ケースを取得する静的メソッド (Select などで使う)

    public function label(): string
    {
        return match ($this) {
            self::MyPortal => __('ledger.landing_page_option_my_portal'), // 翻訳キー
            self::Ledgers => __('ledger.landing_page_option_ledgers'),   // 翻訳キー
        };
    }

    // 修正: MaryUI が期待する形式の options 配列を返す
    /*    public static function optionsForMaryUI(): array
        {
            return collect(self::cases())
                ->map(fn ($case) => [
                    'id' => $case->value, // option の value になるキー (select, radio で共通)
                    'name' => $case->label(), // 表示ラベルになるキー (select, radio で共通)
                    // 必要に応じて 'disabled' や 'hint' なども追加可能
                ])
                ->all();
        }*/

    /**
     * MaryUI の x-radio が期待する形式の options 配列を返す
     *
     * @param  string  $currentValue  現在選択されている値 (Enum の value)
     */
    public static function optionsForMaryUIRadio(string $currentValue): array // メソッド名を変更 (Radio用)
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'id' => $case->value, // value 属性になるキー
                'name' => $case->label(), // 表示ラベルになるキー
                // 修正: 現在の値と一致する場合に checked: true を追加
                'checked' => $case->value === $currentValue,
                'selected' => $case->value === $currentValue,
                // 必要に応じて 'disabled' や 'hint' なども追加可能
            ])
            ->all();
    }

    /**
     * MaryUI の x-radio が期待する形式の options 配列を返す
     *
     * @param  string  $currentValue  現在選択されている値 (Enum の value)
     */
    public static function optionsForMaryUI(string $currentValue): array // メソッド名を変更 (Radio用)
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'id' => $case->value, // value 属性になるキー
                'name' => $case->label(), // 表示ラベルになるキー
                // 修正: 現在の値と一致する場合に checked: true を追加
                'checked' => $case->value === $currentValue,
                'selected' => $case->value === $currentValue,
                // 必要に応じて 'disabled' や 'hint' なども追加可能
            ])
            ->all();
    }
}
