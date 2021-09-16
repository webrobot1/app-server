<?php
namespace Edisom\App\server\model\Protocols;
use Workerman\Worker;

class Udp extends Tcp
{
    public static function encode($buffer):string
    {
		Worker::log("Шлем: ".$buffer);
		return $buffer;
    }    
}