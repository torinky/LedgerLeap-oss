<?php

namespace App\Livewire\Common;

use App\Livewire\BaseLivewireComponent;
use Livewire\Attributes\Computed;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PageQrCode extends BaseLivewireComponent
{
    public string $triggerType = 'mary'; // 'mary' or 'filament'

    public string $url = '';

    public bool $showModal = false;

    /**
     * モーダルを開き、URLを更新する
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

    public function render()
    {
        return view('livewire.common.page-qr-code');
    }
}
