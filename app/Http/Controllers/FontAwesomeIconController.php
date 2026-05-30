<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FontAwesomeIconController extends Controller
{
    /**
     * 指定されたスタイルとアイコン名のSVGファイルを配信する
     */
    public function serveIcon(string $style, string $icon): BinaryFileResponse|Response
    {
        $path = base_path('node_modules/@fortawesome/fontawesome-free/svgs/'.$style.'/'.$icon.'.svg');

        if (! File::exists($path)) {
            // アイコンが見つからない場合はデフォルトのファイルアイコンを返す
            $defaultPath = base_path('node_modules/@fortawesome/fontawesome-free/svgs/solid/file.svg');
            if (! File::exists($defaultPath)) {
                abort(404, 'Icon not found');
            }
            $path = $defaultPath;
        }

        // SVGファイルを読み込んで、適切な属性を追加
        $svgContent = File::get($path);

        // preserveAspectRatio属性を追加して、縦横比を維持
        // すでに存在する場合は置き換え、存在しない場合は追加
        if (preg_match('/<svg([^>]*)>/', $svgContent, $matches)) {
            $svgTag = $matches[0];

            // preserveAspectRatio属性を削除（存在する場合）
            $svgTag = preg_replace('/\s+preserveAspectRatio="[^"]*"/', '', $svgTag);

            // 新しい属性を追加
            $newSvgTag = str_replace('<svg', '<svg preserveAspectRatio="xMidYMid meet"', $svgTag);

            // file-pdfアイコンの場合、viewBoxを修正（FontAwesome 7.1.0のバグ対策）
            // viewBox="0 0 576 512"だが、実際のパスはY=528まで使用しているため、切れてしまう
            if ($icon === 'file-pdf' && str_contains($newSvgTag, 'viewBox="0 0 576 512"')) {
                $newSvgTag = str_replace('viewBox="0 0 576 512"', 'viewBox="0 0 576 560"', $newSvgTag);
            }

            $svgContent = str_replace($svgTag, $newSvgTag, $svgContent);
        }

        return response($svgContent, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=31536000', // 1年間キャッシュ
        ]);
    }

    /**
     * MIMEタイプに基づいて適切なアイコンを配信する
     */
    public function serveIconByMime(Request $request): BinaryFileResponse|Response
    {
        $mimeType = $request->query('type');

        // MIMEタイプからアイコン名を決定する
        $iconName = $this->getIconNameForMimeType($mimeType);

        // 既存のserveIconメソッドを呼び出してファイルを配信
        return $this->serveIcon('solid', $iconName);
    }

    /**
     * MIMEタイプからFont Awesomeのアイコン名を決定するヘルパーメソッド
     */
    private function getIconNameForMimeType(?string $mimeType): string
    {
        if (! $mimeType) {
            return 'file';
        }

        // AttachedFileDownloadControllerのロジックを参考に実装
        switch (true) {
            case $mimeType === 'application/pdf':
                return 'file-pdf';
            case $mimeType === 'application/zip':
            case $mimeType === 'application/x-zip-compressed':
                return 'file-zipper';
            case $mimeType === 'application/msword':
            case str_contains($mimeType, 'wordprocessingml'):
                return 'file-word';
            case $mimeType === 'application/vnd.ms-excel':
            case str_contains($mimeType, 'spreadsheetml'):
                return 'file-excel';
            case str_contains($mimeType, 'presentationml'):
                return 'file-powerpoint';
            case $mimeType === 'text/plain':
                return 'file-lines';
            case $mimeType === 'text/html':
            case $mimeType === 'text/css':
            case $mimeType === 'application/javascript':
            case $mimeType === 'application/json':
            case $mimeType === 'application/xml':
                return 'file-code';
            case $mimeType === 'text/csv':
                return 'file-csv';
            case str_starts_with($mimeType, 'audio/'):
                return 'file-audio';
            case str_starts_with($mimeType, 'video/'):
                return 'file-video';
            case str_starts_with($mimeType, 'image/'):
                // 画像の場合はプレビューが表示されるべきだが、フォールバックとしてアイコンを返す
                return 'file-image';
            default:
                return 'file';
        }
    }
}
