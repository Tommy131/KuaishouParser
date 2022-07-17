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

use application\kuai\KuaiApp;
use owoframe\helper\Helper;
use owoframe\exception\OwOFrameException;
use owoframe\object\JSON;
use owoframe\utils\Curl;
use owoframe\utils\TextFormat;

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
				$list  = (array) unserialize($list);
				$total = count($list);

				$this->getLogger()->info('文件加载成功! 总共获取到 ' . $total . ' 个作品, 即将进行下载请求...');

				$current = 0;
				TextFormat::sendClear();
				foreach($list as $id => $item) {
					++$current;
					TextFormat::sendProgressBar($current, $total, "[{$current}/{$total}] 正在请求下载作品 '{$id}'...", function() use ($item, $outputPath, $id) {
						usleep(1500);
						$result = ['status' => null, 'message' => ''];
						if(is_array($item)) {
							foreach($item as $url) {
								$result['status'] = $this->saveFile($url, $outputPath . $id . DIRECTORY_SEPARATOR);
								$result['message'] = "作品ID '{$id}' 保存成功!";
							}
						} else {
							$result['status'] = $this->saveFile($item, $outputPath);
							$result['message'] = "作品ID '{$id}' 保存失败!";
						}
						return $result;
					});
				}

				if(Helper::getOS() === Helper::OS_WINDOWS) {
					system('start ' . $outputPath);
				}
				$this->getLogger()->success("操作成功完成, 已将 '{$total}' 个作品保存在目录 '{$outputPath}' 下.");
			} else {
				$this->getLogger()->error('不存在该文件夹, 无法执行操作.');
			}
		} else {
			$database = new JSON(SAVE_PATH . 'database.json', [], true);
			$newCurl  = function(bool $returnHeader = false) {
				$pc   = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36 Edg/98.0.1108.62';
				$_    = (new Curl())->setUA($pc)->returnBody(true)->returnHeader($returnHeader);
				$ip   = Curl::getRadomIp();
				$_->setHeader([
					'CLIENT-IP: ' . $ip,
					'X-FORWARDED-FOR: ' . $ip,
					'User-Agent: ' . $pc
				]);
				ini_set('user_agent', $pc);
				return $_;
			};

			$authorId    = array_shift($params);
			if(!preg_match('/[0-9a-z_]+/i', $authorId)) {
				$this->getLogger()->error('无效的用户ID! 请检查是否正确输入.');
				return false;
			}
			$forceUpdate = (count($params) > 0) ? array_shift($params) : false;
			if(preg_match('/true|\-t/i', $forceUpdate)) {
				$forceUpdate = true;
				$this->getLogger()->notice('已开启强制更新数据!');
			}
			$autoDownload = (count($params) > 0) ? array_shift($params) : false;
			if(preg_match('/true|\-t/i', $autoDownload)) {
				$autoDownload = true;
				$this->getLogger()->notice('已开启自动下载作品!');
			}

			$this->getLogger()->notice("正在读取用户ID [{$authorId}] 的数据...");


			// 选择平台;
			$baseUrl     = 'https://%s.kuaishou.com/%sgraphql?operationName=';
			$selectedUrl = '';
			$kuaiPlatformSelected = array_shift($params) ?? 'www';
			if($kuaiPlatformSelected === 'kuai') {
				$selectedUrl = sprintf($baseUrl, 'live', 'live_');
			} else {
				$selectedUrl = sprintf($baseUrl, 'www', '');
			}
			// 注意两个平台的Cookie并不相通;
			$cookie = KuaiApp::getCookie();


			// 获取作者信息;
			$operationName = 'visionProfile';
			$query    = "query visionProfile(\$userId: String) {\n  visionProfile(userId: \$userId) {\n    result\n    hostName\n    userProfile {\n      ownerCount {\n        fan\n        photo\n        follow\n        photo_public\n        __typename\n      }\n      profile {\n        gender\n        user_name\n        user_id\n        headurl\n        user_text\n        user_profile_bg_url\n        __typename\n      }\n      isFollowing\n      __typename\n    }\n    __typename\n  }\n}\n";
			$variables = json_encode([
				'userId'  => $authorId
			]);

			$authorData = $newCurl()->setTimeout(120)->setUrl($selectedUrl . $operationName)->setcookieRaw($cookie)->setPostData([
				'variables' => $variables,
				'query'     => $query
			])->exec()->getContent();

			if(!is_bool($authorData)) {
				$authorData = json_decode($authorData);
				$authorData = $authorData->data->{$operationName};
			} else {
				$this->getLogger()->error('[0x00] 数据抓取失败! 请稍后重试 (此用户ID可能无效).');
				return true;
			}

			if($authorData->result === 1) {
				$authorData = $authorData->userProfile;
				if(is_null($authorData->ownerCount) || is_null($authorData->profile)) {
					$this->getLogger()->error("[1x00] Cookie已失效, 无法获取完整数据, 请打开访问 '§3https://{$kuaiPlatformSelected}.kuaishou.com§1' 登录并且重新获取Cookie!");
					$this->getLogger()->error("登录后在浏览器控制台 (§wF12§1) 中输入 `§3document.cookie§1` 即可重新获取Cookie.");
				return true;
				}
				$this->getLogger()->success('读取成功! 以下是获取到的数据:');
				// 作者粉丝;
				$fansCount    = $authorData->ownerCount->fan;
				// 作者作品数;
				$articleCount = $authorData->ownerCount->photo_public;
				$displayName  = $authorData->profile->user_name;
				$gender       = strtoupper($authorData->profile->gender);
				$getGender    = function(int $mode = 0) use($gender) {
					return ($mode === 0) ? (($gender === 'M') ? '他' : '她') : (($gender === 'M') ? '男' : '女');
				};

				// 转存本地数据;
				/* if(!$database->exists($authorId) || $forceUpdate) {
					$database->set('fansCount',    $fansCount);
					$database->set('articleCount', $articleCount);
					$database->set('displayName',  $displayName);
					$database->set('getGender',    $getGender);
					$database->set('updateTime',   microtime(true));
				} */

				$this->getLogger()->info('§8-------------------------------------');
				$this->getLogger()->info("显示名称: §b§3{$displayName}§r§w | 性别: §1{$getGender(1)}§r§w | 粉丝数: §7{$fansCount}§r§w | 作品数量: §7{$articleCount}");
				$this->getLogger()->info($getGender() . "的个人简介: §r§i" . str_replace("\n", "  ", $authorData->profile->user_text));
				$this->getLogger()->info('§8-------------------------------------');
			} else {
				$this->getLogger()->error('[0x01] 数据抓取失败! 请稍后重试 (无效的Cookie).');
				return true;
			}


			$this->getLogger()->notice("尝试获取用户 [{$displayName}|{$authorId}] 的作品集...");
			// 获取当前作者的作品;
			$operationName = 'visionProfilePhotoList';
			$query    = "fragment photoContent on PhotoEntity {\n  id\n  duration\n  caption\n  likeCount\n  viewCount\n  realLikeCount\n  coverUrl\n  photoUrl\n  photoH265Url\n  manifest\n  manifestH265\n  videoResource\n  coverUrls {\n    url\n    __typename\n  }\n  timestamp\n  expTag\n  animatedCoverUrl\n  distance\n  videoRatio\n  liked\n  stereoType\n  profileUserTopPhoto\n  __typename\n}\n\nfragment feedContent on Feed {\n  type\n  author {\n    id\n    name\n    headerUrl\n    following\n    headerUrls {\n      url\n      __typename\n    }\n    __typename\n  }\n  photo {\n    ...photoContent\n    __typename\n  }\n  canAddComment\n  llsid\n  status\n  currentPcursor\n  __typename\n}\n\nquery visionProfilePhotoList(\$pcursor: String, \$userId: String, \$page: String, \$webPageArea: String) {\n  visionProfilePhotoList(pcursor: \$pcursor, userId: \$userId, page: \$page, webPageArea: \$webPageArea) {\n    result\n    llsid\n    webPageArea\n    feeds {\n      ...feedContent\n      __typename\n    }\n    hostName\n    pcursor\n    __typename\n  }\n}\n";

			$variables = json_encode([
				'userId'  => $authorId,
				'pcursor' => '',
				'page'    => 'profile',
				'count'   => $articleCount
			]);

			$articleData = $newCurl()->setUrl($selectedUrl . $operationName)->setcookieRaw($cookie)->setPostData([
				'variables' => $variables,
				'query'     => $query
			])->exec()->getContent();

			if(!is_bool($articleData)) {
				$articleData = json_decode($articleData);
				$articleData = $articleData->data->{$operationName};
			} else {
				$this->getLogger()->error('[0x02] 数据抓取失败! 请稍后重试.');
				return true;
			}

			if($articleData->result === 1) {
				$articleData = $articleData->feeds;
				$this->getLogger()->success("读取成功! 以下是获取到的数据 (共 " . count($articleData) . " 条):");

				foreach($articleData as $k => $v) {
					$article = $v->photo;
					$this->getLogger()->info('§8>>>>>>>>>>>>>>><<<<<<<<<<<<<<<');
					$this->getLogger()->info("作品ID: §3{$article->id}§r§w | 获赞数: §b§6{$article->likeCount}§r§w (§b§1{$article->realLikeCount}§r§w) | 浏览量: §l{$article->viewCount}");
					$this->getLogger()->info("标题: §r{$article->caption}");
					$this->getLogger()->info("视频下载地址: §r{$article->photoUrl}");
					$this->getLogger()->info('§8>>>>>>>>>>>>>>><<<<<<<<<<<<<<<');

					if($autoDownload) {
						$this->getLogger()->info("§8准备下载视频 [§3{$article->id}]...");
						$savePath = SAVE_PATH . $authorId;
						if(!is_dir($savePath)) {
							mkdir($savePath, 755, true);
						}
						$fileName = $savePath . DIRECTORY_SEPARATOR . $article->id . '.mp4';

						// 先检查一次是否存在, 防止重复下载;
						if(!file_exists($fileName)) {
							file_put_contents($fileName, file_get_contents($article->photoUrl));
						}
						// 第二次检测是否下载成功;
						if(!file_exists($fileName)) {
							$this->getLogger()->error('视频下载失败!');
						} else {
							$this->getLogger()->success('视频保存成功!');
						}
					}
				}
				if($autoDownload) {
					system('start ' . $savePath);
					$this->getLogger()->success('已打开视频保存地址.');
				}
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
		// $this->getLogger()->info('正在保存文件: ' . $outputPath . $saveName);
		// $this->getLogger()->info('来自远程URL: ' . $url);
		if(is_file($outputPath . $saveName)) {
			// $this->getLogger()->notice('文件已存在, 跳过下载.');
			return true;
		}
		@file_put_contents($outputPath . $saveName, file_get_contents($url));
		if(!is_file($outputPath . $saveName)) {
			// $this->getLogger()->error('文件下载失败!');
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
