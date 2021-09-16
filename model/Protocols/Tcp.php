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
    public static function input($buffer, \Workerman\Connection\ConnectionInterface $connection):int
    {
        if (isset($connection->maxPackageSize) && \strlen($buffer) >= $connection->maxPackageSize) 
		{
			Worker::safeEcho("error package. package_length=".\strlen($buffer)."\n");
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
		return \rtrim($buffer, '||');
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