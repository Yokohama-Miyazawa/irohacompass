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
	
	/**
	 * AppController の beforeFilter をオーバーライド ※インストールできなくなる為、この function を消さないこと
	 */
	function beforeFilter()
	{
	}
	
	/**
	 * インストール
	 */
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
			
			// apache_get_modules が存在する場合のみ、Apache のモジュールチェックを行う
			if (function_exists('apache_get_modules'))
			{
				// mod_rewrite 存在チェック
				if(!$this->__apache_module_loaded('mod_rewrite'))
				{
					// エラー出力
					$this->err_msg = 'Apache モジュール mod_rewrite がロードされていません';
					$this->error();
					$this->render('error');
					return;
				}
				
				// mod_headers 存在チェック
				if(!$this->__apache_module_loaded('mod_headers'))
				{
					// エラー出力
					$this->err_msg = 'Apache モジュール mod_headers がロードされていません';
					$this->error();
					$this->render('error');
					return;
				}
			}
			
			// mbstring 存在チェック
			if(!extension_loaded('mbstring'))
			{
				// エラー出力
				$this->err_msg = 'PHP モジュール mbstring がロードされていません';
				$this->error();
				$this->render('error');
				return;
			}
			
			// pdo_mysql 存在チェック
			if(!extension_loaded('pdo_mysql'))
			{
				// エラー出力
				$this->err_msg = 'PHP モジュール pdo_mysql がロードされていません';
				$this->error();
				$this->render('error');
				return;
			}
			
			// ユーザテーブルが存在する場合、インストール済みと判断
			if (count($data) > 0)
			{
				$this->render('installed');
			}
			else
			{
				// 各種テーブルの作成
				$this->path = APP.'Config'.DS.'Schema'.DS.'app.sql';
				$err_statements = $this->__executeSQLScript();
				
				// クエリ実行中にエラーが発生した場合、ログファイルにエラー内容を記録
				if(count($err_statements) > 0)
				{
					$this->err_msg = 'インストール実行中にエラーが発生しました。詳細はエラーログ(tmp/logs/error.log)をご確認ください。';
					
					foreach($err_statements as $err)
					{
						$err .= $err."\n";
					}
					
					// エラー出力
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
	
	/**
	 * インストール済みメッセージを表示
	 */
	function installed()
	{
		$this->set('loginURL', "/users/login/");
		$this->set('loginedUser', $this->Auth->user());
	}
	
	/**
	 * インストール完了メッセージを表示
	 */
	function complete()
	{
		$this->set('loginURL', "/users/login/");
		$this->set('loginedUser', $this->Auth->user());
	}
	
	/**
	 * インストールエラーメッセージを表示
	 */
	function error()
	{
		$this->set('loginURL', "/users/login/");
		$this->set('loginedUser', $this->Auth->user());
		$this->set('body', $this->err_msg);
	}
	
	/**
	 * app.sql のクエリの実行
	 */
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
	
	/**
	 * rootアカウントの作成
	 */
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
	
	private function __apache_module_loaded($module_name)
	{
		$modules = apache_get_modules();
		
		foreach($modules as $module)
		{
			if($module == $module_name)
				return true;
		}
		
		return false;
	}
}
?>