<?php

namespace RedisClient;

use RedisClient\Command\CommandInterface;
use RedisClient\Command\Pipeline;
use RedisClient\Command\Traits\AllCommandsTrait;
use RedisClient\Connection\StreamConnection;
use RedisClient\Exception\ErrorResponseException;
use RedisClient\Protocol\ProtocolInterface;
use RedisClient\Protocol\RedisProtocol;

/**
 * Class RedisClient
 * @package RedisClient
 */
class RedisClient {
    use AllCommandsTrait;

    const CONFIG_SERVER = 'server';
    const CONFIG_TIMEOUT = 'timeout';
    const CONFIG_THROW_REDIS_EXCEPTIONS = 'throw-redis-exceptions';

    /**
     * Default configuration
     * @var array
     */
    protected static $defaultConfig = [
        self::CONFIG_SERVER => 'tcp://127.0.0.1:6379', // or 'unix:///tmp/redis.sock'
        self::CONFIG_TIMEOUT => 0.1, // in seconds
        self::CONFIG_THROW_REDIS_EXCEPTIONS => true,
    ];

    /**
     * @var ProtocolInterface
     */
    protected $Protocol;

    /**
     * @var Pipeline
     */
    protected $Pipeline;

    /**
     * @var array
     */
    protected $config;

    /**
     * @param array|null $config
     */
    public function __construct(array $config = null) {
        $this->config = $config ? array_merge(static::$defaultConfig, $config) : static::$defaultConfig;
    }

    /**
     * @param string|null $param
     * @return mixed|null
     */
    protected function getConfig($param = null) {
        if (!$param) {
            return $this->config;
        }
        return empty($this->config[$param]) ? null : $this->config[$param];
    }

    /**
     * @return ProtocolInterface
     */
    protected function getProtocol() {
        if (!$this->Protocol) {
            $this->Protocol = new RedisProtocol(
                new StreamConnection(
                    $this->getConfig(self::CONFIG_SERVER),
                    $this->getConfig(self::CONFIG_TIMEOUT)
                )
            );
        }
        return $this->Protocol;
    }

    /**
     * @param CommandInterface $Command
     * @return mixed
     */
    protected function returnCommand(CommandInterface $Command) {
        if ($this->Pipeline) {
            $this->Pipeline->addCommand($Command);
            return $this;
        } else {
            return $this->executeCommand($Command);
        }
    }

    /**
     * @param CommandInterface $Command
     * @return mixed|null
     * @throws \Exception
     */
    public function executeCommand(CommandInterface $Command) {
        $response = $this->getProtocol()->send($Command->getStructure());
        if ($response instanceof ErrorResponseException) {
            if ($this->getConfig(self::CONFIG_THROW_REDIS_EXCEPTIONS)) {
                throw $response;
            }
            return $response;
        } else {
            $result = $Command->parseResponse($response);
        }
        return $result;
    }

    /**
     * @param \Closure|null $Closure
     * @return self|bool|mixed
     */
    public function pipeline(\Closure $Closure = null) {
        if ($this->Pipeline) {
            //throw new Error();
        }
        $this->Pipeline = new Pipeline();
        if ($Closure) {
            $Closure($this);
            return $this->executePipeline();
        }
        return $this;
    }

    /**
     * @return bool|mixed
     * @throws \Exception
     */
    public function executePipeline() {
        if (!$Pipeline = $this->Pipeline) {
            return false;
        }
        $this->Pipeline = null;
        $responses = $this->getProtocol()->send($Pipeline->getStructure(), true);
        if ($responses instanceof ErrorResponseException) {
            if ($this->getConfig(self::CONFIG_THROW_REDIS_EXCEPTIONS)) {
                throw $responses;
            }
            return $responses;
        } else {
            return $Pipeline->parseResponse($responses);
        }
    }

}
