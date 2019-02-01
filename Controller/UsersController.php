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
App::uses('Group', 'Group');

/**
 * Users Controller
 *
 * @property User $User
 * @property PaginatorComponent $Paginator
 */
class UsersController extends AppController
{

	public $components = array(
			'Session',
			'Paginator',
			'Security' => array(
				'csrfUseOnce' => false,
				'unlockedActions' => array('login', 'admin_login'),
			),
			'Search.Prg',
			'Cookie',
			'Auth' => array(
					'allowedActions' => array(
							'index',
							'login',
							'logout'
					)
			)
	);

	public function beforeFilter()
	{
		parent::beforeFilter();
		// ユーザー自身による登録とログアウトを許可する
		$this->Auth->allow('add', 'logout');
	}

	public function index()
	{
		$this->redirect("/users_themes");
	}

	public function setting()
	{
		$this->admin_setting();
	}

	public function admin_delete($id = null)
	{
		if(Configure::read('demo_mode'))
			return;
		
		$this->User->id = $id;
		if (! $this->User->exists())
		{
			throw new NotFoundException(__('Invalid user'));
		}
		$this->request->allowMethod('post', 'delete');
		if ($this->User->delete())
		{
			$this->Flash->success(__('ユーザが削除されました'));
		}
		else
		{
			$this->Flash->error(__('ユーザを削除できませんでした'));
		}
		return $this->redirect(array(
				'action' => 'index'
		));
	}

	public function logout()
	{
		$this->Cookie->delete('Auth');
		$this->redirect($this->Auth->logout());
	}

	public function login()
	{
		$username = "";
		$password = "";
		
		// 自動ログイン処理
		// Check cookie's login info.
		if ( $this->Cookie->check('Auth') )
		{
			// クッキー上のアカウントでログイン
			$this->request->data = $this->Cookie->read('Auth');
			
			if ( $this->Auth->login() )
			{
				// 最終ログイン日時を保存
				$this->User->id = $this->Auth->user('id');
				$this->User->saveField('last_logined', date(date('Y-m-d H:i:s')));
				return $this->redirect( $this->Auth->redirect());
			}
			else
			{
				// ログインに失敗した場合、クッキーを削除
				$this->Cookie->delete('Auth');
			}
		}
		
		// 通常ログイン処理
		if ($this->request->is('post'))
		{
			if ($this->Auth->login())
			{
				if (isset($this->data['User']['remember_me']))
				{
					// Remove remember_me data.
					unset( $this->request->data['User']['remember_me']);
					
					// Save login info to cookie.
					$cookie = $this->request->data;
					$this->Cookie->write( 'Auth', $cookie, true, '+2 weeks');
				}
				
				// 最終ログイン日時を保存
				$this->User->id = $this->Auth->user('id');
				$this->User->saveField('last_logined', date(date('Y-m-d H:i:s')));
				$this->writeLog('user_logined', '');
				$this->Session->delete('Auth.redirect');
				$this->redirect($this->Auth->redirect());
			}
			else
			{
				$this->Flash->error(__('ログインID、もしくはパスワードが正しくありません'));
			}
		}
		else
		{
			// デモモードの場合、ログインID、パスワードの初期値を指定
			if(Configure::read('demo_mode'))
			{
				$username = Configure::read('demo_login_id');
				$password = Configure::read('demo_password');
			}
		}
		
		$this->set(compact('username', 'password'));
	}

	public function admin_add()
	{
		$this->admin_edit();
		$this->render('admin_edit');
	}

