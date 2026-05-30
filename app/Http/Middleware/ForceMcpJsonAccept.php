<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCP HTTP エンドポイントで Accept ヘッダーに application/json が含まれていない場合、
 * 強制的に追加するミドルウェア。
 *
 * auth:sanctum などの認証ミドルウェアが 401 エラーを返す際、
 * Laravel の例外ハンドラが JSON レスポンスを返すようにするための対策。
 * Accept ヘッダーがない場合、Laravel は 302 リダイレクトや HTML エラーページを
 * 返してしまい、MCP クライアントがレスポンスを解析できなくなる。
 */
class ForceMcpJsonAccept
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->wantsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
