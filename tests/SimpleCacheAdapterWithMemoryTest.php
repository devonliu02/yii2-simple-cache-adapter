<?php

namespace devonliu\cache\tests;

use Cache\IntegrationTests\SimpleCacheTest;
use devonliu\cache\SimpleCacheAdapter;
use yii\caching\ArrayCache;

class SimpleCacheAdapterWithMemoryTest extends SimpleCacheTest
{
    protected $skippedTests = [
        'testSetMultipleWithIntegerArrayKey' => '',
    ];

    public function createSimpleCache()
    {
        return \Yii::createObject([
            'class' => SimpleCacheAdapter::class,
            'cache' => [
                'class' => ArrayCache::class,
            ],
        ]);
    }
}
