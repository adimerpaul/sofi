<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // La encuesta pública puede quedar abierta horas en el celular del
        // cliente: si el token CSRF expira, en vez de la pantalla 419 lo
        // devolvemos al formulario (recargado desde la BD) con un aviso.
        // (Laravel convierte TokenMismatchException en un HttpException 419
        // antes de llegar aquí, por eso se filtra por código de estado.)
        $this->renderable(function (HttpExceptionInterface $e, $request) {
            if ($e->getStatusCode() === 419 && $request->is('encuesta/*')) {
                return redirect()->to($request->url())
                    ->withInput($request->except('_token'))
                    ->with('error', 'La página estuvo abierta demasiado tiempo. Revisa tu respuesta y envíala de nuevo.');
            }
        });
    }
}
