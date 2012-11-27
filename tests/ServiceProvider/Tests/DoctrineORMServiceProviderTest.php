<?php
namespace ServiceProvider\Tests;

use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use FF\ServiceProvider\DoctrineORMServiceProvider;

class DoctrineORMServiceProviderTest extends \PHPUnit_Framework_TestCase
{
	public function testProvider()
	{
		$app = $this->getApp();
		$app->boot();
		$this->assertTrue(isset($app['dbs.orm.em.default']));
	}

	protected function getApp()
	{
		$app = new Application();

		// Doctrine DBAL
		$app->register(new DoctrineServiceProvider(), array(
			'dbs.options'      => array(
				'default' => array(
					'driver' => 'pdo_pgsql',
					'host'   => null,
					'dbname' => 'ff_silex_test',
					'user'   => 'postgres',
				)
			),
			'db.mapping_types' => array(//'tsvector'        => 'Entora\Node\Dbal\TsvectorType',
			)
		));

		// Doctrine ORM
		$app['dbs.orm'] = array(
			'default' => array(
				'entities' => array(
					array(
						'type'      => 'annotation',
						'path'      => 'src/Entora/Entity',
						'namespace' => 'Entora\Entity'
					),
				)
			)
		);
		$app->register(new DoctrineORMServiceProvider());

		return $app;
	}
}
