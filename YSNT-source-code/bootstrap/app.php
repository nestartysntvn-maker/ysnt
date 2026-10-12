<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestForgery(except: ['webhooks/*']);
        $middleware->encryptCookies(except: ['_fbp', '_fbc']);   // set by the Meta pixel, read for Conversions API
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // old links (e.g. ysnt.com.vn URLs after the domain moves here) -> "Chuyển hướng link cũ"
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if (! $request->isMethod('GET')) return null;
            try {
                $hit = \App\Models\Redirect::map()[\App\Models\Redirect::normalize($request->getPathInfo())] ?? null;
                if (! $hit) return null;
                \App\Models\Redirect::whereKey($hit[2])->update(['hits' => \Illuminate\Support\Facades\DB::raw('hits + 1'), 'last_hit_at' => now()]);
                return redirect($hit[0], $hit[1]);
            } catch (\Throwable) { return null; }
        });
    })->create();
