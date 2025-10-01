<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidAutoLinkPattern implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 正規表現の構文チェック
        if (@preg_match($value, '') === false) {
            $fail('The :attribute must be a valid regular expression.');

            return;
        }

        // URLテンプレートが提供されている場合、キャプチャグループの整合性をチェック
        // このルールはpatternフィールドに適用されるため、url_templateは他の場所から取得する必要がある
        // Filamentのフォームコンテキストからurl_templateを取得する方法を検討するか、
        // このルールをより上位のバリデーション（FormRequestなど）で適用し、両方の値を参照できるようにする
        // 現状はpatternの構文チェックのみに限定する
    }
}
