<?php

namespace Framework\Queue\Driver;

use function Amp\call;
use Framework\Queue\Message;
use Framework\Queue\DriverInterface;
use Framework\Beanstalk\BeanstalkClient;

class BeanstalkDriver implements DriverInterface
{

    private $client;
    private $tube;

    public function __construct($config)
    {
        $this->client = new BeanstalkClient($config['host'], $config['port']);
        // $this->client->debug = true;
        $this->tube = $config['tube'];
    }

    public function connect()
    {
        return call(function() {
            yield $this->client->connect();

            if ($this->tube != 'default') {
                yield $this->client->watch($this->tube);
                yield $this->client->ignore('default');
                yield $this->client->useTube($this->tube);
            }
        });
    }

    public function close()
    {
        return $this->client->close();
    }

    public function push(Message $message, $delay=0)
    {
        $raw = serialize($message);
        return $this->client->put($raw, BeanstalkClient::DEFAULT_PRI, $delay);
    }

    public function pop()
    {
        return call(function() {
            $data = yield $this->client->reserve();
            $message = unserialize($data['body']);
            $message->set('id', $data['id']);
            return $message;
        });
    }

    public function ack(Message $message)
    {
        return $this->client->delete($message->get('id'));
    }

    public function fail(Message $message)
    {
        return yield $this->client->bury($message->get('id'));
    }

    public function release(Message $message)
    {
        return $this->client->release($message->get('id'));
    }

}
