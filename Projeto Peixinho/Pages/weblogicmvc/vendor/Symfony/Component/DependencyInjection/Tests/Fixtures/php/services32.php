<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * ProjectServiceContainer.
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
 */
class ProjectServiceContainer extends Container
{
    private $parameters;
    private $targetDirs = array();

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->services = array();
        $this->normalizedIds = array(
            'psr\\container\\containerinterface' => 'Psr\\Container\\ContainerInterface',
            'symfony\\component\\dependencyinjection\\containerinterface' => 'Symfony\\Component\\DependencyInjection\\ContainerInterface',
        );
        $this->methodMap = array(
            'bar' => 'getBarService',
            'foo' => 'getFooService',
        );

        $this->aliases = array();
    }

    /**
     * {@inheritdoc}
     */
    public function compile()
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    /**
     * {@inheritdoc}
     */
    public function isCompiled()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isFrozen()
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 3.3 and will be removed in 4.0. Use the isCompiled() method instead.', __METHOD__), E_USER_DEPRECATED);

        return true;
    }

    /**
     * Gets the 'bar' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \stdClass A stdClass instance
     */
    protected function getBarService()
    {
        $this->services['bar'] = $instance = new \stdClass();

        $instance->foo = array(0 => /** @closure-proxy Symfony\Component\DependencyInjection\Tests\Fixtures\Container32\Foo::withVariadic */ function ($a, &...$c) {
            return ${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->get('foo')) && false ?: '_'}->withVariadic($a, ...$c);
        }, 1 => /** @closure-proxy Symfony\Component\DependencyInjection\Tests\Fixtures\Container32\Foo::withNullable */ function (?int $a) {
            return ${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->get('foo')) && false ?: '_'}->withNullable($a);
        }, 2 => /** @closure-proxy Symfony\Component\DependencyInjection\Tests\Fixtures\Container32\Foo::withReturnType */ function (): \Bar {
            return ${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->get('foo')) && false ?: '_'}->withReturnType();
        });

        return $instance;
    }

    /**
     * Gets the 'foo' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\Container32\Foo A Symfony\Component\DependencyInjection\Tests\Fixtures\Container32\Foo instance
     */
    protected function getFooService()
    {
        return $this->services['foo'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\Container32\Foo();
    }
}
