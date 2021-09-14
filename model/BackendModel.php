<?php
namespace Edisom\App\server\model;
class BackendModel extends \Edisom\Core\Model
{	
	function status(){
		return [
			'workerman'=>\Edisom\Core\Cli::cmd(\Edisom\Core\Cli::get('\\Edisom\\App\\server\\model\\ServerModel', 'synch', "status")),
			'redis'=>static::explode(static::redis()->info('memory'), "<br/>", false),
			'supervisor'=>\Edisom\Core\Cli::cmd('service supervisor status'),
		];
	}	
	   			
	function stop(){
		\Edisom\Core\Cli::cmd(\Edisom\Core\Cli::get('\\Edisom\\App\\server\\model\\ServerModel', 'synch', "stop"));	
	}		
	
	function restart(){
		\Edisom\Core\Cli::cmd('service supervisor restart');	
	}	
	    		
	function getLog(string $file = 'main.log')
    {    
        if($content = @file(static::temp().$file))
            return implode("", array_reverse(array_splice($content, count($content)-50, 50)));
    }    
}