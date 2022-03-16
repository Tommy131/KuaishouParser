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
		$this->setDefaultController('Kuai');
		MasterManager::getInstance()->getManager('console')->registerCommand(KuaiCommand::getName(), new KuaiCommand());
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
		return '快手API';
	}
}
?>