<?php

namespace devonliu\cache;

use DateInterval;
use DateTime;
use Yii;
use yii\base\Component;
use Psr\SimpleCache\CacheInterface;
use yii\caching\Cache;
use yii\di\Instance;

class SimpleCacheAdapter extends Component implements CacheInterface
{
    const INVALID_KEY_CHARACTER = '{}()/\@:';

    /**
     * @var Cache
     */
    public $cache;

    public function init()
    {
        parent::init();

        if (! $this->cache) {
            $this->cache = Yii::$app->cache;
        } else {
            $this->cache = Instance::ensure($this->cache, Cache::class);
        }

    }

    protected function getCache()
    {
        return $this->cache;
    }

    /**
     * Cache::get() return false if the value is not in the cache or expired, but PSR-16 return $default(null)
     *
     * @param string $key
     * @param null $default
     * @return bool|mixed|null
     */
    public function get($key, $default = null)
    {
        $this->assertValidKey($key);

        $data = $this->getCache()->get($key);

        if ($data === false) {
            return $default;
        } else if ($data === null) {
            return false;
        } else {
            return $data;
        }
    }

    public function set($key, $value, $ttl = null)
    {
        $this->assertValidKey($key);

        if (($duration = $this->toSeconds($ttl)) === false) {
            return $this->delete($key);
        }

        if ($value === null) {
            return $this->delete($key);
        }

        // case FALSE to null so we can detect that if
        // the cache miss/expired or it did set the FALSE value into cache
        $value = $value == false ? null : $value;
        return $this->getCache()->set($key, $value, $duration);
    }

    public function delete($key)
    {
        $this->assertValidKey($key);

        return $this->has($key) ? $this->getCache()->delete($key) : true;
    }

    public function clear()
    {
        return $this->getCache()->flush();
    }

    public function getMultiple($keys, $default = null)
    {
        if (! $keys instanceof \Traversable && ! is_array($keys)) {
            throw new InvalidArgumentException(
                'Invalid keys: ' . var_export($keys, true) . '. Keys should be an array or Traversable of strings.'
            );
        }

        $data = [];
        foreach ($keys as $key) {
            $data[$key] = $this->get($key, $default);
        }

        return $data;
    }

    public function setMultiple($values, $ttl = null)
    {
        if (! $values instanceof \Traversable && ! is_array($values)) {
            throw new InvalidArgumentException(
                'Invalid keys: ' . var_export($values, true) . '. Keys should be an array or Traversable of strings.'
            );
        }

        $pairs = [];
        foreach ($values as $key => $value) {
            $this->assertValidKey($key);
            $pairs[$key] = $value;
        }

        $res = true;
        foreach ($pairs as $key => $value) {
            $res = $res && $this->set($key, $value, $ttl);
        }

        return $res;
    }

    public function deleteMultiple($keys)
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } else if (! is_array($keys)) {
            throw new InvalidArgumentException(
                'Invalid keys: ' . var_export($keys, true) . '. Keys should be an array or Traversable of strings.'
            );
        }

        $res = true;
        array_map(function ($key) use (&$res) {
            $res = $res && $this->delete($key);
        }, $keys);

        return $res;
    }

    public function has($key)
    {
        $this->assertValidKey($key);

        return $this->getCache()->exists($key);
    }

    protected function assertValidKey($key)
    {
        if (! is_string($key)) {
            throw new InvalidArgumentException('Invalid key: ' . var_export($key, true) . '. Key should be a string.');
        }

        if ($key === '') {
            throw new InvalidArgumentException('Invalid key. Key should not be empty.');
        }

        // valid key according to PSR-16 rules
        $invalid = preg_quote(static::INVALID_KEY_CHARACTER, '/');
        if (preg_match('/[' . $invalid . ']/', $key)) {
            throw new InvalidArgumentException(
                'Invalid key: ' . $key . '. Contains (a) character(s) reserved ' .
                'for future extension: ' . static::INVALID_KEY_CHARACTER
            );
        }
    }

    protected function assertValidTtl($ttl)
    {
        if ($ttl !== null && ! is_int($ttl) && ! $ttl instanceof DateInterval) {
            $error = 'Invalid time: ' . serialize($ttl) . '. Must be integer or instance of DateInterval.';
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * @param $ttl
     * @return false|int
     */
    protected function toSeconds($ttl)
    {
        $this->assertValidTtl($ttl);

        if ($ttl === null) {
            // 0 means forever in Yii 2 cache
            return 0;
        }

        if (is_int($ttl)) {
            $sec = $ttl;
        } else {
            $sec = ((new DateTime())->add($ttl))->getTimestamp() - (new DateTime())->getTimestamp();
        }

        return $sec > 0 ? $sec : false;
    }
}
