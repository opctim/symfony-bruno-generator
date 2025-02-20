<?php

declare(strict_types=1);

namespace Opctim\BrunoGeneratorBundle\Command;

use Exception;
use Opctim\BrunoLang\V1\Block\Entry\DictionaryBlockEntry;
use Opctim\BrunoLang\V1\BruFile;
use Opctim\BrunoLang\V1\Collection;
use Opctim\BrunoLang\V1\Tag\DictionaryBlockTag;
use Opctim\BrunoLang\V1\Tag\Schema\GetTag;
use Opctim\BrunoLang\V1\Tag\Schema\MetaTag;
use Opctim\BrunoLang\V1\Tag\Schema\ParamsPathTag;
use Opctim\BrunoLang\V1\Tag\Schema\VarsTag;
use Opctim\BrunoLang\V1\Tag\TagFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\String\UnicodeString;
use Throwable;

#[AsCommand(name: 'make:bruno', description: 'Generates bruno files according to your controller actions.')]
class MakeBrunoCommand extends Command
{
    private string $collectionDir;

    public function __construct(
        private readonly RouterInterface $router,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
    )
    {
        parent::__construct();

        $this->collectionDir = $this->projectDir . '/bruno';
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $collection = $this->createOrParseCollection($io);

        if (!$collection) {
            return Command::FAILURE;
        }

        $io->note("If you're finished, just terminate the command with Ctrl+C");

        $filesystem = new Filesystem();

        foreach ($this->getControllers() as $controller) {
            $routes = $this->getRoutes($controller);

            if (count($routes) > 0) {
                $io->newLine();
                $io->section($controller);

                $response = $io->confirm('Do you want to generate ' . count($routes) . ' requests for the ' . $controller . ' controller?');

                if ($response) {
                    $generated = [];
                    $controllerDirectory = $this->getControllerDirectory($controller, $io);
                    $requestDirectory = $this->collectionDir . '/' . $controllerDirectory;

                    if (!$filesystem->exists($requestDirectory)) {
                        $filesystem->mkdir($requestDirectory);
                    }

                    foreach ($routes as $routeName => $route) {
                        $url = '{{baseUrl}}' . $this->generateUrl($route);

                        $methods = $route->getMethods();

                        if (empty($methods)) {
                            $io->info('No methods specified for route ' . $routeName . ', defaulting to GET');

                            $methods = ['GET'];
                        }

                        foreach ($methods as $method) {
                            $requestName = $this->toSnakeCase($method . '_' . $routeName);

                            $bruFile = $this->buildBruFile($requestName, $method, $url, $route);

                            // Writing individually
                            $bruFile->write($requestDirectory);

                            $generated[] = $method . ' ' . $url . ' -> ' . 'bruno/' . $controllerDirectory . '/' . $requestName . '.bru';
                        }
                    }

                    if (count($generated) > 0) {
                        $io->success('Generated');
                        $io->listing($generated);
                    } else {
                        $io->warning('No requests generated for controller ' . $controller);
                    }
                } else {
                    $io->info('Skipped controller ' . $controller);
                }
            }
        }

        return Command::SUCCESS;
    }

    protected function createOrParseCollection(SymfonyStyle $io): ?Collection
    {
        if (is_dir($this->collectionDir)) {
            try {
                $collection = Collection::parse($this->collectionDir);

                $io->info('Found bruno collection "' . $collection->getName() . '" at ' . $this->collectionDir);
            } catch (Throwable $e) {
                $io->error('Error reading bruno collection from ' . $this->collectionDir . ' -> ' . $e->getMessage());

                return null;
            }
        } else {
            // No collection present, create one
            try {
                $collectionName = $io->ask('How do you want to call your bruno collection?', 'my_collection');
                $baseUrl = $io->ask('What is your application base url?', 'https://localhost');

                $collection = new Collection(
                    [
                        'version' => '1',
                        'name' => $collectionName,
                        'type' => 'collection',
                        'ignore' => [
                            'node_modules',
                            '.git'
                        ]
                    ],
                    [],
                    [
                        new BruFile('localhost', [
                            new VarsTag([
                                new DictionaryBlockEntry('baseUrl', $baseUrl)
                            ])
                        ])
                    ]
                );

                $collection->write($this->collectionDir);

                $io->success('Created bruno collection "' . $collectionName . '" at ' . $this->collectionDir);
            } catch (Throwable $e) {
                $io->error('Error creating bruno collection at ' . $this->collectionDir . ' -> ' . $e->getMessage());

                return null;
            }
        }

        return $collection;
    }

