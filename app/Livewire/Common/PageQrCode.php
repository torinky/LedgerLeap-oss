<?php

namespace App\Livewire\Common;

use App\Livewire\BaseLivewireComponent;
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
     * Filament用のアクション定義
     */
    public function qrCodeAction(): Action
    {
        return Action::make('qrCode')
            ->label(__('ledger.page_qr_code.modal_title'))
            ->icon('heroicon-o-qr-code')
            ->iconButton()
            ->color('gray')
            ->modalHeading(__('ledger.page_qr_code.modal_title'))
            ->modalContent(function () {
                $url = $this->resolveCurrentUrl();

                return view('livewire.common.page-qr-code-modal-content', [
                    'url' => $url,
                    'qrCode' => QrCode::size(250)->format('svg')->generate($url)->toHtml(),
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
