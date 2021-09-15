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
    public static function input($buffer, \Workerman\Connection\TcpConnection $connection)
    {
        return \strlen($buffer);
    }

    /**
     * Decode.
     *
     * @param string $buffer
     * @return string
     */
    public static function decode($buffer)
    {
        return json_decode($buffer, true);
    }

    /**
     * Encode.
     *
     * @param string $buffer
     * @return string
     */
    public static function encode($buffer)
    {
        return $buffer;
    }    
    
}