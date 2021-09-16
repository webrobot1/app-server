<?php
namespace Edisom\App\server\model\Protocols;

class Websocket extends \Workerman\Protocols\Websocket
{
	
	public static function encode($buffer, ConnectionInterface $connection)
    {
		Worker::log("Шлем: ".$buffer);
		return parent::encode($buffer, $connection);
	}
	
    public static function decode($buffer, \Workerman\Connection\ConnectionInterface $connection):array
    {
		Worker::log("Клиент говорит: ".$buffer);
		return json_decode(parent::decode($buffer, $connection), true);
    }
}
