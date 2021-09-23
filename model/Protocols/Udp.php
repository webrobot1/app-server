<?php
namespace Edisom\App\server\model\Protocols;
use Edisom\App\server\model\ServerModel;

class Udp extends Tcp
{
    public static function encode($buffer):string
    {
		ServerModel::log("Шлем: ".$buffer);
		return $buffer;
    }    
}