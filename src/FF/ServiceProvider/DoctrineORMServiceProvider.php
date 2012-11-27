<?php

namespace FF\ServiceProvider;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Configuration as ORMConfiguration;
use Doctrine\ORM\Mapping\Driver\DriverChain;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\ApcCache;
use Doctrine\Common\Cache\ArrayCache;

use Silex\Application;
use Silex\ServiceProviderInterface;

class DoctrineORMServiceProvider implements ServiceProviderInterface
{
	public function register(Application $app)
	{
		$app = $this->setConfigDefaults($app);
		foreach ($app['dbs.orm'] as $dbName => $dbConfig) {
			$this->registerSingle($app, $dbName);
		}

		// Set default db entityManager using first db
		$app['db.orm.em'] = $app->share(function ($app) {
			$dbs = array_keys($app['dbs.orm']);
			return $app['dbs.orm.em.'.$dbs[0]];
		});
	}

	private function setConfigDefaults($app)
	{
		$defaults = array(
			'proxies_dir'           => 'cache/doctrine/Proxy',
			'proxies_namespace'     => 'DoctrineProxy',
			'auto_generate_proxies' => true,
		);
		foreach ($defaults as $key => $value) {
			if (!isset($app['db.orm.'.$key])) {
				$app['db.orm.'.$key] = $value;
			}
		}
		return $app;
	}

	private function registerSingle(Application $app, $dbName)
	{
		$app['dbs.configuration.'.$dbName] = $app->share($this->configClosure($app, $dbName));
		$app['dbs.orm.em.'.$dbName]        = $app->share(function ($app) use ($dbName) {
			$em = EntityManager::create($app['dbs'][$dbName], $app['dbs.configuration.'.$dbName]);

			if (isset($app['db.mapping_types'])) {
				foreach ($app['db.mapping_types'] as $mappingTypeName => $mappingTypeClass) {
					if (!Type::hasType($mappingTypeName)) {
						Type::addType($mappingTypeName, '\\'.$mappingTypeClass);
						$em->getConnection()->getDatabasePlatform()
							->registerDoctrineTypeMapping($mappingTypeName, $mappingTypeName);
					}
				}
			}

			return $em;
		});
	}


	private function configClosure($app, $dbName)
	{
		return function () use ($app, $dbName) {
			$config = new ORMConfiguration;
			$cache  = ($app['debug'] == false) ? new ApcCache : new ArrayCache;
			$config->setMetadataCacheImpl($cache);
			$config->setQueryCacheImpl($cache);

			$chain = new DriverChain;
			foreach ((array)$app['dbs.orm'][$dbName]['entities'] as $entity) {
				switch ($entity['type']) {
					case 'annotation':
						$driver = $config->newDefaultAnnotationDriver((array)$entity['path'], false);
						$chain->addDriver($driver, $entity['namespace']);
						break;
					/*case 'yml':
						  $driver = new YamlDriver((array)$entity['path']);
						  $driver->setFileExtension('.yml');
						  $chain->addDriver($driver, $entity['namespace']);
						  break;
					  case 'xml':
						  $driver = new XmlDriver((array)$entity['path'], $entity['namespace']);
						  $driver->setFileExtension('.xml');
						  $chain->addDriver($driver, $entity['namespace']);
						  break;*/
					default:
						throw new \InvalidArgumentException(sprintf('"%s" is not a recognized driver', $type));
						break;
				}
			}

			$config->setMetadataDriverImpl($chain);

			$config->setProxyDir($app['db.orm.proxies_dir']);
			$config->setProxyNamespace($app['db.orm.proxies_namespace']);
			$config->setAutoGenerateProxyClasses($app['db.orm.auto_generate_proxies']);

			return $config;
		};
	}

	public function boot(Application $app)
	{
	}
}
