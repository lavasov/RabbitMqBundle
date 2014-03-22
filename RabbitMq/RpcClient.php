<?php

namespace OldSound\RabbitMqBundle\RabbitMq;

use OldSound\RabbitMqBundle\RabbitMq\BaseAmqp;
use PhpAmqpLib\Message\AMQPMessage;

class RpcClient extends BaseAmqp
{
    protected $requests = 0;
    protected $replies = array();
    protected $queueName;
    protected $expectSerializedResponse;

    public function initClient($expectSerializedResponse = true)
    {
        list($this->queueName, ,) = $this->getChannel()->queue_declare("", false, false, true, true);
        $this->expectSerializedResponse = $expectSerializedResponse;
    }

    public function addRequest($msgBody, $server, $requestId = null, $routingKey = '')
    {
        if (empty($requestId)) {
            throw new \InvalidArgumentException('You must provide a $requestId');
        }

        $msg = new AMQPMessage($msgBody, array('content_type' => 'text/plain',
                                               'reply_to' => $this->queueName,
                                               'correlation_id' => $requestId));

        $this->getChannel()->basic_publish($msg, $server, $routingKey);

        $this->requests++;
    }

    public function getReplies($timeout = 0)
    {
        $this->replies = array();
        $this->getChannel()->basic_consume($this->queueName, '', false, true, false, false, array($this, 'processMessage'));

        while (count($this->replies) < $this->requests) {
            $this->getChannel()->wait(null, false, $timeout);
        }

        $this->getChannel()->basic_cancel($this->queueName);
        $this->requests = 0;

        return $this->replies;
    }

    public function processMessage(AMQPMessage $msg)
    {
        $messageBody = $msg->body;
        if ($this->expectSerializedResponse) {
            $messageBody = unserialize($messageBody);
        }

        $this->replies[$msg->get('correlation_id')] = $messageBody;
    }
}
