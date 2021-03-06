<?php
namespace Edisom\App\server\model;
class BackendModel extends \Edisom\Core\Model
{	
	function status(){
		return [
			'workerman'=>\Edisom\Core\Cli::cmd(\Edisom\Core\Cli::get('\\Edisom\\App\\server\\model\\ServerModel', 'command', 'status')),
			'redis'=>static::explode(static::redis()->info('memory'), "<br/>", false),
			'supervisor'=>\Edisom\Core\Cli::cmd('service supervisor status'),
		];
	}	
	   			
	function redis_keys()
	{
		$it = NULL;
		do {
			// Scan for some keys
			$arr_keys = static::redis()->scan($it);

			// Redis may return empty results, so protect against that
			if ($arr_keys !== FALSE) {
				foreach($arr_keys as $str_key) {
					echo "$str_key <br/>";
				}
			}
		} while ($it > 0); 
	}		
	
	
	function stop(){
		\Edisom\Core\Cli::cmd(\Edisom\Core\Cli::get('\\Edisom\\App\\server\\model\\ServerModel', 'command', 'stop'));	
	}		
	
	function restart(){
		\Edisom\Core\Cli::cmd('service supervisor restart');	
	}	
	    		
	function getLog(string $file = 'main.log')
    {    
        if($content = @file(static::temp().$file))
            return implode("", array_splice($content, count($content)-50, 50));
    }    
}