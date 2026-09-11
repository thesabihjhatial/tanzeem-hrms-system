<?php

namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class View
{
    private static ?Environment $twig = null;

    /**
     * @param array<string, string> $templatePaths app-name => absolute path to that app's templates/ dir
     */
    public static function boot(string $sharedTemplatesPath, array $templatePaths): Environment
    {
        if (self::$twig === null) {
            $loader = new FilesystemLoader($sharedTemplatesPath);
            foreach ($templatePaths as $path) {
                $loader->addPath($path);
            }
            self::$twig = new Environment($loader);
        }

        return self::$twig;
    }

    /**
     * @param string $template e.g. "employees/index.twig" or "@employees/index.twig"
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = []): string
    {
        if (self::$twig === null) {
            throw new \RuntimeException('View::boot() must be called before View::render()');
        }

        return self::$twig->render($template, $data);
    }
}
