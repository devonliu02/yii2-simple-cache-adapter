<?php

namespace devonliu\cache;

use yii\base\Exception;

class InvalidArgumentException extends Exception implements \Psr\SimpleCache\InvalidArgumentException
{

}
