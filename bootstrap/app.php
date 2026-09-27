<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Railway (como cualquier PaaS) recibe las peticiones por HTTPS en su
        // borde y se las manda al contenedor por HTTP simple. Sin esto,
        // Laravel no detecta que la conexión original era HTTPS y genera
        // todas las URLs (CSS, JS, links) con http:// - el navegador las
        // bloquea por contenido mixto y la página se ve sin estilos.
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'login/cambiar-pin',
        ]);
        
        $middleware->validateCsrfTokens(except: [
            'login/cambiar-pin',
            'login/olvide-pin',
            'login/restablecer-pin',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
    