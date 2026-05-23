<?php

return [
    'mcp' => [
        'approved_locked' => 'この台帳は承認済みのため、初期のMCP更新契約では更新できません。',
        'content_patch_required' => 'content_patch が必要です。',
        'detail_summary' => '台帳「:title」の最新内容を取得しました。現在の状態は :status です。',
        'invalid_content_patch_json' => 'content_patch は JSON オブジェクト形式で指定してください。',
        'preview_summary' => '台帳「:title」の更新プレビューです。:count 件の変更候補があり、現在の状態は :status です。',
        'related_axis_required' => '関連レコード調査では、識別番号または意味検索の少なくとも一方を有効にしてください。',
        'related_summary' => '台帳「:title」に関連するレコードが :count 件見つかりました。',
        'tag_updates_not_supported' => 'タグ更新はまだ初期のMCP更新契約ではサポートされていません。内容更新のみを行うか、タグ更新の公開契約整備を待ってください。',
        'updated_summary' => '台帳「:title」を更新しました。:count 件の変更を反映し、現在の状態は :status です。',
        'workflow_history_base_diff_not_found' => '比較の基準となる版が見つかりませんでした。',
        'workflow_history_base_diff_required' => '比較対象を指定する場合は、基準となる新しい版の diff ID も指定してください。',
        'workflow_history_change_type_added' => '追加',
        'workflow_history_change_type_deleted' => '削除',
        'workflow_history_change_type_modified' => '変更',
        'workflow_history_comparison_summary' => 'Ver.:target_version から Ver.:base_version への変更を比較しました。変更項目は :count 件で、更新者は :modifier、更新日時は :datetime です。',
        'workflow_history_empty_value' => '（空）',
        'workflow_history_next_action_review_activity' => '必要なら、この更新の前後で誰が何をしたかを追加で確認してください。',
        'workflow_history_next_action_trace_related' => '必要なら、この変更時点に関係する関連レコード調査へ進んでください。',
        'workflow_history_same_diff_not_allowed' => '同じ版同士は比較できません。新しい版と古い版をそれぞれ指定してください。',
        'workflow_history_target_diff_not_found' => '比較対象となる過去版が見つかりませんでした。',
    ],
];
