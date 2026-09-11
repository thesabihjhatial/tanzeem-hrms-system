<?php

namespace App\Core;

use App\Utilities\FlashManager;

abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = []): Response
    {
        $data['flash_messages'] ??= FlashManager::consume();

        return Response::html(View::render($template, $data));
    }

    /** @param array<string, mixed> $data */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function notFound(): Response
    {
        return Response::html('Not Found', 404);
    }

    protected function redirect(string $location): Response
    {
        return new Response('', 302, ['Location' => $location]);
    }
}
