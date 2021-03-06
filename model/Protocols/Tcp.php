<?php
namespace Edisom\App\server\model\Protocols;
use Edisom\App\server\model\ServerModel;

class Tcp
{
    /**
     * Check the integrity of the package.
     *
     * @param string        $buffer
     * @param TcpConnection $connection
     * @return int
     */
    public static function input($buffer, \Workerman\Connection\ConnectionInterface $connection):int
    {
        if (isset($connection->maxPackageSize) && \strlen($buffer) >= $connection->maxPackageSize) 
		{
			ServerModel::log("error package. package_length=".\strlen($buffer)."\n");
            $connection->close();
            return 0;
        }
		
        $pos = \strpos($buffer, "||");
        if ($pos === false) {
            return 0;
        }
        // Return the current package length.
        return $pos + 2;
    }

    /**
     * Decode.
     *
     * @param string $buffer
     * @return string
     */
    public static function decode($buffer):array
    {	
		if($buffer = \rtrim($buffer, '||')){
			ServerModel::log("Клиент говорит: ".$buffer);
			return json_decode($buffer, true);
		}
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
			Worker::log("Шлем: ".$buffer);
			return $buffer;
		}
    }     
}