<?php

require_once __DIR__ . "/../../../src/Kafka/Kafka.php";
require_once __DIR__ . "/../../../src/Kafka/V07/ConsumerChannel.php";

use Kafka\Kafka;
use Kafka\Offset;
use Kafka\Offset_32bit;
use Kafka\Offset_64bit;
use Kafka\Message;

class TestV07ConsumerChannel extends \Kafka\V07\ConsumerChannel
{
    private $incomingDataEnabled = false;
    public function setStreamContents($contents)
    {
        ftruncate($this->socket,0);
        fwrite($this->socket, $contents); rewind($this->socket);
        $this->incomingDataEnabled = true;
    }
    public function getStreamContents()
    {
        rewind($this->socket); return stream_get_contents($this->socket);
    }
    protected function createSocket()
    {
        if (!is_resource($this->socket)) $this->socket = fopen("php://memory", "rw");
    }
    public function hasIncomingData()
    {
        if ($this->incomingDataEnabled) {
            return parent::hasIncomingData();
        } else {
            return true;
        }
    }
}


//64bit tests
if (PHP_INT_SIZE === 8) {
	$consumer = new TestV07ConsumerChannel(new Kafka());
	$consumer->fetch("topic1", 0, new Offset_64bit("2870"));
	//test fetch request wire format
	assert($consumer->getStreamContents() === chr(0).chr(0).chr(0).chr(26).chr(0).chr(1).chr(0)
	    .chr(6).chr(116).chr(111).chr(112).chr(105).chr(99).chr(49).chr(0).chr(0).chr(0).chr(0)
	    .chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(11).chr(54).chr(0).chr(15).chr(66).chr(64));
	//test starting watermark
	assert((string) $consumer->getWatermark() === "2870");
	
	$consumer->setStreamContents(
	    chr(0).chr(0).chr(0).chr(115) // request size
	    .chr(0).chr(0) //error code
	    //message 1
	    .chr(0).chr(0).chr(0).chr(20).chr(1).chr(0).chr(116).chr(59).chr(185).chr(158).chr(72)
	    .chr(101).chr(108).chr(108).chr(111).chr(32).chr(87).chr(111).chr(114).chr(108).chr(100)
	    .chr(32).chr(65).chr(33)
	    //message set gzip(message 2 and 3)
	    .chr(0).chr(0).chr(0).chr(61).chr(1).chr(1).chr(161).chr(195).chr(2).chr(60).chr(31)
	    .chr(139).chr(8).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(3).chr(99).chr(96).chr(96)
	    .chr(16).chr(97).chr(100).chr(136).chr(23).chr(123).chr(21).chr(235).chr(145).chr(154)
	    .chr(147).chr(147).chr(175).chr(16).chr(158).chr(95).chr(148).chr(147).chr(162).chr(224)
	    .chr(164).chr(200).chr(0).chr(22).chr(119).chr(227).chr(189).chr(45).chr(131).chr(44).chr(238)
	    .chr(172).chr(8).chr(0).chr(39).chr(196).chr(27).chr(184).chr(48).chr(0).chr(0).chr(0)
	    //message 4
	    .chr(0).chr(0).chr(0).chr(20).chr(1).chr(0).chr(9).chr(76).chr(77).chr(219).chr(72).chr(101)
	    .chr(108).chr(108).chr(111).chr(32).chr(87).chr(111).chr(114).chr(108).chr(100).chr(32)
	    .chr(68).chr(33)
	);
	//starting with one non-compressed message
	$message1 = $consumer->nextMessage();
	//test starting watermark
	assert((string) $consumer->getWatermark() === "2894");
	//test payload and offset
	assert($message1->payload() === 'Hello World A!');
	assert($message1->offset() == new Offset_64bit("2870"));
	$message2 = $consumer->nextMessage();
	//test starting watermark moves by the whole message set
	assert((string) $consumer->getWatermark() === "2959");
	//following message set of two compressed messages have commong offset in 0.7.x
	assert($message2->payload() === 'Hello World B!');
	assert($message2->offset() == new Offset_64bit("2894"));
	$message3 = $consumer->nextMessage();
	//test watermark stays
	assert((string) $consumer->getWatermark() === "2959");
	assert($message3->payload() === 'Hello World C!');
	assert($message3->offset() == new Offset_64bit("2894"));
	//and one more non-compressed message
	$message4 = $consumer->nextMessage();
	assert((string) $consumer->getWatermark() == "2983");
	assert($message4->payload() === 'Hello World D!');
	assert($message4->offset() == new Offset_64bit("2959"));
	
	//test getWatermark doesn't advance when nextMessage fails due to Kafka\Exception\EndOfStream
	$consumer = new TestV07ConsumerChannel(new Kafka());
	$consumer->fetch("topic1", 0, new Offset_64bit("2870"));
	$consumer->setStreamContents(
	    chr(0).chr(0).chr(0).chr(115) // request size (longer than actual size)
	    .chr(0).chr(0) //error code
	    .chr(0).chr(0).chr(0).chr(20).chr(1).chr(0).chr(116).chr(59).chr(185).chr(158).chr(72)
	    .chr(101).chr(108).chr(108).chr(111).chr(32).chr(87).chr(111).chr(114).chr(108).chr(100)
	    //end of stream in the middle of the message
	);
	assert((string) $consumer->getWatermark() === "2870");
	assert($consumer->nextMessage() === false);
	assert((string) $consumer->getWatermark() === "2870");
}	




