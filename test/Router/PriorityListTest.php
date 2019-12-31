<?php

/**
 * @see       https://github.com/laminas/laminas-mvc for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mvc\Router;

use Laminas\Mvc\Router\PriorityList;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * @category   Laminas
 * @package    Laminas_Mvc_Router
 * @subpackage UnitTests
 * @group      Laminas_Router
 */
class PriorityListTest extends TestCase
{
    /**
     * @var PriorityList
     */
    protected $list;

    public function setUp()
    {
        $this->list = new PriorityList();
    }

    public function testInsert()
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), 0);

        $this->assertEquals(1, count($this->list));

        foreach ($this->list as $key => $value) {
            $this->assertEquals('foo', $key);
        }
    }

    public function testRemove()
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), 0);
        $this->list->insert('bar', new TestAsset\DummyRoute(), 0);

        $this->assertEquals(2, count($this->list));

        $this->list->remove('foo');

        $this->assertEquals(1, count($this->list));
    }

    public function testRemovingNonExistentRouteDoesNotYieldError()
    {
        $this->list->remove('foo');
    }

    public function testClear()
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), 0);
        $this->list->insert('bar', new TestAsset\DummyRoute(), 0);

        $this->assertEquals(2, count($this->list));

        $this->list->clear();

        $this->assertEquals(0, count($this->list));
        $this->assertSame(false, $this->list->current());
    }

    public function testGet()
    {
        $route = new TestAsset\DummyRoute();

        $this->list->insert('foo', $route, 0);

        $this->assertEquals($route, $this->list->get('foo'));
        $this->assertNull($this->list->get('bar'));
    }

    public function testLIFOOnly()
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), 0);
        $this->list->insert('bar', new TestAsset\DummyRoute(), 0);
        $this->list->insert('baz', new TestAsset\DummyRoute(), 0);

        $order = array();

        foreach ($this->list as $key => $value) {
            $orders[] = $key;
        }

        $this->assertEquals(array('baz', 'bar', 'foo'), $orders);
    }

    public function testPriorityOnly()
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), 1);
        $this->list->insert('bar', new TestAsset\DummyRoute(), 0);
        $this->list->insert('baz', new TestAsset\DummyRoute(), 2);

        $order = array();

        foreach ($this->list as $key => $value) {
            $orders[] = $key;
        }

        $this->assertEquals(array('baz', 'foo', 'bar'), $orders);
    }

    public function testLIFOWithPriority()
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), 0);
        $this->list->insert('bar', new TestAsset\DummyRoute(), 0);
        $this->list->insert('baz', new TestAsset\DummyRoute(), 1);

        $order = array();

        foreach ($this->list as $key => $value) {
            $orders[] = $key;
        }

        $this->assertEquals(array('baz', 'bar', 'foo'), $orders);
    }

    public function testPriorityWithNegativesAndNull()
    {
        $this->list->insert('foo', new TestAsset\DummyRoute(), null);
        $this->list->insert('bar', new TestAsset\DummyRoute(), 1);
        $this->list->insert('baz', new TestAsset\DummyRoute(), -1);

        $order = array();

        foreach ($this->list as $key => $value) {
            $orders[] = $key;
        }

        $this->assertEquals(array('bar', 'foo', 'baz'), $orders);
    }
}
