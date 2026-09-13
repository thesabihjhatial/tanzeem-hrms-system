<?php

// Tanzeem HRMS System Scaffold Manager developed and maintained by Sabih

namespace App\Utilities;

class ScaffoldManager
{

    private const APP_SUBDIRECTORIES = ['Models', 'Controllers', 'migrations'];

    public static function createApp(string $projectRoot, string $name): string
    {

        $studly = self::studly($name);
        $base = "{$projectRoot}/apps/{$studly}";

        foreach (self::APP_SUBDIRECTORIES as $dir) {

            mkdir("{$base}/{$dir}", 0755, true);

        }

        mkdir("{$base}/templates/{$name}", 0755, true);
        mkdir("{$base}/static/{$name}", 0755, true);
        file_put_contents("{$base}/routes.php", self::routesStub($name));

        return $studly;

    }

    public static function createMigration(string $projectRoot, string $name, string $app): string
    {

        $studly = self::studly($app);
        $dir = "{$projectRoot}/apps/{$studly}/migrations";

        if (!is_dir($dir)) {

            throw new \RuntimeException("No such app: {$app} (expected {$dir})");

        }

        $className = self::studly($name);
        $timestamp = date('YmdHis');
        $path = "{$dir}/{$timestamp}_" . self::snake($className) . '.php';
        file_put_contents($path, self::migrationStub($className));

        return $path;

    }

    private static function studly(string $value): string
    {

        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));

    }

    private static function snake(string $value): string
    {

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));

    }

    private static function routesStub(string $name): string
    {

        return <<<PHP
        <?php

        use App\\Core\\Router;

        return function (Router \$router): void {

            // \$router->get('/{$name}', ...);
        
        };

        PHP;

    }

    private static function migrationStub(string $className): string
    {

        return <<<PHP
        <?php

        use Phinx\\Migration\\AbstractMigration;

        class {$className} extends AbstractMigration
        {

            public function change(): void
            {

                // \$table = \$this->table('table_name');
                // \$table->addColumn('column_name', 'string')->create();

            }

        }

        PHP;

    }

}
