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
namespace application\kuai\command;

use owoframe\helper\Helper;
use owoframe\exception\OwOFrameException;
use owoframe\utils\Curl;

class KuaiCommand extends \owoframe\console\CommandBase
{
	public function execute(array $params) : bool
	{
		if(!defined('SAVE_PATH')) {
			throw new OwOFrameException('缺失常量定义: SAVE_PATH');
		}
		$savePath = SAVE_PATH;

		if(isset($params[0]) && is_string($params[0]) && (strtolower($params[0]) === 'file')) {
			$authorId = array_shift($params);
			$authorId = array_shift($params);
			if(!is_string($authorId)) {
				$this->getLogger()->info('请输入一个有效的AuthorID. 用法: ' . self::getUsage() . ' file [string:AuthorId]');
				return false;
			}
			$outputPath = $savePath . $authorId . DIRECTORY_SEPARATOR;
			if(is_dir($outputPath)) {
				$list = file_get_contents($outputPath . 'serialized.txt');
				if(!is_serialized($list)) {
					$this->getLogger()->error('文件格式错误, 请确认文件数据是否已经标准序列化.');
					return true;
				}
				$list = (array) unserialize($list);

				$this->getLogger()->success('文件加载成功! 总共获取到 ' . count($list) . ' 个作品, 即将进行下载请求...');
				foreach($list as $id => $item) {
					if(is_array($item)) {
						$this->getLogger()->notice('正在请求下载作品 [' . $id . '] ...');
						foreach($item as $url) {
							$this->saveFile($url, $outputPath . $id . DIRECTORY_SEPARATOR);
							usleep(1500);
						}
					} else {
						$this->saveFile($item, $outputPath);
						usleep(1500);
					}
				}
				if(Helper::getOS() === Helper::OS_WINDOWS) {
					system('start ' . $outputPath);
				}
				$this->getLogger()->success("操作成功完成, 已将 '" . count($list) . "' 个作品保存在目录 '{$outputPath}' 下.");
			} else {
				$this->getLogger()->error('不存在该文件夹, 无法执行操作.');
			}
			return true;
		}

		$shareId = array_shift($params);
		if(empty($shareId)) {
			$this->getLogger()->info('请输入一个有效的分享ID. 用法: ' . self::getUsage() . ' [string:shareId] [int:mode[0|1]');
			return false;
		}

		$baseUrl  = 'https://live.kuaishou.com/u/';
		$shareUrl = 'https://v.kuaishou.com/';
		// $shareUrl = 'https://v.kuaishouapp.com/';


		$ip = Curl::getRadomIp();
		$newCurl = function(int $mode = 1) use ($ip) {
			$this->getLogger()->info('已选择UA: ' . (($mode === 1) ? 'PC' : 'Mobile'));
			$mobile = 'Mozilla/5.0 (iPhone; CPU iPhone OS 10_3_1 like Mac OS X) AppleWebKit/603.1.30 (KHTML, like Gecko) Version/10.0 Mobile/14E304 Safari/602.1 Edg/98.0.4758.102';
			$pc     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36 Edg/98.0.1108.62';
			$_      = (new Curl())->setUA(($mode === 0) ? $mobile : $pc)->returnBody(true)->returnHeader(true);
			$_->setHeader([
				'CLIENT-IP: ' . $ip,
				'X-FORWARDED-FOR: ' . $ip
			]);
			ini_set('user_agent', ($mode === 0) ? $mobile : $pc);
			return $_;
		};

		$this->getLogger()->info("正在解析分享ID: [shareId={$shareId}]");
		$mode    = array_shift($params) ?? 1;
		$curl    = $newCurl($mode)->setUrl($shareUrl . $shareId)->exec();
		$result  = curl_getinfo($curl->getResource());
		// var_dump($curl->getContent());exit;

		if(isset($result['redirect_url'])) {
			$data = parse_url($result['redirect_url']);
			$data = $data['query'] ?? null;
			if(is_null($data)) {
				$this->getLogger()->error("分享ID [shareId={$shareId}] 无效!");
				return true;
			}
			// $this->getLogger()->debug('Raw Url: ' . $result['redirect_url']);
			parse_str($data, $params);
			$url  = $baseUrl . $params['userId'] . '/' . $params['photoId'] . '?' . $data;
			$this->getLogger()->success("解析成功~ 用户ID: {$params['userId']} | 图片集作品ID: {$params['photoId']}");
			$this->getLogger()->debug("解析地址: {$url}");
			if(ask('是否继续执行下载操作?', 'Y', 'warning') !== 'Y') {
				$this->getLogger()->info('已终止.');
				return true;
			}
			$this->getLogger()->notice("解析成功, 正在尝试获取目标图床, 此操作过程预计在10分钟以内完成, 请耐心等待...");
			// $page = file_get_contents($url);
			$page = $newCurl($mode)->setUrl($url)->exec()->getContent();
			if(!$page) {
				$this->getLogger()->error("解析失败, 可能请求超时, 请稍后重试.");
				return true;
			}
			// var_dump($page);exit;
			$outputPath = $savePath . $params['userId'] . DIRECTORY_SEPARATOR . $params['photoId'] . DIRECTORY_SEPARATOR;
			$count = 0;

			if(preg_match_all('/type="video\/mp4" src="(.*)"/imU', $page, $matches)) {
				foreach($matches[1] as $image) {
					if((stripos($image, 'upic/') !== false) || (stripos($image, 'upic') !== false)) {
						if($this->saveFile($image, $outputPath)) {
							$count++;
							usleep(1500);
						}
					}
				}
			}
			elseif(preg_match_all('/img src="(.*)"/imU', $page, $matches)) {
				foreach($matches[1] as $image) {
					// var_dump($matches);
					if((stripos($image, 'ufile/atlas') !== false) || (stripos($image, 'upic') !== false)) {
						if($this->saveFile($image, $outputPath)) {
							$count++;
							usleep(1500);
						}
					}
				}
			}
			elseif(preg_match_all('/img class="play-image" src="(.*)"/imU', $page, $matches)) {
				foreach($matches[2] as $image) {
					if((stripos($image, 'ufile/atlas') !== false) || (stripos($image, 'upic') !== false)) {
						if($this->saveFile($image, $outputPath)) {
							$count++;
							usleep(1500);
						}
					}
				}
			} else {
				$this->getLogger()->error('无法匹配到任何资源.');
			}
			if($count > 0) {
				$this->getLogger()->success("操作成功完成, 已将 '{$count}' 个文件保存在目录 '{$outputPath}' 下.");
				if(Helper::getOS() === Helper::OS_WINDOWS) {
					system('start ' . $outputPath);
				}
			} else {
				$this->getLogger()->warning('文件保存失败, 尝试使用移动UA [mode=0] 有几率能够成功解析资源地址.');
			}
		}
		return true;
	}

	private function saveFile(string $url, string $outputPath) : bool
	{
		if(!is_dir($outputPath)) {
			mkdir($outputPath, 777, true);
		}
		$saveName = explode('/', parse_url($url)['path']);
		$saveName = end($saveName);
		$this->getLogger()->info('正在保存文件: ' . $outputPath . $saveName);
		$this->getLogger()->info('来自远程URL: ' . $url);
		if(is_file($outputPath . $saveName)) {
			$this->getLogger()->notice('文件已存在, 跳过下载.');
			return true;
		}
		@file_put_contents($outputPath . $saveName, file_get_contents($url));
		if(!is_file($outputPath . $saveName)) {
			$this->getLogger()->error('文件下载失败!');
			return false;
		} else {
			return true;
		}
	}

	public static function getAliases() : array
	{
		return ['k', '-k'];
	}

	public static function getName() : string
	{
		return 'kuai';
	}

	public static function getDescription() : string
	{
		return '快手API指令';
	}
}
