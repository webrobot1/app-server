<?php
namespace Edisom\App\server\model\Protocols;

class Tcp
{
    /**
     * Check the integrity of the package.
     *
     * @param string        $buffer
     * @param TcpConnection $connection
     * @return int
     */
    public static function input($buffer, \Workerman\Connection\Connection $connection):int
    {
        return \strlen($buffer);
    }

    /**
     * Decode.
     *
     * @param string $buffer
     * @return string
     */
    public static function decode($buffer):array
    {	
		if($buffer = explode('||', $buffer))
		{
			foreach($buffer as &$buff)
				$buff = json_decode($buff, true);	
		}
		return $buffer;
    }

    /**
     * Encode.
     *
     * @param string $buffer
     * @return string
     */
    public static function encode($buffer):string
    {
		if($buffer = $buffer.'||')
		{	
			return $buffer;
		}
    }     
}