//32bit tests
$consumer = new TestV07ConsumerChannel(new Kafka());
$consumer->fetch("topic1", 0, new Offset_32bit("b36"));
//test fetch request wire format
assert($consumer->getStreamContents() === chr(0).chr(0).chr(0).chr(26).chr(0).chr(1).chr(0)
    .chr(6).chr(116).chr(111).chr(112).chr(105).chr(99).chr(49).chr(0).chr(0).chr(0).chr(0)
    .chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(11).chr(54).chr(0).chr(15).chr(66).chr(64));
//test starting watermark
assert((string) $consumer->getWatermark() === "0000000000000b36");


//test loadMessage() can decompress message sets correctly
/* emulate response - this was generated by consuming the following produce sequence:
$kafka  = new Kafka();
$producer = $kafka->createProducer();
$producer->add(new Message("topic1", 0, "Hello World A!", Kafka::COMPRESSION_NONE));
$producer->add(new Message("topic1", 0, "Hello World B!", Kafka::COMPRESSION_GZIP));
$producer->add(new Message("topic1", 0, "Hello World C!", Kafka::COMPRESSION_GZIP));
$producer->add(new Message("topic1", 0, "Hello World D!", Kafka::COMPRESSION_NONE));
$producer->produce();*/
$consumer->setStreamContents(
    chr(0).chr(0).chr(0).chr(115) // request size
    .chr(0).chr(0) //error code
    //message 1
    .chr(0).chr(0).chr(0).chr(20).chr(1).chr(0).chr(116).chr(59).chr(185).chr(158).chr(72)
    .chr(101).chr(108).chr(108).chr(111).chr(32).chr(87).chr(111).chr(114).chr(108).chr(100)
    .chr(32).chr(65).chr(33)
    //message set gzip(message 2 and 3)
    .chr(0).chr(0).chr(0).chr(61).chr(1).chr(1).chr(161).chr(195).chr(2).chr(60).chr(31)
    .chr(139).chr(8).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(3).chr(99).chr(96).chr(96)
    .chr(16).chr(97).chr(100).chr(136).chr(23).chr(123).chr(21).chr(235).chr(145).chr(154)
    .chr(147).chr(147).chr(175).chr(16).chr(158).chr(95).chr(148).chr(147).chr(162).chr(224)
    .chr(164).chr(200).chr(0).chr(22).chr(119).chr(227).chr(189).chr(45).chr(131).chr(44).chr(238)
    .chr(172).chr(8).chr(0).chr(39).chr(196).chr(27).chr(184).chr(48).chr(0).chr(0).chr(0)
    //message 4
    .chr(0).chr(0).chr(0).chr(20).chr(1).chr(0).chr(9).chr(76).chr(77).chr(219).chr(72).chr(101)
    .chr(108).chr(108).chr(111).chr(32).chr(87).chr(111).chr(114).chr(108).chr(100).chr(32)
    .chr(68).chr(33)
);
//starting with one non-compressed message
$message1 = $consumer->nextMessage();
//test starting watermark
assert((string) $consumer->getWatermark() === "0000000000000b4e");
//test payload and offset
assert($message1->payload() === 'Hello World A!');
assert($message1->offset() == new Offset_32bit("b36"));
$message2 = $consumer->nextMessage();
//test starting watermark moves by the whole message set
assert((string) $consumer->getWatermark() === "0000000000000b8f");
//following message set of two compressed messages have commong offset in 0.7.x
assert($message2->payload() === 'Hello World B!');
assert($message2->offset() == new Offset_32bit("b4e"));
$message3 = $consumer->nextMessage();
//test watermark stays
assert((string) $consumer->getWatermark() === "0000000000000b8f");
assert($message3->payload() === 'Hello World C!');
assert($message3->offset() == new Offset_32bit("b4e"));
//and one more non-compressed message
$message4 = $consumer->nextMessage();
assert((string) $consumer->getWatermark() == "0000000000000ba7");
assert($message4->payload() === 'Hello World D!');
assert($message4->offset() == new Offset_32bit("b8f"));

//test getWatermark doesn't advance when nextMessage fails due to Kafka\Exception\EndOfStream
$consumer = new TestV07ConsumerChannel(new Kafka());
$consumer->fetch("topic1", 0, new Offset_32bit("b36"));
$consumer->setStreamContents(
    chr(0).chr(0).chr(0).chr(115) // request size (longer than actual size)
    .chr(0).chr(0) //error code
    .chr(0).chr(0).chr(0).chr(20).chr(1).chr(0).chr(116).chr(59).chr(185).chr(158).chr(72)
    .chr(101).chr(108).chr(108).chr(111).chr(32).chr(87).chr(111).chr(114).chr(108).chr(100)
    //end of stream in the middle of the message
);
assert((string) $consumer->getWatermark() === "0000000000000b36");
assert($consumer->nextMessage() === false);
assert((string) $consumer->getWatermark() === "0000000000000b36");
