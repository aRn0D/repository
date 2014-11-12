<?php

/*
 * This file is part of the Puli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webmozart\Puli\Tests\Uri;

use Webmozart\Puli\Resource\Collection\ResourceCollection;
use Webmozart\Puli\Tests\Resource\TestFile;
use Webmozart\Puli\Uri\UriRepository;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UriRepositoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var UriRepository
     */
    private $repo;

    protected function setUp()
    {
        $this->repo = new UriRepository();
    }

    public function testRegisterRepository()
    {
        $repo = $this->getMock('Webmozart\Puli\ResourceRepositoryInterface');

        $this->repo->register('scheme', $repo);

        $repo->expects($this->once())
            ->method('get')
            ->with('/path/to/resource')
            ->will($this->returnValue('RESULT'));

        $this->assertEquals('RESULT', $this->repo->get('scheme:///path/to/resource'));
    }

    public function testRegisterRepositoryFactory()
    {
        $repo = $this->getMock('Webmozart\Puli\ResourceRepositoryInterface');

        $this->repo->register('scheme', function () use ($repo) {
            return $repo;
        });

        $repo->expects($this->once())
            ->method('get')
            ->with('/path/to/resource')
            ->will($this->returnValue('RESULT'));

        $this->assertEquals('RESULT', $this->repo->get('scheme:///path/to/resource'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRegisterExpectsValidRepositoryFactory()
    {
        $this->repo->register('scheme', 'foo');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRegisterExpectsValidScheme()
    {
        $repo = $this->getMock('Webmozart\Puli\ResourceRepositoryInterface');

        $this->repo->register(new \stdClass(), $repo);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRegisterExpectsAlphabeticScheme()
    {
        $repo = $this->getMock('Webmozart\Puli\ResourceRepositoryInterface');

        $this->repo->register('foo1', $repo);
    }

    /**
     * @expectedException \Webmozart\Puli\Uri\RepositoryFactoryException
     */
    public function testRepositoryFactoryMustReturnRepository()
    {
        $repo = $this->getMock('Webmozart\Puli\ResourceRepositoryInterface');

        $this->repo->register('scheme', function () use ($repo) {
            return 'foo';
        });

        $this->repo->get('scheme:///path/to/resource');
        $this->repo->get('scheme:///path/to/resource');
    }

    /**
     * @expectedException \Webmozart\Puli\Uri\SchemeNotSupportedException
     */
    public function testGetExpectsRegisteredScheme()
    {
        $this->repo->get('scheme:///path/to/resource');
    }

    /**
     * @expectedException \Webmozart\Puli\Uri\SchemeNotSupportedException
     */
    public function testGetCantUseUnregisteredScheme()
    {
        $repo = $this->getMock('Webmozart\Puli\ResourceRepositoryInterface');

        $this->repo->register('scheme', $repo);
        $this->repo->unregister('scheme');

        $this->repo->get('scheme:///path/to/resource');
    }

    public function testGetRegisteredSchemes()
    {
        $this->assertEquals(array(), $this->repo->getSupportedSchemes());

        $repo = $this->getMock('Webmozart\Puli\ResourceRepositoryInterface');

        $this->repo->register('resource', $repo);
        $this->assertEquals(array('resource'), $this->repo->getSupportedSchemes());

        $this->repo->register('namespace', $repo);
        $this->assertEquals(array('resource', 'namespace'), $this->repo->getSupportedSchemes());

        $this->repo->unregister('resource');
        $this->assertEquals(array('namespace'), $this->repo->getSupportedSchemes());

        $this->repo->unregister('namespace');
        $this->assertEquals(array(), $this->repo->getSupportedSchemes());
    }

    /**
     * @expectedException \Webmozart\Puli\Uri\InvalidUriException
     */
    public function testGetExpectsValidUri()
    {
        $this->repo->get('foo');
    }

    public function testContains()
    {
        $repo = $this->getMock('Webmozart\Puli\ResourceRepositoryInterface');

        $this->repo->register('scheme', $repo);

        $repo->expects($this->at(0))
            ->method('contains')
            ->with('/path/to/resource-1')
            ->will($this->returnValue(true));
        $repo->expects($this->at(1))
            ->method('contains')
            ->with('/path/to/resource-2')
            ->will($this->returnValue(false));

        $this->assertTrue($this->repo->contains('scheme:///path/to/resource-1'));
        $this->assertFalse($this->repo->contains('scheme:///path/to/resource-2'));
    }

    /**
     * @expectedException \Webmozart\Puli\Uri\InvalidUriException
     */
    public function testContainsExpectsValidUri()
    {
        $this->repo->contains('foo');
    }

    public function testFind()
    {
        $repo = $this->getMock('Webmozart\Puli\ResourceRepositoryInterface');

        $this->repo->register('scheme', $repo);

        $repo->expects($this->once())
            ->method('find')
            ->with('/path/to/res*')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->repo->find('scheme:///path/to/res*'));
    }

    /**
     * @expectedException \Webmozart\Puli\Uri\InvalidUriException
     */
    public function testFindExpectsValidUri()
    {
        $this->repo->find('foo');
    }

    public function testGetByTagChecksAllRepositorys()
    {
        $repo1 = $this->getMock('Webmozart\Puli\ResourceRepositoryInterface');
        $repo2 = $this->getMock('Webmozart\Puli\ResourceRepositoryInterface');

        $this->repo->register('resource', $repo1);
        $this->repo->register('namespace', $repo2);

        $resources = new ResourceCollection(array(
            new TestFile('foo'),
            new TestFile('bar'),
        ));

        $repo1->expects($this->once())
            ->method('getByTag')
            ->with('acme/tag')
            ->will($this->returnValue(new ResourceCollection(array($resources[0]))));
        $repo2->expects($this->once())
            ->method('getByTag')
            ->with('acme/tag')
            ->will($this->returnValue(new ResourceCollection(array($resources[1]))));

        $this->assertEquals(
            $resources,
            $this->repo->getByTag('acme/tag')
        );
    }

    public function testGetTagsReturnsUnionFromAllRepositorys()
    {
        $repo1 = $this->getMock('Webmozart\Puli\ResourceRepositoryInterface');
        $repo2 = $this->getMock('Webmozart\Puli\ResourceRepositoryInterface');

        $this->repo->register('resource', $repo1);
        $this->repo->register('namespace', $repo2);

        $repo1->expects($this->once())
            ->method('getTags')
            ->will($this->returnValue(array('foo')));
        $repo2->expects($this->once())
            ->method('getTags')
            ->will($this->returnValue(array('foo', 'bar')));

        $this->assertEquals(
            array('foo', 'bar'),
            $this->repo->getTags()
        );
    }
}