    /**
     * @throws Exception
     */
    protected function buildBruFile(string $requestName, string $method, string $url, Route $route): BruFile
    {
        $bruFile = new BruFile($requestName);

        $bruFile->addBlock(new MetaTag([
            new DictionaryBlockEntry('name', $requestName),
            new DictionaryBlockEntry('type', 'http')
        ]));

        $methodTag = $this->resolveMethodTag($method);

        $methodTag->addBlockEntry(
            new DictionaryBlockEntry('url', $url)
        );

        if ($methodTag instanceof GetTag) {
            $methodTag->addBlockEntry(
                new DictionaryBlockEntry('body', 'none'),
                new DictionaryBlockEntry('auth', 'inherit')
            );
        }

        $bruFile->addBlock($methodTag);

        $pathParametersDefaults = $this->getPathParametersDefaults($route, $url);

        if (!empty($pathParametersDefaults)) {
            $bruFile->addBlock(
                new ParamsPathTag($pathParametersDefaults)
            );
        }

        return $bruFile;
    }

    /**
     * @throws Exception
     */
    protected function resolveMethodTag(string $method): DictionaryBlockTag
    {
        $tagClass = TagFactory::findByTagName(
            strtolower($method)
        );

        if (!$tagClass || !in_array(DictionaryBlockTag::class, class_parents($tagClass))) {
            throw new Exception('Unable to resolve tag for method ' . $method);
        }

        return new $tagClass([]);
    }

    /**
     * @param Route $route
     * @param string $url
     * @return DictionaryBlockEntry[]
     */
    protected function getPathParametersDefaults(Route $route, string $url): array
    {
        $result = [];

        foreach ($route->getDefaults() as $name => $default) {
            $name = $this->toCamelCase($name);

            if (str_contains($url, ':' . $name) && $default) {
                $result[] = new DictionaryBlockEntry($name, $default);
            }
        }

        return $result;
    }

    protected function getControllers(): array
    {
        $routes = $this->router->getRouteCollection()->all();

        $controllerActions = array_values(
            array_map(fn(Route $route) => $route->getDefault('_controller'), $routes)
        );

        $controllerActions = array_filter(
            $controllerActions,
            fn(string $controllerAction) => preg_match('/^App\\\/', $controllerAction)
        );

        $controllers = array_map(
            fn(string $controllerAction) => preg_replace('/::.*$/', '', $controllerAction),
            $controllerActions
        );

        $controllers = array_values(
            array_unique($controllers)
        );

        sort($controllers);

        return $controllers;
    }

    /**
     * @param string $controller
     * @return Route[]
     */
    protected function getRoutes(string $controller): array
    {
        return array_filter(
            $this->router->getRouteCollection()->all(),
            fn(Route $route) => str_starts_with($route->getDefault('_controller'), $controller)
        );
    }

    protected function generateUrl(Route $route): string
    {
        // Replace placeholders with their values or :param
        return preg_replace_callback('/(?<DOT>\.?)\{(?<NAME>[^}]+)}/', function ($matches) use ($route) {
            $name = $matches['NAME'];

            $defaults = $route->getDefaults();
            $requirements = $route->getRequirements();

            if ($name === '_format') {
                if (!empty($requirements[$name])) {
                    // It is required
                    if ($defaults[$name]) {
                        // Use default value.
                        return $matches['DOT'] . $defaults[$name];
                    } else {
                        // No default value, add parameter
                        return $matches['DOT'] . ':' . $this->toCamelCase($name);
                    }
                }

                // Omit internal param, as it is optional.
                return '';
            }

            return ':' . $this->toCamelCase($name);
        }, $route->getPath());
    }

    protected function toSnakeCase(string $string): string
    {
        return (string)(new UnicodeString($string))->snake();
    }

    protected function toCamelCase(string $string): string
    {
        return (string)(new UnicodeString($string))->camel();
    }

    protected function getControllerDirectory(string $controller, SymfonyStyle $io): string
    {
        $controller = preg_replace('/App\\\/', '', $controller);
        $controller = preg_replace('/Controller\\\/', '', $controller);

        $path = explode('\\', $controller);
        $path = array_map([$this, 'toSnakeCase'], $path);

        if ($path[0] === 'environments') {
            $io->warning('The folder name "environments" at the root of the collection is reserved in bruno. Defaulting to "environments_folder".');

            $path[0] = 'environments_folder';
        }

        return implode('/', $path);
    }
}
