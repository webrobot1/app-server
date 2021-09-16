<?php
namespace Edisom\App\server\model\Protocols;

class Websocket extends \Workerman\Protocols\Websocket
{
    /**
     * Decode.
     *
     * @param string $buffer
     * @return string
     */
    public static function decode($buffer, \Workerman\Connection\ConnectionInterface $connection)
    {
		return json_decode(parent::decode($buffer, $connection), true);
    }
}
