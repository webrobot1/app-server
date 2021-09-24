<?php
namespace Edisom\App\server\controller;

class BackendController extends \Edisom\Core\Backend
{	
	function index()
	{		
		$this->view->assign('cron', \Edisom\Core\Cli::get('\\Edisom\\App\\server\\model\\ServerModel', 'start', null, 'server/main.log'));
		$this->view->assign('status', $this->model->status());
		$this->view->display('main.html');
	}	
	
	function logs()
	{		
		$this->view->assign('log', $this->model->getLog());
		$this->view->display('logs.html');
	}	
	
	function redis()
	{		
/* 		$it = NULL;
		do {
			// Scan for some keys
			$arr_keys = $this->model::redis()->scan($it);

			// Redis may return empty results, so protect against that
			if ($arr_keys !== FALSE) {
				foreach($arr_keys as $str_key) {
					echo "$str_key <br/>";
				}
			}
		} while ($it > 0); */
	}		
	
	// тк у нас supervisor  сервер запустится им после остановки
	function stop()
	{		
		$this->model->stop();
		$this->redirect();
	}	
		
	function restart()
	{		
		$this->model->restart();
		$this->redirect();
	}							
}