<?php

/**
 * @see       https://github.com/laminas/laminas-mvc for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mvc\Service;

use Laminas\EventManager\EventManager;
use Laminas\Mvc\Controller\PluginManager;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionClass;
use stdClass;

/**
 * @covers \Laminas\Mvc\Service\ServiceManagerConfig
 */
class ServiceManagerConfigTest extends TestCase
{
    /**
     * @var ServiceManagerConfig
     */
    private $config;

    /**
     * @var ServiceManager
     */
    private $services;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $this->config   = new ServiceManagerConfig();
        $this->services = new ServiceManager();
        $this->config->configureServiceManager($this->services);
    }

    /**
     * Is this a v2 service manager?
     *
     * @return bool
     */
    public function isV2ServiceManager()
    {
        return (! method_exists($this->services, 'configure'));
    }

    /**
     * Create an event manager instance based on laminas-eventmanager version
     *
     * @param null|\Laminas\EventManager\SharedEventManagerInterface
     * @return EventManager
     */
    protected function createEventManager($sharedManager = null)
    {
        $r = new ReflectionClass(EventManager::class);

        if ($r->hasMethod('setSharedManager')) {
            $events = new EventManager();
            $events->setSharedManager($sharedManager ?: $this->services->get('SharedEventManager'));
            return $events;
        }

        return new EventManager($sharedManager ?: $this->services->get('SharedEventManager'));
    }

    /**
     * @group 3786
     */
    public function testEventManagerAwareInterfaceIsNotInjectedIfPresentButSharedManagerIs()
    {
        $events = $this->createEventManager();
        TestAsset\EventManagerAwareObject::$defaultEvents = $events;

        $this->services->setAlias('EventManagerAwareObject', TestAsset\EventManagerAwareObject::class);
        $this->services->setFactory(TestAsset\EventManagerAwareObject::class, InvokableFactory::class);

        $instance = $this->services->get('EventManagerAwareObject');
        $this->assertInstanceOf(TestAsset\EventManagerAwareObject::class, $instance);
        $this->assertSame($events, $instance->getEventManager());
        $this->assertSame($this->services->get('SharedEventManager'), $events->getSharedManager());
    }

    /**
     * @group 6266
     */
    public function testCanMergeCustomConfigWithDefaultConfig()
    {
        $custom = [
            'invokables' => [
                'foo' => stdClass::class,
            ],
            'factories' => [
                'bar' => function () {
                    return new stdClass();
                },
            ],
        ];

        $sm = new ServiceManager();
        (new ServiceManagerConfig($custom))->configureServiceManager($sm);

        $this->assertTrue($sm->has('foo'));
        $this->assertTrue($sm->has('bar'));
        $this->assertTrue($sm->has('ModuleManager'));
    }

    /**
     * @group 6266
     */
    public function testCanOverrideDefaultConfigWithCustomConfig()
    {
        $custom = [
            'invokables' => [
                'foo' => stdClass::class,
            ],
            'factories' => [
                'ModuleManager' => function () {
                    return new stdClass();
                },
            ],
        ];

        $sm = new ServiceManager();
        (new ServiceManagerConfig($custom))->configureServiceManager($sm);

        $this->assertTrue($sm->has('foo'));
        $this->assertTrue($sm->has('ModuleManager'));

        $this->assertInstanceOf(stdClass::class, $sm->get('ModuleManager'));
    }

    /**
     * @group 6266
     */
    public function testCanAddDelegators()
    {
        /*
         * Create delegator closure
         *
         * The signature for delegators differs between laminas-servicemanager
         * v2 and v3, so we must vary the closure used based on the version
         * being used when testing.
         */
        if (method_exists($this->services, 'configure')) {
            // v3
            $delegator = function ($container, $name, $callback, array $options = null) {
                $service = $callback();
                $service->bar = 'baz';
                return $service;
            };
        } else {
            // v2
            $delegator = function ($container, $name, $requestedName, $callback) {
                $service = $callback();
                $service->bar = 'baz';
                return $service;
            };
        }

        $config = [
            'aliases' => [
                'foo' => stdClass::class,
            ],
            'factories' => [
                stdClass::class => InvokableFactory::class,
            ],
            'delegators' => [
                stdClass::class => [ $delegator ],
            ],
        ];

        $sm = new ServiceManager();
        (new ServiceManagerConfig($config))->configureServiceManager($sm);

        $std = $sm->get('foo');
        $this->assertInstanceOf(stdClass::class, $std);
        $this->assertEquals('baz', $std->bar);
    }

    /**
     * @group 6266
     */
    public function testEventManagerInitializerCanBeReplaced()
    {
        $instance       = $this->getMock('Laminas\EventManager\EventManagerAwareInterface');
        $initializer    = $this->getMock(stdClass::class, ['__invoke']);
        $config         = new ServiceManagerConfig([
            'initializers' => [
                'EventManagerAwareInitializer' => $initializer,
            ],
            'factories' => [
                'EventManagerAware' => function () use ($instance) {
                    return $instance;
                },
            ],
        ]);
        $serviceManager = new ServiceManager();
        $config->configureServiceManager($serviceManager);

        /*
         * Need to vary the order of arguments the initializer receives based on
         * which laminas-servicemanager version is being tested against.
         */
        if (method_exists($this->services, 'configure')) {
            // v3
            $initializer->expects($this->once())->method('__invoke')->with($serviceManager, $instance);
        } else {
            // v2
            $initializer->expects($this->once())->method('__invoke')->with($instance, $serviceManager);
        }

        $instance->expects($this->never())->method('getEventManager');
        $instance->expects($this->never())->method('setEventManager');

        $serviceManager->get('EventManagerAware');
    }

    /**
     * @group 101
     */
    public function testCreatesAFactoryForTheServiceManagerThatReturnsIt()
    {
        $serviceManager = new ServiceManager();
        $config         = new ServiceManagerConfig();
        $config->configureServiceManager($serviceManager);

        $this->assertTrue($serviceManager->has('ServiceManager'), 'Missing ServiceManager service!');
        $this->assertSame($serviceManager, $serviceManager->get('ServiceManager'));
    }

    /**
     * @see https://github.com/zendframework/zend-servicemanager/issues/109
     */
    public function testServiceLocatorAwareInitializerCanInjectPluginManagers()
    {
        if (! $this->isV2ServiceManager()) {
            $this->markTestSkipped(sprintf(
                '%s verifies backwards compatibility with the v2 series of laminas-servicemanager',
                __FUNCTION__
            ));
        }

        $this->services->setFactory('test-plugins', function () {
            return new PluginManager();
        });

        $deprecated = false;
        set_error_handler(function ($errno, $errstr) use (&$deprecated) {
            $deprecated = true;
        }, E_USER_DEPRECATED);

        $plugins = $this->services->get('test-plugins');

        restore_error_handler();

        $this->assertSame($this->services, $plugins->getServiceLocator());
        $this->assertTrue($deprecated, 'Deprecation notice for ServiceLocatorAwareInitializer was not triggered');
    }

    /**
     * @see https://github.com/zendframework/zend-servicemanager/issues/109
     */
    public function testServiceLocatorAwareInitializerWillNotReinjectPluginManagers()
    {
        if (! $this->isV2ServiceManager()) {
            $this->markTestSkipped(sprintf(
                '%s verifies backwards compatibility with the v2 series of laminas-servicemanager',
                __FUNCTION__
            ));
        }

        $altServices = new ServiceManager();
        $this->services->setFactory('test-plugins', function () use ($altServices) {
            return new PluginManager($altServices);
        });

        $deprecated = false;
        set_error_handler(function ($errno, $errstr) use (&$deprecated) {
            $deprecated = true;
        }, E_USER_DEPRECATED);

        $plugins = $this->services->get('test-plugins');

        restore_error_handler();

        $this->assertSame($altServices, $plugins->getServiceLocator());
        $this->assertFalse(
            $deprecated,
            'Deprecation notice for ServiceLocatorAwareInitializer was triggered, but should not have been'
        );
    }
}
