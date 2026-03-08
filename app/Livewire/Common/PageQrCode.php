<?php

namespace App\Livewire\Common;

use App\Livewire\BaseLivewireComponent;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\Computed;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PageQrCode extends BaseLivewireComponent implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    public string $triggerType = 'mary'; // 'mary' or 'filament'

    public string $url = '';

    public bool $showModal = false;

    /**
     * モーダルを開き、URLを更新する (MaryUI用)
     *
     * @param string $currentUrl クライアント側から渡される現在のURL
     */
    public function openModal(string $currentUrl): void
    {
        $this->url = $currentUrl;
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
            ->modalContent(fn () => view('livewire.common.page-qr-code-modal-content', [
                'url' => $this->url,
                'qrCode' => $this->qrCode,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('ledger.close'))
            ->extraAttributes([
                'x-on:click' => '$wire.set("url", window.location.href)',
            ]);
    }

    public function render()
    {
        return view('livewire.common.page-qr-code');
    }
}
