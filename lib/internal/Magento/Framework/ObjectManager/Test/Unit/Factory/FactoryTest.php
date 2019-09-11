<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\ObjectManager\Test\Unit\Factory;

use Magento\Framework\ObjectManager\FactoryInterface;
use Magento\Framework\ObjectManager\Config\Config;
use Magento\Framework\ObjectManager\Factory\Dynamic\Developer;
use Magento\Framework\ObjectManager\ObjectManager;

/**
 * Class FactoryTest
 */
class FactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var FactoryInterface
     */
    private $factory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * Setup tests
     */
    protected function setUp()
    {
        $this->config = new Config();
        $this->factory = new Developer($this->config);
        $this->objectManager = new ObjectManager($this->factory, $this->config);
        $this->factory->setObjectManager($this->objectManager);
    }

    /**
     * Test create without args
     */
    public function testCreateNoArgs()
    {
        $this->assertInstanceOf('StdClass', $this->factory->create(\StdClass::class));
    }

    /**
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage Invalid parameter configuration provided for $firstParam argument
     */
    public function testResolveArgumentsException()
    {
        $configMock = $this->createMock(\Magento\Framework\ObjectManager\Config\Config::class);
        $configMock->expects($this->once())->method('getArguments')->will(
            $this->returnValue(
                [
                    'firstParam' => 1,
                ]
            )
        );

        $definitionsMock = $this->createMock(\Magento\Framework\ObjectManager\DefinitionInterface::class);
        $definitionsMock->expects($this->once())->method('getParameters')->will(
            $this->returnValue(
                [
                    [
                        'firstParam',
                        'string',
                        true,
                        'default_val',
                        false
                    ]
                ]
            )
        );

        $this->factory = new Developer(
            $configMock,
            null,
            $definitionsMock
        );
        $this->objectManager = new ObjectManager($this->factory, $this->config);
        $this->factory->setObjectManager($this->objectManager);
        $this->factory->create(
            \Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\OneScalar::class,
            ['foo' => 'bar']
        );
    }

    /**
     * Test create with one arg
     */
    public function testCreateOneArg()
    {
        /**
         * @var \Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\OneScalar $result
         */
        $result = $this->factory->create(
            \Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\OneScalar::class,
            ['foo' => 'bar']
        );
        $this->assertInstanceOf(\Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\OneScalar::class, $result);
        $this->assertEquals('bar', $result->getFoo());
    }

    /**
     * Test create with injectable
     */
    public function testCreateWithInjectable()
    {
        // let's imitate that One is injectable by providing DI configuration for it
        $this->config->extend(
            [
                \Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\OneScalar::class => [
                    'arguments' => ['foo' => 'bar'],
                ],
            ]
        );
        /**
         * @var \Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\Two $result
         */
        $result = $this->factory->create(\Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\Two::class);
        $this->assertInstanceOf(\Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\Two::class, $result);
        $this->assertInstanceOf(
            \Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\OneScalar::class,
            $result->getOne()
        );
        $this->assertEquals('bar', $result->getOne()->getFoo());
        $this->assertEquals('optional', $result->getBaz());
    }

    /**
     * @param        string $startingClass
     * @param        string $terminationClass
     * @dataProvider circularDataProvider
     */
    public function testCircular($startingClass, $terminationClass)
    {
        $this->expectException('\LogicException');
        $this->expectExceptionMessage(
            sprintf('Circular dependency: %s depends on %s and vice versa.', $startingClass, $terminationClass)
        );
        $this->factory->create($startingClass);
    }

    /**
     * @return array
     */
    public function circularDataProvider()
    {
        $prefix = 'Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\\';
        return [
            ["{$prefix}CircularOne", "{$prefix}CircularThree"],
            ["{$prefix}CircularTwo", "{$prefix}CircularOne"],
            ["{$prefix}CircularThree", "{$prefix}CircularTwo"]
        ];
    }

    /**
     * Test create using reflection
     */
    public function testCreateUsingReflection()
    {
        $type = \Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\Polymorphous::class;
        $definitions = $this->createMock(\Magento\Framework\ObjectManager\DefinitionInterface::class);
        // should be more than defined in "switch" of create() method
        $definitions->expects($this->once())->method('getParameters')->with($type)->will(
            $this->returnValue(
                [
                    ['one', null, false, null, false],
                    ['two', null, false, null, false],
                    ['three', null, false, null, false],
                    ['four', null, false, null, false],
                    ['five', null, false, null, false],
                    ['six', null, false, null, false],
                    ['seven', null, false, null, false],
                    ['eight', null, false, null, false],
                    ['nine', null, false, null, false],
                    ['ten', null, false, null, false],
                ]
            )
        );
        $factory = new Developer($this->config, null, $definitions);
        $result = $factory->create(
            $type,
            [
                'one' => 1,
                'two' => 2,
                'three' => 3,
                'four' => 4,
                'five' => 5,
                'six' => 6,
                'seven' => 7,
                'eight' => 8,
                'nine' => 9,
                'ten' => 10,
            ]
        );
        $this->assertSame(10, $result->getArg(9));
    }

    /**
     * Test create objects with variadic argument in constructor
     *
     * @param        $createArgs
     * @param        $expectedArg0
     * @param        $expectedArg1
     * @dataProvider testCreateUsingVariadicDataProvider
     */
    public function testCreateUsingVariadic(
        $createArgs,
        $expectedArg0,
        $expectedArg1
    ) {
        $type = \Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\Variadic::class;
        $definitions = $this->createMock(\Magento\Framework\ObjectManager\DefinitionInterface::class);

        $definitions->expects($this->once())->method('getParameters')->with($type)->will(
            $this->returnValue(
                [
                    [
                'oneScalars',
                \Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\OneScalar::class,
                false,
                [],
                true
                    ],
                ]
            )
        );
        $factory = new Developer($this->config, null, $definitions);

        /**
         * @var \Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\Variadic $variadic
         */
        $variadic = is_null($createArgs)
            ? $factory->create($type)
            : $factory->create($type, $createArgs);

        $this->assertSame($expectedArg0, $variadic->getOneScalarByKey(0));
        $this->assertSame($expectedArg1, $variadic->getOneScalarByKey(1));
    }

    /**
     * @return array
     */
    public function testCreateUsingVariadicDataProvider()
    {
        $oneScalar1 = $this->createMock(\Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\OneScalar::class);
        $oneScalar2 = $this->createMock(\Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\OneScalar::class);

        return [
            'without_args'    => [
                null,
                null,
                null,
            ],
            'with_empty_args' => [
                [],
                null,
                null,
            ],
            'with_empty_args_value' => [
                [
                    'oneScalars' => []
                ],
                null,
                null,
            ],
            'with_args' => [
                [
                    'oneScalars' => [
                        $oneScalar1,
                        $oneScalar2,
                    ]
                ],
                $oneScalar1,
                $oneScalar2,
            ],
        ];
    }

    /**
     * Test data can be injected into variadic arguments from di config
     */
    public function testCreateVariadicFromDiConfig()
    {
        $oneScalar1 = $this->createMock(\Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\OneScalar::class);
        $oneScalar2 = $this->createMock(\Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\OneScalar::class);

        // let's imitate that Variadic is configured by providing DI configuration for it
        $this->config->extend(
            [
                \Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\Variadic::class => [
                    'arguments' => [
                        'oneScalars' => [
                            $oneScalar1,
                            $oneScalar2,
                        ]
                    ]
                ],
            ]
        );
        /**
         * @var \Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\Variadic $variadic
         */
        $variadic = $this->factory->create(\Magento\Framework\ObjectManager\Test\Unit\Factory\Fixture\Variadic::class);

        $this->assertSame($oneScalar1, $variadic->getOneScalarByKey(0));
        $this->assertSame($oneScalar2, $variadic->getOneScalarByKey(1));
    }
}
