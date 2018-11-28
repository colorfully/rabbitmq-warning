<?php

/*
 * This file is part of PHP CS Fixer.
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace LCP;

use LCP\Service\Monitor;
use LCP\Service\Notice;

class App
{
    private $config     = null;
    private $connection = null;

    /**
     * 构造方法 - 连接MQ.
     */
    public function __construct()
    {
    }

    /**
     * 启动监控服务.
     */
    public function start()
    {
        var_dump('Job start...');
        $p1 = new \Swoole\Process([$this, 'checkConnection']);
        $p2 = new \Swoole\Process([$this, 'checkOverStock']);
        $p1->start();
        $p2->start();
        while (true) {
            if (!\Swoole\Process::wait()) {
                break;
            }
        }
        var_dump('Job end...');
    }

    /**
     * 监控连接MQ是否正常.
     */
    public function checkConnection()
    {
        try {
            $this->connection = Monitor::getInstance()->checkConnection();
        } catch (\Throwable $e) {
            //调用预警服务
            $data              = [];
            $data['title']     = '监控多次连接MQ服务器失败';
            $data['content']   = '### Error: ' . $data['title'] . PHP_EOL
                . '- Host: ' . GC::$config['connection']['host'] . PHP_EOL
                . '- User: ' . GC::$config['connection']['user'] . PHP_EOL
                . '- Port: ' . GC::$config['connection']['port'] . PHP_EOL
                . '- Vhost: ' . GC::$config['connection']['vhost'] . PHP_EOL
                . '- Exchange: ' . GC::$config['connection']['exchange'] . PHP_EOL
                . '- Level: 一级' . PHP_EOL
            ;
            $data = array_merge($data, GC::$config['connectRules']['mode']);
            Notice::getInstance()->notice($data, GC::$config['connectRules']['mode']['type']);
        }
    }

    /**
     * 监控MQ队列消息是否积压.
     */
    public function checkOverStock()
    {
        if (!empty(GC::$config['queueRules'])) {
            foreach (GC::$config['queueRules'] as $queueName => $queueConfig) {
                try {
                    $result = Monitor::getInstance()->checkOverStock($queueConfig);
                    if (false !== $result) {
                        $data              = [];
                        $data['title']     = 'MQ积压消息过多';
                        $data['content']   = '### Notice: ' . $data['title'] . PHP_EOL
                            . '- Host: ' . GC::$config['connection']['host'] . PHP_EOL
                            . '- User: ' . GC::$config['connection']['user'] . PHP_EOL
                            . '- Port: ' . GC::$config['connection']['port'] . PHP_EOL
                            . '- Vhost: ' . GC::$config['connection']['vhost'] . PHP_EOL
                            . '- Exchange: ' . GC::$config['connection']['exchange'] . PHP_EOL
                            . '- Queue: ' . $queueName . PHP_EOL
                            . '- 积压数量: ' . $result . PHP_EOL
                        ;
                        $data = array_merge($data, $queueConfig['mode']);
                        Notice::getInstance()->notice($data, $queueConfig['mode']['type']);
                    }
                } catch (\AMQPConnectiodnException $e) {
                    var_dump($e->getMessage());
                    break;
                } catch (\Throwable $e) {
                    var_dump($e->getMessage());
                    break;
                }
            }
        }
    }
}
