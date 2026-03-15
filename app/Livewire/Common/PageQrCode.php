<?php

namespace App\Livewire\Common;

use App\Livewire\BaseLivewireComponent;
use App\Services\QrCodeDownloadFileNameService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Attributes\Computed;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PageQrCode extends BaseLivewireComponent implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public string $triggerType = 'mary'; // 'mary' or 'filament'

    public string $url = '';

    public bool $showModal = false;

    public bool $isLedgerEditScreen = false;

    public function mount()
    {
        $this->isLedgerEditScreen = $this->shouldUsePrefillModal(request()->route()?->getName());
    }

    protected function shouldUsePrefillModal(?string $routeName): bool
    {
        return in_array($routeName, ['ledger.create', 'ledger.edit', 'ledger.duplicate'], true);
    }

    /**
     * 現在のページのURLを取得する
     */
    protected function resolveCurrentUrl(): string
    {
        // AJAXリクエストの場合はRefererヘッダーから、そうでない場合は現在のURLを取得
        return request()->header('Referer') ?? request()->fullUrl();
    }

    /**
     * モーダルを開き、URLを更新する (MaryUI用)
     */
    public function openModal(?string $url = null): void
    {
        if ($this->isLedgerEditScreen) {
            $this->dispatch('open-prefill-modal');

            return;
        }

        $this->url = $url !== null && $url !== ''
            ? $url
            : $this->resolveCurrentUrl();
        $this->showModal = true;
    }

    /**
     * QRコード（SVG）を生成する
     */
    #[Computed]
    public function qrCode(): string
    {
        if (empty($this->url)) {
            return '';
        }

        return QrCode::size(250)
            ->format('svg')
            ->generate($this->url)
            ->toHtml();
    }

    /**
     * ダウンロード用のファイル名を生成する
     */
    #[Computed]
    public function downloadFileName(): string
    {
        return app(QrCodeDownloadFileNameService::class)->forPageShare(
            $this->url !== '' ? $this->url : $this->resolveCurrentUrl()
        );
    }

    /**
     * Filament用のアクション定義
     */
    public function qrCodeAction(): Action
    {
        $action = Action::make('qrCode')
            ->label(__('ledger.page_qr_code.modal_title'))
            ->icon('heroicon-o-qr-code')
            ->iconButton()
            ->color('gray');

        if ($this->isLedgerEditScreen) {
            return $action->action(function () {
                $this->dispatch('open-prefill-modal');
            });
        }

        return $action
            ->modalHeading(__('ledger.page_qr_code.modal_title'))
            ->modalDescription(__('ledger.page_qr_code.description'))
            ->modalContent(function () {
                $url = $this->resolveCurrentUrl();

                return view('livewire.common.page-qr-code-modal-content', [
                    'url' => $url,
                    'qrCode' => QrCode::size(250)->format('svg')->generate($url)->toHtml(),
                    'downloadName' => app(QrCodeDownloadFileNameService::class)->forPageShare($url),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('ledger.close'));
    }

    public function render()
    {
        return view('livewire.common.page-qr-code');
    }
}
