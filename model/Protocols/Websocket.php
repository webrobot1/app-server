<?php
namespace Edisom\App\server\model\Protocols;
use Workerman\Worker;

class Websocket extends \Workerman\Protocols\Websocket
{	
	public static function encode($buffer, \Workerman\Connection\ConnectionInterface $connection)
    {
		if($buffer = parent::encode($buffer, $connection))
		{
			Worker::log("Шлем: ".$buffer);
			return $buffer;
		}
	}
	
    public static function decode($buffer, \Workerman\Connection\ConnectionInterface $connection):array
    {
		if($buffer = parent::decode($buffer, $connection))
		{
			Worker::log("Клиент говорит: ".$buffer);
			return json_decode($buffer, true);
		}
    }
}
