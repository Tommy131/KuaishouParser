<?php

/*********************************************************************
	 _____   _          __  _____   _____   _       _____   _____
	/  _  \ | |        / / /  _  \ |  _  \ | |     /  _  \ /  ___|
	| | | | | |  __   / /  | | | | | |_| | | |     | | | | | |
	| | | | | | /  | / /   | | | | |  _  { | |     | | | | | |  _
	| |_| | | |/   |/ /    | |_| | | |_| | | |___  | |_| | | |_| |
	\_____/ |___/|___/     \_____/ |_____/ |_____| \_____/ \_____/

	* Copyright (c) 2015-2021 OwOBlog-DGMT.
	* Developer: HanskiJay(Tommy131)
	* Telegram:  https://t.me/HanskiJay
	* E-Mail:    support@owoblog.com
	* GitHub:    https://github.com/Tommy131

**********************************************************************/

declare(strict_types=1);
namespace application\kuai;

use application\kuai\command\KuaiCommand;
use owoframe\MasterManager;

class KuaiApp extends \owoframe\application\AppBase
{
	public function initialize() : void
	{
		if(!defined('SAVE_PATH')) {
			define('SAVE_PATH', STORAGE_A_PATH . 'kuaiApp' . DIRECTORY_SEPARATOR); // 默认将解析到的作品资源保存在框架的资源存储文件目录下;
		}
		$this->setDefaultController('Kuai');
		MasterManager::getInstance()->getUnit('console')->registerCommand(KuaiCommand::getName(), KuaiCommand::class);
	}

	public static function isCLIOnly() : bool
	{
		return false;
	}

	public static function autoTo404Page() : bool
	{
		return true;
	}

	public static function getName() : string
	{
		return 'kuai';
	}

	public static function getAuthor() : string
	{
		return 'HanskiJay';
	}

	public static function getDescription() : string
	{
		return '快手作品解析后端';
	}

	public static function getCookie() : string
	{
		return '(cookie: string)';
	}

	public static function getVersion(): string
	{
		return '1.0.1';
	}
}
?>