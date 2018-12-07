<?php
/**
 * iroha Compass Project
 *
 * @author        Kotaro Miura
 * @copyright     2015-2018 iroha Soft, Inc. (http://irohasoft.jp)
 * @link          http://irohacompass.irohasoft.jp
 * @license       http://www.gnu.org/licenses/gpl-3.0.en.html GPL License
 */
App::uses('AppController', 'Controller');

class InstallController extends AppController
{
	var $name = 'Install';
	var $uses = array();
	var $helpers = array('Html');
	var $err_msg = '';
	var $db   = null;
	var $path = '';
	
	public $components = array(
			'Session',
			'Auth' => array(
					'allowedActions' => array(
							'index',
							'installed',
							'complete',
							'error',
							'add'
					)
			)
	);
	
	function beforeFilter()
	{
	}
	
	function index()
	{
		try
		{
			App::import('Model','ConnectionManager');
			
			$this->db   = ConnectionManager::getDataSource('default');
			$cdd = new DATABASE_CONFIG();
			
			//debug($db);
			$sql = "SHOW TABLES FROM `".$cdd->default['database']."` LIKE 'ib_users'";
			$data = $this->db->query($sql);
			
			// ユーザテーブルが存在する場合、インストール済みと判断
			if (count($data) > 0)
			{
				$this->render('installed');
			}
			else
			{
				// 各種テーブルの作成
				$this->path = APP.DS.'Config'.DS.'app.sql';
				$err_statements = $this->__executeSQLScript();
				
				// クエリ実行中にエラーが発生した場合、ログファイルにエラー内容を記録
				if(count($err_statements) > 0)
				{
					$this->err_msg = 'インストール実行中にエラーが発生しました。詳細はエラーログ(tmp/logs/error.log)をご確認ください。';
					
					foreach($err_statements as $err)
					{
						$err .= $err."\n";
					}
					
					// デバッグログ
					$this->log($err);
					$this->error();
					$this->render('error');
					return;
				}
				else
				{
					$this->complete();
					$this->render('complete');
				}
			}
			
			// 初期管理者アカウントの存在確認および作成
			$this->__createRootAccount();
		}
		catch (Exception $e)
		{
			$this->err_msg = 'データベースへの接続に失敗しました。<br>Config / database.php ファイル内のデータベースの設定を確認して下さい。';
			$this->error();
			$this->render('error');
		}
	}
	
	function installed()
	{
		$this->set('loginURL', "/users/login/");
		$this->set('loginedUser', $this->Auth->user());
	}
	
	function complete()
	{
		$this->set('loginURL', "/users/login/");
		$this->set('loginedUser', $this->Auth->user());
	}
	
	function error()
	{
		$this->set('loginURL', "/users/login/");
		$this->set('loginedUser', $this->Auth->user());
		$this->set('body', $this->err_msg);
	}
	
	private function __createTables()
	{
		App::import('Model','ConnectionManager');
		$this->db   = ConnectionManager::getDataSource('default');
		return (count($err_statements) == 0);
	}
	
	private function __executeSQLScript()
	{
		$statements = file_get_contents($this->path);
		$statements = explode(';', $statements);
		$err_statements = array();
		
		foreach ($statements as $statement)
		{
			if (trim($statement) != '')
			{
				try
				{
					$this->db->query($statement);
				}
				catch (Exception $e)
				{
					// カラム重複追加エラー
					if($e->errorInfo[0]=='42S21')
						continue;
					
					// ビュー重複追加エラー
					if($e->errorInfo[0]=='42S01')
						continue;
					
					$error_msg = sprintf("%s\n[Error Code]%s\n[Error Code2]%s\n[SQL]%s", $e->errorInfo[2], $e->errorInfo[0], $e->errorInfo[1], $statement);
					$err_statements[count($err_statements)] = $error_msg;
				}
			}
		}
		
		return $err_statements;
	}
	
	private function __createRootAccount()
	{
		// 管理者アカウントの存在確認
		$options = array(
			'conditions' => array(
				'User.role' => 'admin'
			)
		);
		
		$this->loadModel('User');
		$data = $this->User->find('first', $options);
		
		//debug($data);
		if(!$data)
		{
			// 管理者アカウントが１つも存在しない場合、初期管理者アカウント root を作成
			$data = array(
				'username' => 'root',
				'password' => 'irohacompass',
				'name' => 'root',
				'role' => 'admin',
				'email' => 'info@example.com'
			);

			$this->User->save($data);
		}
	}
}
?>