<?php
/**
 * iroha Compass Project
 *
 * @author        Kotaro Miura
 * @copyright     2015-2018 iroha Soft, Inc. (http://irohasoft.jp)
 * @link          http://irohacompass.irohasoft.jp
 * @license       http://www.gnu.org/licenses/gpl-3.0.en.html GPL License
 */

App::uses('AppModel', 'Model');
App::uses('BlowfishPasswordHasher', 'Controller/Component/Auth');

/**
 * User Model
 *
 * @property Group $Group
 * @property Task $Task
 * @property Theme $Theme
 * @property Group $Group
 */
class User extends AppModel
{

	public $validate = array(
		'username' => array(
				array(
						'rule' => 'isUnique',
						'message' => 'ログインIDが重複しています'
				),
				array(
						'rule' => 'alphaNumeric',
						'message' => 'ログインIDは英数字で入力して下さい'
				),
				array(
						'rule' => array(
								'between',
								2,
								32
						),
						'message' => 'ログインIDは5文字以上32文字以内で入力して下さい'
				)
		),
		'name' => array(
			'notBlank' => array(
				'rule' => array(
						'notBlank'
				)
			)
		),
		'role' => array(
			'notBlank' => array(
				'rule' => array(
						'notBlank'
				)
			)
		),
		'password' => array(
				array(
						'rule' => 'alphaNumeric',
						'message' => 'パスワードは英数字で入力して下さい'
				),
				array(
						'rule' => array(
								'between',
								4,
								32
						),
						'message' => 'パスワードは4文字以上32文字以内で入力して下さい'
				)
		),
		'new_password' => array(
				array(
						'rule' => 'alphaNumeric',
						'message' => 'パスワードは英数字で入力して下さい',
						'allowEmpty' => true
				),
				array(
						'rule' => array(
								'between',
								4,
								32
						),
						'required' => false,
						'message' => 'パスワードは4文字以上32文字以内で入力して下さい',
						'allowEmpty' => true
				)
		)
	);

	// The Associations below have been created with all possible keys, those
	// that are not needed can be removed

	/**
	 * belongsTo associations
	 *
	 * @var array
	 */
	public $belongsTo = array(
	);

	/**
	 * hasMany associations
	 *
	 * @var array
	 */
	public $hasMany = array(
			'Task' => array(
					'className' => 'Task',
					'foreignKey' => 'user_id',
					'dependent' => false,
					'conditions' => '',
					'fields' => '',
					'order' => '',
					'limit' => '',
					'offset' => '',
					'exclusive' => '',
					'finderQuery' => '',
					'counterQuery' => ''
			)
	);

	/**
	 * hasAndBelongsToMany associations
	 *
	 * @var array
	 */
	public $hasAndBelongsToMany = array(
			'Theme' => array(
					'className' => 'Theme',
					'joinTable' => 'users_themes',
					'foreignKey' => 'user_id',
					'associationForeignKey' => 'theme_id',
					'unique' => 'keepExisting',
					'conditions' => '',
					'fields' => '',
					'order' => '',
					'limit' => '',
					'offset' => '',
					'finderQuery' => ''
			),
			'Group' => array(
					'className' => 'Group',
					'joinTable' => 'users_groups',
					'foreignKey' => 'user_id',
					'associationForeignKey' => 'group_id',
					'unique' => 'keepExisting',
					'conditions' => '',
					'fields' => '',
					'order' => '',
					'limit' => '',
					'offset' => '',
					'finderQuery' => ''
	 		)
	);

	/*
	function checkCompare($valid_field1, $valid_field2)
	{
		$fieldname = key($valid_field1);

		if ($this->data[$this->name][$fieldname] === $this->data[$this->name][$valid_field2])
		{
			return true;
		}
		return false;
	}
	*/

	public function beforeSave($options = array())
	{
		if (isset($this->data[$this->alias]['password']))
		{
			$this->data[$this->alias]['password'] = AuthComponent::password($this->data[$this->alias]['password']);
		}
		return true;
	}

	// 検索用
	public $actsAs = array(
			'Search.Searchable'
	);

	public $filterArgs = array(
			'username' => array(
					'type' => 'like',
					'field' => 'User.name'
			),
			'themetitle' => array(
					'type' => 'like',
					'field' => 'Theme.title'
			),
			'contenttitle' => array(
					'type' => 'like',
					'field' => 'Task.title'
			),
			'active' => array(
					'type' => 'value'
			)
	);

}