	// 検索対象のフィルタ設定
	/*
	 * public $filterArgs = array( array('name' => 'name', 'type' => 'value',
	 * 'field' => 'User.name'), array('name' => 'name', 'type' => 'like',
	 * 'field' => 'User.username'), array('name' => 'username', 'type' => 'like',
	 * 'field' => 'Task.title') );
	 */
	public function admin_index()
	{
		if(
			($this->Session->read('Auth.User.role') != 'admin') &&
			($this->Session->read('Auth.User.role') != 'manager')
		)
			$this->redirect("/admin/themes");

		// 検索条件設定
		$this->Prg->commonProcess();
		
		$conditions = $this->User->parseCriteria($this->Prg->parsedParams());
		
		// クラスが指定されている場合、選択中のクラスに設定
		if(isset($this->request->query['group_id']))
			$this->Session->write('Iroha.group_id', intval($this->request->query['group_id']));
		
		$group_id	= (isset($this->request->query['group_id'])) ? $this->request->query['group_id'] : $this->Session->read('Iroha.group_id');
		$username	= (isset($this->request->query['username'])) ? $this->request->query['username'] : "";
		$name		= (isset($this->request->query['name']))     ? $this->request->query['name'] : "";
		
		$conditions = array();
		if($group_id != "")
			$conditions['User.id'] = $this->Group->getUserIdByGroupID($group_id);
		
		if($username != "")
			$conditions['User.username like'] = '%'.$username.'%';
		
		if($name != "")
			$conditions['User.name like'] = '%'.$name.'%';
		
		$this->paginate = array(
			'User' => array(
				'fields' => array('*', 'UserGroup.group_title', 'UserTheme.theme_title'),
				'conditions' => $conditions,
				'limit' => 20,
				'order' => 'created desc',
				'joins' => array(
					array('type' => 'LEFT OUTER', 'alias' => 'UserGroup',
							'table' => '(SELECT ug.user_id, group_concat(g.title order by g.id SEPARATOR \', \') as group_title FROM ib_users_groups ug INNER JOIN ib_groups g ON g.id = ug.group_id GROUP BY ug.user_id)',
							'conditions' => 'User.id = UserGroup.user_id'),
					array('type' => 'LEFT OUTER', 'alias' => 'UserTheme',
							'table' => '(SELECT uc.user_id, group_concat(c.title order by c.id SEPARATOR \', \') as theme_title FROM ib_users_themes uc INNER JOIN ib_themes c ON c.id = uc.theme_id  GROUP BY uc.user_id)',
							'conditions' => 'User.id = UserTheme.user_id')
				))
		);

		try
		{
			$result = $this->paginate();
		}
		catch (Exception $e)
		{
			// 指定したページが存在しなかった場合（主に検索条件変更時に発生）、1ページ目を設定
			$this->request->params['named']['page']=1;
			$result = $this->paginate();
		}

		// 独自カラムの場合、自動でソートされないため、個別の実装が必要
		if (isset($this->request->named['sort']) && $this->request->named['sort'] == 'UserGroup.group_title')
		{
			$result = Set::sort($result, '/UserGroup/group_title', $this->request->named['direction']);
		}

		if (isset($this->request->named['sort']) && $this->request->named['sort'] == 'UserTheme.theme_title')
		{
			$result = Set::sort($result, '/UserTheme/theme_title', $this->request->named['direction']);
		}

		$this->Group = new Group();
		$this->set('groups',   $this->Group->find('list'));
		$this->set('users',    $result);
		$this->set('group_id', $group_id);
		$this->set('name',     $name);

		//debug($this->Paginator->paginate());
	}

	public function admin_edit($id = null)
	{
		if ($this->action == 'admin_edit' && ! $this->User->exists($id))
		{
			throw new NotFoundException(__('Invalid user'));
		}
		
		$username = "";
		
		if ($this->request->is(array(
				'post',
				'put'
		)))
		{
			if(Configure::read('demo_mode'))
				return;
			
			if ($this->request->data['User']['new_password'] !== '')
				$this->request->data['User']['password'] = $this->request->data['User']['new_password'];

			if ($this->User->save($this->request->data))
			{
				$this->Flash->success(__('ユーザ情報が保存されました'));

				unset($this->request->data['User']['new_password']);

				return $this->redirect(array(
						'action' => 'index'
				));
			}
			else
			{
				$this->Flash->error(__('The user could not be saved. Please, try again.'));
			}
		}
		else
		{
			$options = array(
				'conditions' => array(
					'User.' . $this->User->primaryKey => $id
				)
			);
			$this->request->data = $this->User->find('first', $options);
			
			if($this->request->data)
				$username = $this->request->data['User']['username'];
		}

		$this->Group = new Group();
		
		$themes = $this->User->Theme->find('list');
		$groups = $this->Group->find('list');
		
		$this->set(compact('themes', 'groups', 'username'));
	}

	public function admin_setting()
	{
		if ($this->request->is(array(
				'post',
				'put'
		)))
		{
			if(Configure::read('demo_mode'))
				return;
			
			$this->request->data['User']['id'] = $this->Session->read('Auth.User.id');
			
			if($this->request->data['User']['new_password'] != $this->request->data['User']['new_password2'])
			{
				$this->Flash->error(__('入力された「パスワード」と「パスワード（確認用）」が一致しません'));
				return;
			}

			if($this->request->data['User']['new_password'] !== '')
			{
				$this->request->data['User']['password'] = $this->request->data['User']['new_password'];
				
				if ($this->User->save($this->request->data))
				{
					$this->Flash->success(__('パスワードが保存されました'));
				}
				else
				{
					$this->Flash->error(__('The user could not be saved. Please, try again.'));
				}
			}
			else
			{
				$this->Flash->error(__('パスワードを入力して下さい'));
			}
		}
		else
		{
			$options = array(
				'conditions' => array(
						'User.' . $this->User->primaryKey => $this->Session->read('Auth.User.id')
				)
			);
			$this->request->data = $this->User->find('first', $options);
		}
	}

	public function admin_login()
	{
		$this->login();
	}

	public function admin_logout()
	{
		$this->logout();
	}
}
