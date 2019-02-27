<?php

namespace Oro\Component\Config\Tests\Unit\Cache;

use Oro\Component\Config\ResourcesContainerInterface;
use Oro\Component\Config\Tests\Unit\Fixtures\PhpArrayConfigProviderStub;
use Oro\Component\Testing\TempDirExtension;
use Symfony\Component\Config\Tests\Resource\ResourceStub;

class PhpConfigProviderTest extends \PHPUnit\Framework\TestCase
{
    use TempDirExtension;

    /** @var string */
    private $cacheFile;

    protected function setUp()
    {
        $this->cacheFile = $this->getTempFile('PhpConfigProvider');
        self::assertFileNotExists($this->cacheFile);
    }

    /**
     * @param mixed $config
     * @param bool  $debug
     *
     * @return PhpArrayConfigProviderStub
     */
    private function getProvider($config, $debug = false): PhpArrayConfigProviderStub
    {
        return new PhpArrayConfigProviderStub(
            $this->cacheFile,
            $debug,
            function (ResourcesContainerInterface $resourcesContainer) use ($config) {
                return $config;
            }
        );
    }

    public function testIsCacheChangeableForProductionMode()
    {
        $provider = $this->getProvider(['test']);

        self::assertFalse($provider->isCacheChangeable());
    }

    public function testIsCacheChangeableForDevelopmentMode()
    {
        $provider = $this->getProvider(['test'], true);

        self::assertTrue($provider->isCacheChangeable());
    }

    public function testIsCacheFreshWhenNoCachedData()
    {
        $config = ['test'];

        $provider = $this->getProvider($config);

        $timestamp = time() - 1;
        self::assertFalse($provider->isCacheFresh($timestamp));
    }

    public function testIsCacheFreshWhenCachedDataExist()
    {
        file_put_contents($this->cacheFile, \sprintf('<?php return %s;', \var_export(['test'], true)));

        $provider = $this->getProvider(['initial']);

        $cacheTimestamp = filemtime($this->cacheFile);
        self::assertTrue($provider->isCacheFresh($cacheTimestamp));
        self::assertTrue($provider->isCacheFresh($cacheTimestamp + 1));
        self::assertFalse($provider->isCacheFresh($cacheTimestamp - 1));
    }

    public function testIsCacheFreshWhenCachedDataExistForDevelopmentModeWhenCacheIsFresh()
    {
        file_put_contents($this->cacheFile, \sprintf('<?php return %s;', \var_export(['test'], true)));
        $resource = new ResourceStub();
        file_put_contents($this->cacheFile . '.meta', serialize([$resource]));

        $provider = $this->getProvider(['initial'], true);

        $cacheTimestamp = filemtime($this->cacheFile);
        self::assertTrue($provider->isCacheFresh($cacheTimestamp));
        self::assertTrue($provider->isCacheFresh($cacheTimestamp + 1));
        self::assertFalse($provider->isCacheFresh($cacheTimestamp - 1));
    }

    public function testIsCacheFreshWhenCachedDataExistForDevelopmentModeWhenCacheIsDirty()
    {
        file_put_contents($this->cacheFile, \sprintf('<?php return %s;', \var_export(['test'], true)));
        $resource = new ResourceStub();
        $resource->setFresh(false);
        file_put_contents($this->cacheFile . '.meta', serialize([$resource]));

        $provider = $this->getProvider(['initial'], true);

        $cacheTimestamp = filemtime($this->cacheFile);
        self::assertFalse($provider->isCacheFresh($cacheTimestamp));
        self::assertFalse($provider->isCacheFresh($cacheTimestamp + 1));
        self::assertFalse($provider->isCacheFresh($cacheTimestamp - 1));
    }

    public function testGetCacheTimestampWhenNoCachedData()
    {
        $provider = $this->getProvider(['initial']);

        self::assertNull($provider->getCacheTimestamp());
    }

    public function testGetCacheTimestampWhenCachedDataExist()
    {
        file_put_contents($this->cacheFile, \sprintf('<?php return %s;', \var_export(['test'], true)));

        $provider = $this->getProvider(['initial']);

        self::assertEquals(filemtime($this->cacheFile), $provider->getCacheTimestamp());
    }

    public function testGetConfigWhenNoCachedData()
    {
        $config = ['test'];

        $provider = $this->getProvider($config);

        self::assertEquals($config, $provider->getConfig());
    }

    public function testGetConfigWhenCachedDataExist()
    {
        $cachedConfig = ['test'];
        $initialConfig = ['initial'];

        file_put_contents($this->cacheFile, \sprintf('<?php return %s;', \var_export($cachedConfig, true)));

        $provider = $this->getProvider($initialConfig);

        self::assertEquals($cachedConfig, $provider->getConfig());
    }

    public function testClearCache()
    {
        $cachedConfig = ['test'];
        $initialConfig = ['initial'];

        file_put_contents($this->cacheFile, \sprintf('<?php return %s;', \var_export($cachedConfig, true)));

        $provider = $this->getProvider($initialConfig);

        $provider->clearCache();
        self::assertAttributeSame(null, 'config', $provider);
        self::assertFileNotExists($this->cacheFile);

        // test that the cache is built after it was cleared
        self::assertEquals($initialConfig, $provider->getConfig());
    }

    public function testWarmUpCache()
    {
        $cachedConfig = ['test'];
        $initialConfig = ['initial'];

        file_put_contents($this->cacheFile, \sprintf('<?php return %s;', \var_export($cachedConfig, true)));

        $provider = $this->getProvider($initialConfig);

        $provider->warmUpCache();
        self::assertEquals($initialConfig, $provider->getConfig());
    }

    public function testEnsureCacheWarmedUpWhenCachedDataExist()
    {
        $cachedConfig = ['test'];
        $initialConfig = ['initial'];

        file_put_contents($this->cacheFile, \sprintf('<?php return %s;', \var_export($cachedConfig, true)));

        $provider = $this->getProvider($initialConfig);

        $provider->ensureCacheWarmedUp();
        self::assertEquals($cachedConfig, $provider->getConfig());
    }

    public function testEnsureCacheWarmedUpWhenCachedDataExistForDevelopmentModeWhenCacheIsFresh()
    {
        $cachedConfig = ['test'];
        $initialConfig = ['initial'];

        file_put_contents($this->cacheFile, \sprintf('<?php return %s;', \var_export($cachedConfig, true)));
        $resource = new ResourceStub();
        file_put_contents($this->cacheFile . '.meta', serialize([$resource]));

        $provider = $this->getProvider($initialConfig, true);

        $provider->ensureCacheWarmedUp();
        self::assertEquals($cachedConfig, $provider->getConfig());
    }

    public function testEnsureCacheWarmedUpWhenCachedDataExistForDevelopmentModeWhenCacheIsDirty()
    {
        $cachedConfig = ['test'];
        $initialConfig = ['initial'];

        file_put_contents($this->cacheFile, \sprintf('<?php return %s;', \var_export($cachedConfig, true)));
        $resource = new ResourceStub();
        $resource->setFresh(false);
        file_put_contents($this->cacheFile . '.meta', serialize([$resource]));

        $provider = $this->getProvider($initialConfig, true);

        $provider->ensureCacheWarmedUp();
        self::assertEquals($initialConfig, $provider->getConfig());
    }

    public function testInvalidInitialConfig()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The config "%s" is not valid. Expected an array.',
            $this->cacheFile
        ));

        $provider = $this->getProvider('invalid');
        $provider->getConfig();
    }
}
