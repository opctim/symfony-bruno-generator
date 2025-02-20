# Symfony Bruno Generator Bundle

This bundle provides a simple command that will generate .bru files based on your registered routes using `opctim/bruno-lang`.
Its usage is very similar to the commands of `symfony/maker-bundle`.

## Requirements

- PHP >= 8.1
- Symfony >= 5.4

## Installation

```shell
composer require --dev opctim/symfony-bruno-generator
```

## Usage

    bin/console make:bruno

The command will guide you through the process:

    How do you want to call your bruno collection? [my_collection]:
    > demo
    
    What is your application base url? [https://localhost]:
    >
    
    
    [OK] Created bruno collection "demo" at /var/www/html/bruno
    
    
    ! [NOTE] If you're finished, just terminate the command with Ctrl+C
    
    
    App\Controller\ApiController
    ----------------------------
    
    Do you want to generate 6 requests for the App\Controller\ApiController controller? (yes/no) [yes]:
    >
    
Just follow along, and you'll have a basic bruno collection at your project root, which you can open & modify using your Bruno App!

Happy requesting! 

## Tests

Tests are located inside the `tests/` folder and can be run with `vendor/bin/phpunit`:

```shell
composer install

vendor/bin/phpunit       
```
