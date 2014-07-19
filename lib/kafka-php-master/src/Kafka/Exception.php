<?php

namespace Kafka;

class Kafka_Exception extends \RuntimeException
{
}

namespace Kafka\Kafka_Exception;

class EndOfStream extends \Kafka\Kafka_Exception
{
}

class TopicUnavailable extends \Kafka\Kafka_Exception
{
}