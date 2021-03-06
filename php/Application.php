<?php
namespace Rarst\ReleaseBelt;

use League\Fractal\Manager;
use Mustache\Silex\Application\MustacheTrait;
use Mustache\Silex\Provider\MustacheServiceProvider;
use Rarst\ReleaseBelt\Fractal\PackageSerializer;
use Rarst\ReleaseBelt\Fractal\ReleaseTransformer;
use Silex\Provider\SecurityServiceProvider;
use Symfony\Component\Finder\Finder;

class Application extends \Silex\Application
{
    use MustacheTrait;

    public function __construct(array $values = [ ])
    {
        parent::__construct();

        $app = $this;

        $app->register(new MustacheServiceProvider, [
            'mustache.path'    => __DIR__ . '/../mustache',
        ]);

        $app['http.users'] = [];

        $this['release.dir'] = __DIR__ . '/../releases';

        $this['finder'] = function () {

            $finder = new Finder();
            return $finder->files()->in($this['release.dir']);
        };

        $this['parser'] = function () use ($app) {

            return new ReleaseParser($app['finder']);
        };

        $this['fractal'] = function () {
            $fractal = new Manager();
            $fractal->setSerializer(new PackageSerializer());

            return $fractal;
        };

        $this['transformer'] = function () use ($app) {
            $transformer = new ReleaseTransformer();
            $transformer->setUrlGenerator($app['url_generator']);

            return $transformer;
        };

        $this->get('/', 'Rarst\\ReleaseBelt\\Controller::getHtml');

        $this->get('/packages.json', 'Rarst\\ReleaseBelt\\Controller::getJson');

        $this->get('/{vendor}/{file}', 'Rarst\\ReleaseBelt\\Controller::getFile')
            ->bind('file');

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }

        if (! empty($app['http.users'])) {

            $users = [];

            foreach ($app['http.users'] as $login => $hash) {
                $users[$login] = ['ROLE_COMPOSER', $hash];
            }

            $app->register(new SecurityServiceProvider(), [
                'security.firewalls' => [
                    'composer' => [
                        'pattern' => '^.*$',
                        'http'    => true,
                        'users'   => $users,
                    ]
                ]
            ]);
        }
    }
}
