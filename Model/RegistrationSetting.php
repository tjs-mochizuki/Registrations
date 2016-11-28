<?php
/**
 * RegistrationBlocksSetting Model
 *
 * @property Block $Block
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author AllCreator <info@allcreator.net>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('BlockSettingBehavior', 'Blocks.Model/Behavior');
App::uses('BlockBaseModel', 'Blocks.Model');

/**
 * Summary for RegistrationBlocksSetting Model
 */
class RegistrationSetting extends BlockBaseModel {

/**
 * Custom database table name
 *
 * @var string
 */
	public $useTable = false;

/**
 * use behaviors
 *
 * @var array
 */
	public $actsAs = array(
		'Blocks.BlockRolePermission',
		'Blocks.BlockSetting' => array(
			BlockSettingBehavior::FIELD_USE_WORKFLOW,
		),
	);

/**
 * Constructor. Binds the model's database table to the object.
 *
 * @param bool|int|string|array $id Set this ID for this model on startup,
 * can also be an array of options, see above.
 * @param string $table Name of database table to use.
 * @param string $ds DataSource connection name.
 * @see Model::__construct()
 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
 */
	public function __construct($id = false, $table = null, $ds = null) {
		parent::__construct($id, $table, $ds);

		$this->loadModels([
			'Frame' => 'Frames.Frame',
			'Block' => 'Blocks.Block',
		]);
	}

/**
 * getSetting
 *
 * @return array RegistrationBlockSetting data
 */
	public function getSetting() {
		$blockSetting = $this->Block->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'Block.id' => Current::read('Block.id')
			),
		));
		if (! $blockSetting) {
			return $blockSetting;
		}
		return Hash::merge($blockSetting[0], $this->getBlockSetting());
	}

/**
 * Save registration settings
 *
 * @param array $data received post data
 * @return bool True on success, false on failure
 * @throws InternalErrorException
 */
	public function saveRegistrationSetting($data) {
		//トランザクションBegin
		$this->begin();

		// idが未設定の場合は、指定されたblock_keyを頼りに既存レコードがないか調査
		//$existRecord = $this->find('first', array(
		//	'recursive' => -1,
		//	'fields' => 'id',
		//	'conditions' => array(
		//		'block_key' => $data['RegistrationSetting']['block_key'],
		//	)
		//));
		//$data = Hash::merge($existRecord, $data);
		//$data = Hash::remove($data, 'RegistrationSetting.created_user');
		//$data = Hash::remove($data, 'RegistrationSetting.created');
		//$data = Hash::remove($data, 'RegistrationSetting.modified_user');
		//$data = Hash::remove($data, 'RegistrationSetting.modified');

		//バリデーション
		$this->set($data);
		if (! $this->validates()) {
			$this->rollback();
			return false;
		}

		try {
			$this->save($data, false);

			//トランザクションCommit
			$this->commit();

		} catch (Exception $ex) {
			//トランザクションRollback
			$this->rollback();
			throw $ex;
		}
		return true;
	}

/**
 * save block
 *
 * afterFrameSaveやsaveRegistrationから呼び出される
 *
 * @param array $frame frame data
 * @return bool
 * @throws InternalErrorException
 */
	public function saveBlock($frame) {
		// すでに結びついている場合はBlockは作らないでよい
		//var_dump(debug_backtrace());exit();
		// フレームにブロックが配置されてても新規にブロックを作成したいのでコメントアウト byRyujiAMANO
		//if (! empty($frame['Frame']['block_id'])) {
		//	return true;
		//}
		//トランザクションBegin
		$this->begin();

		try {
			// ルームに存在するブロックを探す。登録フォームは常に新規ブロックとする
			//$block = $this->Block->find('first', array(
			//	'conditions' => array(
			//		'Block.room_id' => $frame['Frame']['room_id'],
			//		'Block.plugin_key' => $frame['Frame']['plugin_key'],
			//		'Block.language_id' => $frame['Frame']['language_id'],
			//	)
			//));
			// まだない場合
			//if (empty($block)) {
				// 作成する
				$block = $this->Block->save(array(
					'room_id' => $frame['Frame']['room_id'],
					//'language_id' => $frame['Frame']['language_id'],
					'plugin_key' => $frame['Frame']['plugin_key'],
				));
				if (!$block) {
					throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
				}
				Current::$current['Block'] = $block['Block'];
			//}

			// フレームは更新しない
			//$frame['Frame']['block_id'] = $block['Block']['id'];
			//if (!$this->Frame->save($frame)) {
			//	throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			//}
			//Current::$current['Frame']['block_id'] = $block['Block']['id'];

			$this->commit();
		} catch (Exception $ex) {
			//トランザクションRollback
			$this->rollback();
			CakeLog::error($ex);
			throw $ex;
		}
		return true;
	}

/**
 * save setting
 *
 * afterFrameSaveやsaveQuestionnaireから呼び出される
 *
 * @return bool
 * @throws InternalErrorException
 */
	public function saveSetting() {
		// block settingはあるか
		if ($this->isExsistBlockSetting()) {
			return true;
		}
		// ないときは作る
		$blockSetting = $this->createBlockSetting();
		$ret = $this->saveRegistrationSetting($blockSetting);
		return $ret;
	}
}
