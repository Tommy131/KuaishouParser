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

namespace application\kuai\controller;

use owoframe\helper\Helper;
use owoframe\utils\Curl;

class Kuai extends \owoframe\application\ControllerBase
{
	public function __construct()
	{
		self::showUsedTimeDiv(false);
	}

	public function Kuai() : string
	{
		// 指定允许其他域名访问
		header('Access-Control-Allow-Origin: *');
		// 响应类型
		header('Access-Control-Allow-Methods: POST');
		// 响应头设置
		header('Access-Control-Allow-Headers: x-requested-with,content-type');
		header('Content-Type: application/json; charset=UTF-8');

		if(stripos(server('CONTENT_TYPE'), 'json') !== false) {
			$data = json_decode(fetch());
			// var_dump($data);
			$userId = $data->userId;
			$data   = $data->url;
		}
		elseif(stripos(server('CONTENT_TYPE'), 'x-www-form-urlencoded') !== false) {
			$userId = post('userId');
			$data   = post('url');
		} else {
			return '无效的请求格式.';
		}

		$basePath = SAVE_PATH . $userId . DIRECTORY_SEPARATOR;
		if(!is_dir($basePath)) {
			mkdir($basePath, 777, true);
		}
		file_put_contents($basePath . 'serialized.txt', serialize($data));
		return '请求接收成功. 已将文件保存在以下路径: ' . $basePath . 'serialized.txt';
	}
}