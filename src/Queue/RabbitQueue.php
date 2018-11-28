<?php

/*
 * This file is part of PHP CS Fixer.
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace LCP\Queue;

use Enqueue\AmqpExt\AmqpConnectionFactory;
use Enqueue\AmqpExt\AmqpContext;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;

class RabbitQueue
{
    private $context = null;
    private $config  = null;

    /**
     * RabbitQueue构造方法.
     *
     * @param AmqpContext $context
     * @param array       $config  连接配置
     *
     * @throws
     */
    private function __construct(AmqpContext $context, array $config)
    {
        $exchange = $config['exchange'] ?? '';
        if (empty($exchange)) {
            throw new \Exception('Empty Exchange');
        }
        $rabbitTopic = $context->createTopic($exchange);
        $rabbitTopic->addFlag(AmqpTopic::FLAG_DURABLE);
        $context->declareTopic($rabbitTopic);
        $this->context = $context;
        $this->config  = $config;
    }

    /**
     * 建立Queue连接.
     *
     * @param array $config 连接配置
     *
     * @throws
     *
     * @return mixed
     */
    public static function getConnection(array $config)
    {
        try {
            $factory     = new AmqpConnectionFactory($config);
            $context     = $factory->createContext();
            $connection  = new self($context, $config);
        } catch (\AMQPConnectionException $e) {
            throw new \AMQPConnectionException($e);
        } catch (\Throwable $e) {
            throw new \Throwable($e);
        } catch (\Exception $e) {
            throw new \Exception($e);
        }

        return $connection;
    }

    /**
     * push message.
     *
     *
     * @param mixed $message
     * @param mixed $queueName
     *
     * @throws
     */
    public function push($message, $queueName)
    {
        if (!$this->isConnected()) {
            throw new \Exception('Connection Break');
        }
        $queue = $this->createQueue($queueName);
        if (!is_object($queue)) {
            throw new \Exception('Error Queue Object');
        }
        $producer = $this->context->createProducer();

        $producer->send($queue, $this->context->createMessage($message));
    }

    /**
     * pop message.
     *
     *
     * @param mixed $queueName
     *
     * @throws
     *
     * @return bool
     */
    public function pop($queueName)
    {
        if (!$this->isConnected()) {
            throw new \Exception('Connection Break');
        }
        $queue    = $this->createQueue($queueName);
        $consumer = $this->context->createConsumer($queue);
        if ($msg = $consumer->receive(1)) {
            $result = $msg->getBody();
            $consumer->acknowledge($msg);

            return $result;
        }

        return false;
    }

    /**
     * queue message length.
     *
     *
     * @param mixed $queueName
     *
     * @throws
     *
     * @return int
     */
    public function getLength($queueName)
    {
        if (!$this->isConnected()) {
            throw new \Exception('Connection Break');
        }
        $queue = $this->createQueue($queueName);
        if (!is_object($queue)) {
            throw new \Exception('Error Queue Object');
        }
        $len = $this->context->declareQueue($queue);

        return $len ?? 0;
    }

    /**
     * close connection.
     */
    public function close()
    {
        if (!$this->isConnected()) {
            return true;
        }

        $this->context->close();
    }

    /**
     * if connection.
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->context->getExtChannel()->getConnection()->isConnected();
    }

    /**
     * create queue.
     *
     * @param string $queueName
     *
     * @return bool
     */
    private function createQueue($queueName)
    {
        try {
            $i = 0;
            do {
                $queue = $this->context->createQueue($queueName);
                $i++;
                if (($queue && $this->isConnected()) || $i > 3) {
                    break;
                }
                sleep(1);
            } while (!$queue);
            $queue->addFlag(AmqpQueue::FLAG_DURABLE);
            $this->context->declareQueue($queue);
        } catch (\Throwable $e) {
            return false;
        } catch (\Exception $e) {
            return false;
        }

        return $queue;
    }
}
