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
		$this->run('api', 'save', $token);	
	}
		
	// нужно прийти к тому что бы ответ не ждать и рассылать в приложенях данные
	private function run(string $model, string $action, string $token, array $data = null)
	{	
		if($model = '\\Edisom\\App\\game\\model\\api\\'.ucfirst(strtolower($model))."Model")
		{
			// модель загружается в отдельном ветки процесса и уничтожается (те все модели Api можно обновлять без перезагрузки сервера и рабоатет все асинхронно)
			$pid = pcntl_fork();
			if ($pid == -1) 
				 throw new \Exception('Не удалось породить дочерний процесс');
			 
			if ($pid) // Код родительского процесса
			{
				pcntl_wait($status, WNOHANG);
				static::log('вызываем '.$model.' в ветке '.$pid);
			} 
			else 
			{
				$model::getInstance($token)->$action();
				exit();
			}	
		}
	}
	
	// метод нужен только для протокола Udp  тк у него нет обработчика onClose
	private function disconect(string $token, string $message)
	{		
		if($connection = $this->socket->connections[$this->tokens[$token]])
		{	
			$connection->close(json_encode(['error'=>$message]));		
			if(static::PROTOCOL == 'Udp')
			{
				unset($this->socket->connections[$this->tokens[$token]]);
				 \call_user_func($this->socket->onClose, $connection);
			}
		}	
	}	
	
	private function remove($token)
	{	
		// удаляем последние записи о пользователе
		$this->save($token);
		
		// удаляем геокоординаты
		static::redis()->zRem('map:'.static::redis()->hGet($token, 'map_id'), $token);
		static::redis()->del($token);
			
		// удаляем его из конекта
		unset($this->tokens[$token]);
		
		//todo удаляем с карты		
	}
	
	function command()
	{
		\Workerman\Worker::runAll();
    }		
   		
	function start()
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
				$subscribe->pSubscribe(["token:*", "map:*"], function ($pattren, $chanel, $message) 
				{
					list($key, $id) = explode(':', $chanel);
					switch($key)
					{
						case 'map':
							$tokens = static::redis()->zRange($chanel, 0, -1);
						break;
						case 'token':
							$tokens[] = $id;
						break;
					}
					
					static::log('Пришли данные на канал '.$key.':'.$id);
					
					foreach($tokens as $token)
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
							//if($time >= time()-static::$pingPause){
							//	static::log("запрос скриншета");	
							//	$this->socket->connections[$id]->send(json_encode(['action'=>'screen']));
							//}
							
							$this->save($token);
						}
					}			
				}); 
			}; 
					
			$this->socket->onWorkerStop = function($worker)
			{	
				static::log('сервер остановлен');
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
					
					$this->remove($token);
				}
				else
					static::log("не найден токен закрытого соединения");	
			};
			
			$this->socket->onError = function($connection, $code, $msg)
			{
				$connection->close(json_encode(['error'=>"error $code $msg"]));
			};
			
			$this->socket->onMessage = function($connection, array $data)
			{ 
				// udp
				if(static::PROTOCOL == 'Udp')
				{
					$connection->id = $connection->getRemoteAddress();
					$this->socket->connections[$connection->id] = $connection;
				}
				
				if((!$token = array_search($connection->id, $this->tokens)) && isset($data['token']) && static::redis()->hExists($data['token'], 'id'))
				{
					$token = $data['token'];
					
					// запишем кто сидит из под токеном что бы слать ответ
					$this->tokens[$token] = $connection->id;
					static::redis()->hSet($token, 'ip' , $connection->getRemoteAddress());	
				}
				
				
				// токен передаем только в первом сообщении (дальше его из переменной $this->tokens берем по установленному соединению)
				if($token)
				{
					if($data['action'])
					{
						// обновим в редисе данные статические	
						static::redis()->hSet($token, 'datetime' , date("Y-m-d H:i:s"));
						if(isset($data['pingTime']))
							static::redis()->hSet($token, 'ping' , $data['pingTime']);
							
						// можно еще в $data['action'] слать первым парметром приложение (ну пока только game используем)
						// можно переделать на HTTP (типа Rabbit) , тогда вызываем метод по адресной строке вида (последняя часть - GET параметры распарсенные из массива):
						// /game/$model/$action/?static::explode($data, '&', false) 
						list($model, $action) = array_replace_recursive(array('api', 'index'), array_filter(explode('/', $data['action'])));
							
						static::redis()->hSet($token, 'action', $data['action']);	
						unset($data['action']);
						
						$this->run($model, $action, $token, $data);
					}
					else
						$connection->close(json_encode(['error'=>'не указан action']));	
				}
				else{
					$connection->close(json_encode(['error'=>'токен не найден']));
				}
			};

			\Workerman\Worker::runAll();
		}
		else
			throw new \Exception('не определен протокол');
	}	
}
