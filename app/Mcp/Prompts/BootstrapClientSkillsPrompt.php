<?php

namespace App\Mcp\Prompts;

use App\Services\Ai\BootstrapManifestService;
use App\Services\Ai\ClientSkillBootstrapService;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('bootstrap-client-skills')]
#[Title('Bootstrap Client Skills')]
#[Description(
    'Returns a short onboarding prompt starter with first questions, checks, '
    .'and next actions for LedgerLeap clients.'
)]
class BootstrapClientSkillsPrompt extends Prompt
{
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'client_type',
                description: 'Client type: copilot, claude-code, gemini-cli, or openai-agents. Defaults to copilot.',
                required: false,
            ),
            new Argument(
                name: 'role_profile',
                description: 'Role profile: operator, administrator, or field-leader. Defaults to operator.',
                required: false,
            ),
            new Argument(
                name: 'model_profile',
                description: 'Model profile: small-local, general-local, or remote-capable. Defaults to general-local.',
                required: false,
            ),
            new Argument(
                name: 'language',
                description: 'Response language: ja or en. Defaults to ja.',
                required: false,
            ),
        ];
    }

    public function handle(Request $request): array
    {
        $input = $request->validate(
            [
                'client_type' => ['sometimes', 'string', Rule::in(ClientSkillBootstrapService::SUPPORTED_CLIENTS)],
                'role_profile' => ['sometimes', 'string', Rule::in(array_keys(BootstrapManifestService::ROLE_PROFILES))],
                'model_profile' => [
                    'sometimes',
                    'string',
                    Rule::in(array_keys(BootstrapManifestService::MODEL_PROFILES)),
                ],
                'language' => ['sometimes', 'string', Rule::in(['ja', 'en'])],
            ],
            [
                'client_type.in' => 'client_type は copilot, claude-code, gemini-cli, openai-agents のいずれかを指定してください。',
                'role_profile.in' => 'role_profile は operator, administrator, field-leader のいずれかを指定してください。',
                'model_profile.in' => 'model_profile は small-local, general-local, remote-capable のいずれかを指定してください。',
                'language.in' => 'language は ja または en を指定してください。',
            ]
        );

        $clientType = (string) ($input['client_type'] ?? 'copilot');
        $roleProfile = (string) ($input['role_profile'] ?? 'operator');
        $modelProfile = (string) ($input['model_profile'] ?? 'general-local');
        $language = (string) ($input['language'] ?? 'ja');

        return [
            Response::text($this->assistantMessage($clientType, $roleProfile, $modelProfile, $language))->asAssistant(),
            Response::text($this->userMessage($clientType, $roleProfile, $modelProfile, $language)),
        ];
    }

    private function assistantMessage(
        string $clientType,
        string $roleProfile,
        string $modelProfile,
        string $language,
    ): string {
        $roleLabel = (string) BootstrapManifestService::ROLE_PROFILES[$roleProfile]['label'];
        $clientLabel = $this->clientLabel($clientType, $language);
        $outputCount = $this->outputCount($modelProfile);
        $clientTip = $this->clientTip($clientType, $language);

        if ($language === 'en') {
            return trim(<<<MARKDOWN
            You are LedgerLeap's onboarding assistant.

            Keep the response compact and client-facing.

            ## Context
            - Client: {$clientLabel}
            - Role: {$roleLabel}
            - Model profile: {$modelProfile}

            ## Response rules
            - Return only these sections: First questions, Checks, Next action
            - Keep each section to {$outputCount} bullets or fewer
            - Use business terms only
            - Ask for only the minimum missing details
            - Do not explain internal implementation details
            - Do not turn this prompt into bundle resolution or file distribution guidance

            ## Client tip
            - {$clientTip}
            MARKDOWN);
        }

        return trim(<<<MARKDOWN
        あなたは LedgerLeap の開始支援アシスタントです。

        応答は短く、client-facing の内容だけにしてください。

        ## 利用条件
        - クライアント: {$clientLabel}
        - 役割: {$roleLabel}
        - モデル特性: {$modelProfile}

        ## 応答ルール
        - 「最初の質問例」「確認事項」「次アクション」だけを返す
        - 各見出しは {$outputCount} 項目以内にする
        - 業務語彙だけを使う
        - 足りない情報は最小限だけ確認する
        - 内部実装の説明はしない
        - bundle 解決や file 配布の主案内にはしない

        ## クライアント向けのコツ
        - {$clientTip}
        MARKDOWN);
    }

    private function userMessage(
        string $clientType,
        string $roleProfile,
        string $modelProfile,
        string $language,
    ): string {
        $questionExamples = $this->formatBullets(
            $this->starterQuestions($roleProfile, $modelProfile, $language)
        );
        $checks = $this->formatBullets(
            $this->checks($roleProfile, $modelProfile, $language)
        );
        $nextActions = $this->formatBullets(
            $this->nextActions($clientType, $modelProfile, $language)
        );

        if ($language === 'en') {
            return trim(<<<MARKDOWN
            Help a new LedgerLeap user get started.

            ## First questions
            {$questionExamples}

            ## Checks
            {$checks}

            ## Next action
            {$nextActions}
            MARKDOWN);
        }

        return trim(<<<MARKDOWN
        LedgerLeap を使い始める人に、次を短く案内してください。

        ## 最初の質問例
        {$questionExamples}

        ## 確認事項
        {$checks}

        ## 次アクション
        {$nextActions}
        MARKDOWN);
    }

    /**
     * @return array<int, string>
     */
    private function starterQuestions(string $roleProfile, string $modelProfile, string $language): array
    {
        $examples = match ($roleProfile) {
            'administrator' => $language === 'en'
                ? [
                    'Show me this month\'s application count and approval backlog.',
                    'What operational activity should I review first today?',
                    'Show folders or teams with many pending approvals.',
                ]
                : [
                    '今月の申請件数と承認滞留を見せて。',
                    '今日の運用確認で先に見るべき活動履歴を教えて。',
                    '承認待ちが多いフォルダやチームを見せて。',
                ],
            'field-leader' => $language === 'en'
                ? [
                    'Show records that may need to be sent back or corrected.',
                    'Find team records that likely need an update today.',
                    'What should I confirm before updating a record on behalf of my team?',
                ]
                : [
                    '差し戻しや修正が必要そうな記録を見せて。',
                    '今日更新が必要そうなチームの記録を探して。',
                    '代理更新の前に確認すべきことを教えて。',
                ],
            default => $language === 'en'
                ? [
                    'Show me approval tasks I should handle today.',
                    'Find the daily reports created last week.',
                    'Tell me what I need before creating a new report.',
                ]
                : [
                    '今日対応すべき承認待ちを見せて。',
                    '先週作成した日報を探して。',
                    '新しい日報を作る前に必要な項目を教えて。',
                ],
        };

        return array_slice($examples, 0, $this->outputCount($modelProfile));
    }

    /**
     * @return array<int, string>
     */
    private function checks(string $roleProfile, string $modelProfile, string $language): array
    {
        $checks = match ($roleProfile) {
            'administrator' => $language === 'en'
                ? [
                    'Which period, folder, or team matters most right now?',
                    'Do you want status checking, audit review, or aggregation first?',
                    'Do you already know the target folder or should we narrow it down together?',
                ]
                : [
                    'まず確認したい期間・フォルダ・チームが決まっているか。',
                    '先に見たいのが状況確認・活動監査・集計のどれか。',
                    '対象フォルダが決まっているか、それとも一緒に絞り込むか。',
                ],
            'field-leader' => $language === 'en'
                ? [
                    'Do you already know the team, person, or record to focus on?',
                    'Is your first goal review, update, or send-back handling?',
                    'Do you need only the latest records or also older history?',
                ]
                : [
                    '対象のチーム・担当者・記録が決まっているか。',
                    '最初の目的が確認・更新・差し戻し対応のどれか。',
                    '最新の記録だけ見たいか、過去履歴も必要か。',
                ],
            default => $language === 'en'
                ? [
                    'Which period or ledger do you want to start with?',
                    'Do you want to look up records first or create a new one?',
                    'Are you checking only your own work or also shared items?',
                ]
                : [
                    '最初に見たい期間や台帳が決まっているか。',
                    'まず記録を探したいのか、新規作成したいのか。',
                    '自分の作業だけを見るか、共有分も確認したいか。',
                ],
        };

        return array_slice($checks, 0, $this->outputCount($modelProfile));
    }

    /**
     * @return array<int, string>
     */
    private function nextActions(string $clientType, string $modelProfile, string $language): array
    {
        $actions = $language === 'en'
            ? [
                'Start with one short request only.',
                'If you need placement or discovery details, check `ledgerleap://bootstrap/'.$clientType.'`.',
                'If you need a role-aware bundle, use `GetClientBootstrapManifestTool` or the bootstrap manifest API.',
            ]
            : [
                'まずは 1 件だけ短い依頼で会話を始める。',
                '配置や導線を詳しく確認したい場合は `ledgerleap://bootstrap/'.$clientType.'` を見る。',
                '役割に応じた bundle を知りたい場合は `GetClientBootstrapManifestTool` または bootstrap manifest API を使う。',
            ];

        return array_slice($actions, 0, $this->outputCount($modelProfile));
    }

    private function clientLabel(string $clientType, string $language): string
    {
        return match ($clientType) {
            'claude-code' => $language === 'en' ? 'Claude Code' : 'Claude Code',
            'gemini-cli' => $language === 'en' ? 'Gemini CLI' : 'Gemini CLI',
            'openai-agents' => $language === 'en' ? 'OpenAI Agents' : 'OpenAI Agents',
            default => $language === 'en' ? 'GitHub Copilot' : 'GitHub Copilot',
        };
    }

    private function clientTip(string $clientType, string $language): string
    {
        return match ($clientType) {
            'claude-code' => $language === 'en'
                ? 'Split the request into goal, constraints, and desired output.'
                : '依頼を「目的」「制約」「欲しい出力」に分けて短く伝える。',
            'gemini-cli' => $language === 'en'
                ? 'Use short headings and bullets instead of long paragraphs.'
                : '長文より、短い見出しと箇条書きで依頼する。',
            'openai-agents' => $language === 'en'
                ? 'State the goal and expected output first, then add only the missing details.'
                : '最初に目的と欲しい出力を示し、必要な条件だけ後から足す。',
            default => $language === 'en'
                ? 'Start with one short request and keep the scope narrow.'
                : '最初は短い依頼を 1 件だけ出し、範囲を広げすぎない。',
        };
    }

    private function outputCount(string $modelProfile): int
    {
        return match ($modelProfile) {
            'small-local' => 2,
            default => 3,
        };
    }

    /**
     * @param  array<int, string>  $items
     */
    private function formatBullets(array $items): string
    {
        return collect($items)
            ->map(fn (string $item) => '- '.$item)
            ->implode("\n");
    }
}
