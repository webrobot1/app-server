<?php
namespace Edisom\App\server\model;

class ServerModel extends \Edisom\Core\Model
{	
	const PROTOCOL = "Websocket";
	
	private $socket;
	private $tokens = array();
	public static $pingPause = 20;
		
	private function save(string $token)
	{
		// сохранение в тихом режиме (ответа не ждем)
		\Edisom\Core\Cli::cmd(\Edisom\Core\Cli::get('\\Edisom\\App\\game\\controller\\ApiController', 'save', base64_encode(json_encode(['token'=>$token], JSON_NUMERIC_CHECK)), null, true));	
	}
	
	
	// нужно прийти к тому что бы ответ не ждать и рассылать в приложенях данные
	private function run(string $controller, string $action, array $data)
	{		
		$cmd = \Edisom\Core\Cli::get('\\Edisom\\App\\game\\controller\\'.ucfirst($controller)."Controller", $action, base64_encode(json_encode($data, JSON_NUMERIC_CHECK)));
		
		static::log('вызываем '.$cmd);
		
		try{
			if($return = \Edisom\Core\Cli::cmd($cmd))
			{
				$this->socket->connections[$this->tokens[$data['token']]]->send($return);
			}			
		}
		catch(\Exception $ex) 
		{
			static::log($ex, 'main.log');
			$this->disconect($data['token'], $ex->getMessage());	
		}
	}
		
	private function disconect(string $token, string $message)
	{		
		if($connection = $this->socket->connections[$this->tokens[$token]])
		{	
			static::log('отключаем '.$token);
			static::log($message);
			$connection->close($message);		
			
			if(static::PROTOCOL == 'Udp')
			{
				unset($this->socket->connections[$this->tokens[$token]]);
				 \call_user_func($this->socket->onClose, $connection);
			}
		}	
	}	
	
	private function remove($token)
	{
		// удаляем геокоординаты
		static::redis()->zRem('map:'.static::redis()->hGet($token, 'map_id'), $token);
		// удаляем последние записи о пользователе
		static::redis()->del($token);
		
		
		// удаляем его из конекта
		unset($this->tokens[$token]);
		
		//todo удаляем с карты		
	}
	
	function synch()
	{
		if (\PHP_SAPI !== 'cli') {
            exit("Only run in command line mode \n");
        }
		
		// ищем фаил /lib/systemd/system/apache2.service и ставим PrivateTmp = false.
		// а то все идет по пизде с проверко наличие фаилов Workerman в папке /tmp
		// systemctl daemon-reload  и перезапускаем apache 
		
		if(static::PROTOCOL && ($address = strtolower(static::PROTOCOL).'://0.0.0.0:'.static::config('port')))
		{
			\Workerman\Worker::$logFile = static::temp().'main.log';

			static::log('открываем сервер на '.$address);	
			$this->socket = new \Workerman\Worker($address);
			$this->socket->onWorkerStart = function($worker)
			{	
				// персональный протокол (для decode и encode сообщений)
				// todo понадогбиться разделять Json пакеты друг от друга (придумать разделитель, типа \n)
				$worker->protocol = "\\Edisom\\App\\server\\model\\Protocols\\".static::PROTOCOL;
				static::log('Используемый протокол: '.$worker->protocol);
				
				@unlink(static::temp().'main.log');
				@unlink(static::temp().'error.log');
				
				static::log("очищаем Redis");
				static::redis()->flushAll();
							
				
				// если что то придет из других приложений (из Redis) - сообщим всем на карте
				// todo пока тут одна карта но нужен массив из всех карт
				$subscribe = new \Workerman\Redis\Client('redis://127.0.0.1:6379');
				$subscribe->pSubscribe("map:?", function ($pattren, $channel, $message) 
				{
					foreach(static::redis()->zRange($channel, 0, -1) as $token)
					{	
						// если у нас есть соединение  
						if(isset($this->socket->connections[$this->tokens[$token]]))
						{				
							$this->socket->connections[$this->tokens[$token]]->send($message);
						}
						else
						{
							// если нет - удалим из редиса данные токена
							static::log('Пользователь '.$token.' не найден');	
							$this->remove($token);
						}
					}
				});
				
				static::log("запускаем таймер");	
				
				// сохраняем всех игроков раз в период
				\Workerman\Lib\Timer::add(static::$pingPause, function()
				{
					foreach ($this->tokens as $token=>$id) 
					{	
						if(($time = strtotime(static::redis()->hGet($token, 'datetime'))) && strtotime("+5 minute", $time) < time())
						{
							$this->disconect($token, 'Таймаут');
						}
						else
						{
							if($time >= time()-static::$pingPause){
								static::log("запрос скриншета");	
								$this->socket->connections[$id]->send(json_encode(['action'=>'screen']));
							}
							
							$this->save($token);
						}
					}			
				}); 
			}; 
					
			$this->socket->onWorkerStop = function($worker)
			{	
				foreach (array_keys($this->tokens) as $token) 
					$this->disconect($token, 'сервер остановлен');		
			};	
			
			$this->socket->onClose = function($connection)
			{
				if($token = array_search($connection->id, $this->tokens))
				{
					
					// обнулим параметры (они обнулятся при сохранении игрока в БД)
					static::redis()->hSet($token, 'ip', '');
					static::redis()->hSet($token, 'token', '');
					static::redis()->hSet($token, 'action', 'offline');
					
					
					$this->save($token);
					$this->remove($token);
				}
			};
			
			$this->socket->onError = function($connection, $code, $msg)
			{
				$connection->close("error $code $msg");
			};
			
			$this->socket->onMessage = function($connection, array $data)
			{ 
				// udp
				if(static::PROTOCOL == 'Udp')
				{
					$connection->id = $connection->getRemoteAddress();
					$this->socket->connections[$connection->id] = $connection;
				}
								
				// токен передаем только в первом сообщении (дальше его из переменной $this->tokens берем по установленному соединению)
				if((isset($data['token']) || ($data['token'] = array_search($connection->id, $this->tokens))) && static::redis()->hExists($data['token'], 'id'))
				{								
					// запишем кто сидит из под токеном что бы слать ответ
					if(!array_key_exists($data['token'], $this->tokens))
					{
						$this->tokens[$data['token']] = $connection->id;
						static::redis()->hSet($data['token'], 'ip' , $connection->getRemoteAddress());
					}
						
					// обновим в редисе данные статические	
					static::redis()->hSet($data['token'], 'datetime' , date("Y-m-d H:i:s"));
					if(isset($data['pingTime']))
						static::redis()->hSet($data['token'], 'ping' , $data['pingTime']);
						
					// можно еще в $data['action'] слать первым парметром приложение (ну пока только game используем)
					// можно переделать на HTTP (типа Rabbit) , тогда вызываем метод по адресной строке вида (последняя часть - GET параметры распарсенные из массива):
					// /game/$controller/$action/?static::explode($data, '&', false) 
					list($controller, $action) = array_replace_recursive(array('api', 'index'), array_filter(explode('/', $data['action'])));
						
					static::redis()->hSet($data['token'], 'action', $data['action']);	
					unset($data['action']);
					
					$this->run($controller, $action, $data);					
				}
				else{
					$connection->close('токен не найден');
				}
			};

			\Workerman\Worker::runAll();
		}
		else
			throw new \Exception('не определен протокол');
	}	
}