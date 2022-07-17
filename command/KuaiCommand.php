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

		#--------------------------------------------------------------------------#

		// ~需要查询的用户ID;
		$userId = array_shift($params);
		if(!preg_match('/[0-9a-z_]+/i', $userId)) {
			$this->getLogger()->error('无效的用户ID! 请检查是否正确输入.');
			return false;
		}

		// ~自动下载作品选项;
		$autoDownload = (count($params) > 0) ? array_shift($params) : false;
		if(preg_match('/true|\-t/i', $autoDownload)) {
			$autoDownload = true;
			$this->getLogger()->notice('已开启自动下载作品!');
		} else {
			$autoDownload = false;
		}

		// ~读取Cookies;
		$cookie_live = KuaiApp::getCookie('live');
		$cookie_www  = KuaiApp::getCookie('www');

		// ~不同的请求接口;
		$graphql_live = 'https://live.kuaishou.com/live_graphql';
		$graphql_www  = 'https://www.kuaishou.com/graphql';

		#-------------------------------------------------------------------------#

		$operation = new class($this) {
			private $platform = 'www';
			private $names = [
				'authorData' => [
					'www'  => 'visionProfile',
					'live' => 'sensitiveUserInfoQuery'
				],
				'articleData' => [
					'www'  => 'visionVideoDetail',
					'live' => 'privateFeedsQuery'
				]
			];

			public function getName(string $type) : ?string
			{
				return $this->names[$type][$this->platform] ?? null;
			}

			public function getQuery(string $type) : ?string
			{
				$appPath = KuaiApp::getAppPath() . 'graphql' . DIRECTORY_SEPARATOR;
				$query = [
					'authorData' => [
						'www'  => file_get_contents($appPath . 'visionProfile.graphql'),
						'live' => file_get_contents($appPath . 'sensitiveUserInfoQuery.graphql')
					],
					'articleData' => [
						'www'  => file_get_contents($appPath . 'visionVideoDetail.graphql'),
						'live' => file_get_contents($appPath . 'privateFeedsQuery.graphql')
					]
				];
				return $query[$type][$this->platform] ?? null;
			}

			public function setPlatform(string $platform = 'www') : void
			{
				$this->platform = $platform;
			}
		};

		$getGender = function(string $gender, int $mode = 0) {
			return ($mode === 0) ? (($gender === 'M') ? '他' : '她') : (($gender === 'M') ? '男' : '女');
		};

		// 获取作者信息;
		$this->getLogger()->notice("若多次尝试仍然请求失败, 极大几率是Cookie失效, 无法请求API, 请打开网站 '§3https://live.kuaishou.com§6' 和 '§3https://www.kuaishou.com§6' 登录并且复制Cookie到 `KuaiApp` 的方法内.");
		$this->getLogger()->notice("登录后在浏览器控制台 (§wF12§6) 中输入 `§3document.cookie§6` 即可获取Cookie.");
		$this->getLogger()->info("正在查询......");

		$object = $this->Graphql($this)->setOperationName($operation->getName('authorData'))->setVariables(['userId' => $userId])->setQuery($operation->getQuery('authorData'));
		$result = $object->sendQuery($graphql_www, $cookie_www)->getResult();

		if(is_null($result)) {
			$this->getLogger()->error('[0x001] 请求失败, 请稍后重试.');
			return true;
		}

		$result      = $result->visionProfile->userProfile;
		$gender      = $result->profile->gender;
		$displayName = $result->profile->user_name;
		$description = $result->profile->user_text;

		$operation->setPlatform('live');
		$object = $this->Graphql($this)->setOperationName($operation->getName('authorData'))->setVariables(['userId' => $userId])->setQuery($operation->getQuery('authorData'));
		$result = $object->sendQuery($graphql_live, $cookie_live)->getResult();

		if(is_null($result)) {
			$this->getLogger()->error('[0x002] 请求失败, 请稍后重试.');
			return true;
		}

		$result        = $result->sensitiveUserInfo;
		$kwaiId        = $result->kwaiId;
		$originUserId  = $result->originUserId;
		$constellation = $result->constellation;
		$cityName      = $result->cityName;

		$fansCount     = $result->counts->fan;
		$articleCount  = $result->counts->photo;
		$privateCount  = $result->counts->private ?? 0;

		$this->getLogger()->info("--------------------[UserId:§3{$userId}§w]--------------------");
		$this->getLogger()->info($getGender($gender) . "所在的城市: §b§l{$cityName}§r§w | 性别: §1{$getGender($gender, 1)}§r§w | 星座: §7{$constellation}");
		$this->getLogger()->info("快手号: §3{$kwaiId}§r§w | 原始ID: §3{$originUserId}§r§w | 显示名称: §b§3{$displayName}§r§w | 粉丝数: §7{$fansCount}§r§w | 作品数量: §7{$articleCount}§r§w | 私有作品: §7{$privateCount}");
		$this->getLogger()->info($getGender($gender) . '的个人简介: §r§i' . str_replace("\n", '  ', $description));
		$this->getLogger()->info('----------------------------------------------------------------');



		$operation->setPlatform('live');
		$object = $this->Graphql($this)->setOperationName($operation->getName('articleData'))->setVariables(['userId' => $userId, 'count' => $articleCount])->setQuery($operation->getQuery('articleData'));
		$result = $object->sendQuery($graphql_live, $cookie_live)->getResult();

		if(is_null($result)) {
			$this->getLogger()->error('[0x003] 请求失败, 请稍后重试.');
			return true;
		}

		$result = $result->privateFeeds->list;
		$this->getLogger()->sendEmpty();
		$this->getLogger()->info($getGender($gender) . '在快手一共发布了 §b§1' . count($result) . '§r§w 个作品!');
		$this->getLogger()->sendEmpty();

		$authorPath = $savePath . $userId . DIRECTORY_SEPARATOR;
		foreach($result as $article) {
			$location      = $article->location ?? 'N/A';
			$coordinate    = ($location !== 'N/A') ? "{$location->longitude}° {$location->latitude}°" : '';
			$location      = ($location !== 'N/A') ? "§2{$article->location->address} §w(在 §2{$article->location->city}§w 标注了 §4{$article->location->title}" : '无';
			$uploadTime    = date('Y-m-d H:i:s', $article->timestamp / 1000);
			$allowComments = $article->onlyFollowerCanComment ? '§1仅允许关注者评论' : '§5允许';

			// ~单个作品的详细数据查询;
			$operation->setPlatform('www');
			$object = $this->Graphql($this)->setOperationName($operation->getName('articleData'))->setVariables(['photoId' => $article->id])->setQuery($operation->getQuery('articleData'));
			$result = $object->sendQuery($graphql_www, $cookie_www)->getResult();

			if(is_null($result)) {
				$this->getLogger()->error('[0x004] 请求失败, 请稍后重试.');
				return true;
			}

			$result        = $result->visionVideoDetail;
			$realLikeCount = $result->photo->realLikeCount ?? 'N/A';
			$videoUrl      = $result->photo->photoUrl ?? 'N/A'; // 不要修改, 快手的命名问题;
			// $tags          = $result->tags;

			$this->getLogger()->info('----------------------------------------------------------------');
			$this->getLogger()->info("上传时间: §2{$uploadTime}§r§w | 定位: {$location}§r§w | 经纬度: §g{$coordinate}");
			$this->getLogger()->info("作品ID: §3{$article->id}§r§w | 类型: §3{$article->workType}§r§w | 公开评论: {$allowComments}§r§w | 评论数: §7{$article->counts->displayComment}");
			$this->getLogger()->info("获赞数: §7{$article->counts->displayLike}§r§w (§1{$realLikeCount}§r§w) | 共计观看次数: §7{$article->counts->displayView}");
			$this->getLogger()->info('文章标题及标签: §g' . str_replace("\n", '  ', $article->caption));

			if($article->workType === 'vertical') {
				$imgUrls = $article->imgUrls;
				$this->getLogger()->info('共计 §1' . count($imgUrls). '§w 张图片!');

				if($autoDownload) {
					foreach($imgUrls as $url) {
						$path = explode('.', $url);
						$path[count($path) - 1] = 'jpg';
						$url  = implode('.', $path);
						$this->getLogger()->debug('正在下载: ' . $url);
						if(!$this->saveFile($url, $authorPath . $article->id . DIRECTORY_SEPARATOR, $article->id, $status)) {
							$this->getLogger()->error(rtrim('保存失败! ' . ($status ?? '')));
						} else {
							if(!is_null($status)) {
								$this->getLogger()->info($status);
								continue;
							}
							$this->getLogger()->success('保存成功!');
						}
					}
				}
			} else {
				if($autoDownload) {
					$this->getLogger()->debug('正在下载: ' . $videoUrl);
					if(!$this->saveFile($videoUrl, $authorPath, $article->id, $status)) {
						$this->getLogger()->error(rtrim('保存失败! ' . ($status ?? '')));
					} else {
						if(!is_null($status)) {
							$this->getLogger()->info($status);
							continue;
						}
						$this->getLogger()->success('保存成功!');
					}
				}
			}
		}
		if($autoDownload) {
			if(Helper::getOS() === 'windows') {
				system('start ' . $authorPath);
				$this->getLogger()->success('已打开保存文件夹.');
			}
		}
		$this->getLogger()->info('----------------------------------------------------------------');

		return true;
	}


	/**
	 * 一个简单的Graphql请求处理方法
	 *
	 * @author HanskiJay
	 * @since  2022-07-17
	 * @return object
	 */
	private function Graphql() : object
	{
		return new class($this)
		{
			private $command;

			/* 原始数据 */
			public $encoded = '';
			public $operationName, $query = '';
			public $variables = [];

			/* 处理结果 */
			public $result = null;

			public function __construct(KuaiCommand $command)
			{
				$this->command = $command;
			}

			public function setOperationName(string $name)
			{
				$this->operationName = $name;
				return $this;
			}

			public function setVariables(array $variables)
			{
				$this->variables = array_merge($variables, $this->variables);
				return $this;
			}

			public function setQuery(string $query)
			{
				$this->query = $query;
				return $this;
			}

			public function encode() : string
			{
				return $this->encoded = json_encode([
					'operationName' => $this->operationName,
					'variables'     => $this->variables,
					'query'         => $this->query
				]);
			}

			public function sendQuery(string $url, string $cookie = '', int $timeout = 60)
			{
				$curl    = $this->command->initCurl()->setUrl($url)->setTimeOut($timeout)->setCookieRaw($cookie);
				$content = $curl->setPostDataRaw($this->encode())->exec()->getContent();

				if(!is_bool($content)) {
					$content = json_decode($content);
					if(is_object($content)) {
						$this->result = $content;
					}
				}
				return $this;
			}

			public function getResult() : ?object
			{
				if(isset($this->result->data)) {
					$this->result = $this->result->data;
				}
				return $this->result ?? null;
			}
		};
	}

	/**
	 * 初始化Curl请求
	 *
	 * @param  string|null $userAgent
	 * @param  boolean     $returnBody
	 * @param  boolean     $useRadomIp
	 * @param  boolean     $returnHeader
	 * @author HanskiJay
	 * @since  2022-07-17
	 * @return Curl
	 */
	public function initCurl(?string $userAgent = null, bool $returnBody = true, bool $useRadomIp = true, bool $returnHeader = false) : Curl
	{
		$userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.5060.114 Safari/537.36 Edg/103.0.1264.62';
		$curl      = (new Curl())->setUA($userAgent)->returnBody($returnBody)->returnHeader($returnHeader);

		if($useRadomIp) {
			$radomIp   = Curl::getRadomIp();
			$curl->setHeader([
				'CLIENT-IP: ' . $radomIp,
				'X-FORWARDED-FOR: ' . $radomIp
			]);
		}

		$curl->setHeader([
			'User-Agent: ' . $userAgent,
			'Content-Type: application/json; charset=UTF-8'
		]);

		ini_set('user_agent', $userAgent);
		return $curl;
	}

	/**
	 * 保存文件到本地
	 *
	 * !Attention: 文件保存名称的优先级为 parse_url($url)['path] > $saveName
	 *
	 * @param  string  $url
	 * @param  string  $outputPath
	 * @param  string  $saveName
	 * @param  string  &$status
	 * @author HanskiJay
	 * @since  2022-07-17
	 * @return boolean
	 */
	private function saveFile(string $url, string $outputPath, string $saveName = '', ?string &$status = null) : bool
	{
		if(!is_dir($outputPath)) {
			mkdir($outputPath, 777, true);
		}

		$pu = parse_url($url);
		if(!empty($pu['path'])) {
			$saveName = explode('/', $pu['path']);
			$saveName = end($saveName);
		} else {
			$status = '无法通过URL设置文件名!';
		}

		if(!Helper::isDomain($pu['host'])) {
			$status = '无效的网址!';
			return false;
		}

		if(is_file($outputPath . $saveName)) {
			$status = '文件已存在, 跳过下载.';
			return true;
		}
		@file_put_contents($outputPath . $saveName, file_get_contents($url));
		return is_file($outputPath . $saveName);
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