<?php
/*
 * This file is part of the reva2/jsonapi.
 *
 * (c) OrbitScripts LLC <support@orbitscripts.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Reva2\JsonApi\Tests\Services;

use Neomerx\JsonApi\Contracts\Decoder\DecoderInterface;
use Neomerx\JsonApi\Contracts\Encoder\EncoderInterface;
use Reva2\JsonApi\Services\JsonApiRegistry;

/**
 * Test for JSON API decoders/encoders registry
 *
 * @package Reva2\JsonApi\Tests\Services
 * @author Sergey Revenko <reva2@orbita1.ru>
 */
class JsonApiRegistryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var JsonApiRegistry
     */
    protected $registry;

    /**
     * @test
     */
    public function shouldRegisterDecoder()
    {
        $decoder = $this->getMockBuilder(DecoderInterface::class)->getMock();
        $factory = function () use ($decoder) {
            return $decoder;
        };

        $this->registry->registerDecoder('test', $factory);

        $this->assertSame($decoder, $this->registry->getDecoder('test'));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function shouldThrowOnInvalidDecoderParams()
    {
        $this->registry->registerDecoder('invalid', []);
    }

    /**
     * @test
     * @expectedException \LogicException
     */
    public function shouldThrowOnInvalidDecoder()
    {
        $decoder = false;
        $factory = function () use ($decoder) {
            return $decoder;
        };

        $this->registry->registerDecoder('invalid', $factory)->getDecoder('invalid');
    }

    /**
     * @test
     * @expectedException \RuntimeException
     */
    public function shouldThrowIfDecoderNotRegistered()
    {
        $this->registry->getDecoder('unknown');
    }

    /**
     * @test
     */
    public function shouldRegisterEncoder()
    {
        $encoder = $this->getMockBuilder(EncoderInterface::class)->getMock();
        $factory = function () use ($encoder) {
            return $encoder;
        };

        $this->registry->registerEncoder('test', $factory);

        $this->assertSame($encoder, $this->registry->getEncoder('test'));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function shouldThrowOnInvalidEncoderParams()
    {
        $this->registry->registerEncoder('invalid', []);
    }

    /**
     * @test
     * @expectedException \LogicException
     */
    public function shouldThrowOnInvalidEncoder()
    {
        $encoder = false;
        $factory = function () use ($encoder) {
            return $encoder;
        };

        $this->registry->registerEncoder('invalid', $factory)->getEncoder('invalid');
    }

    /**
     * @test
     * @expectedException \RuntimeException
     */
    public function shouldThrowIfEncoderNotRegistered()
    {
        $this->registry->getEncoder('unknown');
    }

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->registry = new JsonApiRegistry();
    }
}