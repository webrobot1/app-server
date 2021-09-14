<?php
namespace Edisom\App\server\model\Protocols;

class WebSocket extends \Workerman\Protocols\WebSocket
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