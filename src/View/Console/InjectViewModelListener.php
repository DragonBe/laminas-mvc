<?php

/**
 * @see       https://github.com/laminas/laminas-mvc for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Mvc\View\Console;

use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\Mvc\View\Http\InjectViewModelListener as HttpInjectViewModelListener;

class InjectViewModelListener extends HttpInjectViewModelListener implements ListenerAggregateInterface
{}
