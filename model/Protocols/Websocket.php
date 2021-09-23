<?php
namespace Edisom\App\server\model\Protocols;
use Edisom\App\server\model\ServerModel;

class Websocket extends \Workerman\Protocols\Websocket
{	
	public static function encode($buffer, \Workerman\Connection\ConnectionInterface $connection)
    {
		ServerModel::log("Шлем: ".$buffer);
		return parent::encode($buffer, $connection);
	}
	
    public static function decode($buffer, \Workerman\Connection\ConnectionInterface $connection):array
    {
		if($buffer = parent::decode($buffer, $connection))
		{
			ServerModel::log("Клиент говорит: ".$buffer);
			return json_decode($buffer, true);
		}
    }
}
