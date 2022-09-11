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
namespace application\kuai\command;

use owoframe\System;
use application\kuai\KuaiApp;
use application\kuai\api\{Graphql, Url};
use owoframe\exception\OwOFrameException;
use owoframe\network\Curl;
use owoframe\network\Network;
use owoframe\utils\Str;

class KuaiCommand extends \owoframe\console\CommandBase
{

	public function execute(array $params) : bool
	{
		if(!defined('SAVE_PATH')) {
			throw new OwOFrameException('缺失常量定义: SAVE_PATH');
		}
		$savePath = SAVE_PATH;

		if(KuaiApp::isProxyOn()) {
			$this->getLogger()->notice('[KuaiApp] 代理服务已开启!');
		}

		#--------------------------------------------------------------------------#

		// ~自动下载作品选项;
		$autoDownload = array_search('--autoDownload', $params);
		if(!is_bool($autoDownload)) {
			unset($params[$autoDownload]);
			$params = array_values($params);
			$autoDownload = true;
			$this->getLogger()->notice('已开启自动下载作品!');
		} else {
			$autoDownload = false;
		}

		if($this->interceptParameters($params, $autoDownload)) return true;

		// ~需要查询的用户ID;
		$userId = array_shift($params);
		if(!preg_match('/[0-9a-z_]+/i', $userId)) {
			$this->getLogger()->error('无效的用户ID! 请检查是否正确输入.');
			return false;
		}

		// ~读取Cookies;
		$cookie_live = KuaiApp::getCookies('live');
		$cookie_www  = KuaiApp::getCookies('www');

		#-------------------------------------------------------------------------#

		$operation = self::getOperation();

		$getGender = function(string $gender, int $mode = 0) {
			return ($mode === 0) ? (($gender === 'M') ? '他' : '她') : (($gender === 'M') ? '男' : '女');
		};

		// 获取作者信息;
		$this->getLogger()->notice("若多次尝试仍然请求失败, 极大几率是Cookie失效, 无法请求API, 请打开网站 '§3https://live.kuaishou.com§6' 和 '§3https://www.kuaishou.com§6' 登录并且复制Cookie到配置文件.");
		$this->getLogger()->notice("登录后在浏览器控制台 (§wF12§6) 中输入 `§3document.cookie§6` 即可获取Cookie.");
		$this->getLogger()->info("正在查询......");

		$object = $this->Graphql()->setOperationName($operation->getName('searchEID'))->setVariables(['keyword' => $userId])->setQuery($operation->getQuery('searchEID'));
		$result = $object->sendQuery(Url::GRAPHQL_WWW, $cookie_www)->getResult();
		$result = $result->visionSearchUser ?? null;

		if(!is_null($result)) {
			$result = is_array($result->users) ? array_shift($result->users) : [];
			$result = !empty($result) ? $result->user_id : null;
			if(is_string($result)) {
				$userId = $result;
			}
		}

		$object = $this->Graphql()->setOperationName($operation->getName('authorData'))->setVariables(['userId' => $userId])->setQuery($operation->getQuery('authorData'));
		$result = $object->sendQuery(Url::GRAPHQL_WWW, $cookie_www)->getResult();
		$result = $result->visionProfile->userProfile ?? null;

		if(is_null($result)) {
			$this->getLogger()->error('[0x001] 请求失败, 请稍后重试.');
			return true;
		}

		$gender      = $result->profile->gender;
		$displayName = $result->profile->user_name;
		$description = $result->profile->user_text;

		$operation->setPlatform('live');
		$object = $this->Graphql()->setOperationName($operation->getName('authorData'))->setVariables(['userId' => $userId])->setQuery($operation->getQuery('authorData'));
		$result = $object->sendQuery(Url::GRAPHQL_LIVE, $cookie_live)->getResult();
		$result = $result->sensitiveUserInfo ?? null;

		if(is_null($result)) {
			$this->getLogger()->error('[0x002] 请求失败, 请稍后重试.');
			return true;
		}

		$kwaiId        = $result->kwaiId ?? '未定义';
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
		$object = $this->Graphql()->setOperationName($operation->getName('articleData'))->setVariables(['userId' => $userId, 'count' => $articleCount])->setQuery($operation->getQuery('articleData'));
		$result = $object->sendQuery(Url::GRAPHQL_LIVE, $cookie_live)->getResult();
		$result = $result->privateFeeds->list ?? null;

		if(is_null($result) || (count($result) == 0)) {
			$this->getLogger()->error('[0x003] 请求失败, 请稍后重试.');
			return true;
		}

		$this->getLogger()->sendEmpty();
		$this->getLogger()->info($getGender($gender) . '在快手一共发布了 §b§1' . count($result) . '§r§w 个作品!');
		$this->getLogger()->sendEmpty();

		$authorPath = $savePath . $userId . DIRECTORY_SEPARATOR;
		foreach($result as $k => $article) {

			// 跳过错误的时间戳: 可能为直播;
			if($article->timestamp === null) continue;

			$location      = $article->location ?? 'N/A';
			$coordinate    = ($location !== 'N/A') ? "{$location->longitude}° {$location->latitude}°" : '';
			$address       = $article->location->address ?? 'N/A';
			$city          = $article->location->city ?? 'N/A';
			$location      = ($location !== 'N/A') ? "§2{$address} §w(在 §2{$city}§w 标注了 §4{$article->location->title}§w)" : '无';
			$uploadTime    = date('Y-m-d H:i:s', (int) ($article->timestamp / 1000));
			$allowComments = $article->onlyFollowerCanComment ? '§1仅允许关注者评论' : '§5允许';

			// ~单个作品的详细数据查询;
			$operation->setPlatform('www');
			$object = $this->Graphql()->setOperationName($operation->getName('articleData'))->setVariables(['photoId' => $article->id])->setQuery($operation->getQuery('articleData'));
			$result = $object->sendQuery(Url::GRAPHQL_WWW, $cookie_www)->getResult();
			$result = $result->visionVideoDetail ?? null;

			if(is_null($result)) {
				$this->getLogger()->error('[0x004] 请求失败, 请稍后重试.');
				return true;
			}

			$realLikeCount = $result->photo->realLikeCount ?? 'N/A';
			$videoUrl      = $result->photo->photoUrl ?? 'N/A'; // 不要修改, 快手的命名问题;
			// $tags          = $result->tags;

			$this->getLogger()->info('----------------------------------------------------------------');
			$this->getLogger()->info("[No.{$k}] 上传时间: §2{$uploadTime}§r§w | 定位: {$location}§r§w | 经纬度: §g{$coordinate}");
			$this->getLogger()->info("作品ID: §3{$article->id}§r§w | 类型: §3{$article->workType}§r§w | 公开评论: {$allowComments}§r§w | 评论数: §7{$article->counts->displayComment}");
			$this->getLogger()->info("获赞数: §7{$article->counts->displayLike}§r§w (§1{$realLikeCount}§r§w) | 共计观看次数: §7{$article->counts->displayView}");
			$this->getLogger()->info('文章标题及标签: §g' . str_replace("\n", '  ', $article->caption));

			if(in_array($article->workType, ['single', 'vertical', 'multiple'])) {
				$imgUrls = $article->imgUrls;
				$this->getLogger()->info('共计 §1' . count($imgUrls). '§w 张图片!');

				if($autoDownload) {
					foreach($imgUrls as $url) {
						$path = explode('.', $url);
						$path[count($path) - 1] = 'jpg';
						$url  = implode('.', $path);
						$this->download($url, $authorPath . $article->id . DIRECTORY_SEPARATOR, $article->id);
					}
				}
			} else {
				if($autoDownload) {
					$this->download($videoUrl, $authorPath, $article->id);
				}
			}
		}
		if($autoDownload) {
			if(System::getOS() === 'windows') {
				system('start ' . $authorPath);
				$this->getLogger()->success('已打开保存文件夹.');
			}
		}
		$this->getLogger()->info('----------------------------------------------------------------');

		return true;
	}

	/**
	 * 拦截参数方法
	 *
	 * @author HanskiJay
	 * @since  2022-07-24
	 * @param  array   $params
	 * @param  array   $autoDownload
	 * @return boolean
	 */
	private function interceptParameters(array $params, bool $autoDownload = false) : bool
	{
		$headers = [
			'Content-Type'    => 'application/x-www-form-urlencoded; charset=UTF-8',
			'Accept'          => '*/*',
			'Accept-Language' => 'zh-CN,zh;q=0.8,zh-TW;q=0.7,zh-HK;q=0.5,en-US;q=0.3,en;q=0.2',
			'Connection'      => 'keep-alive',
			'Sec-Fetch-Dest'  => 'empty',
			'Sec-Fetch-Mode'  => 'cors',
			'Sec-Fetch-Site'  => 'same-site',
			'Pragma'          => 'no-cache',
			'Cache-Control'   => 'no-cache'
		];
		switch(array_shift($params)) {
			case 'shareId':
				$shareId = array_shift($params) ?? null;
				if(is_null($shareId)) {
					$this->getLogger()->error('无效的分享ID! 请检查是否正确输入.');
				} else {
					$this->getLogger()->info("正在解析单个分享作品 [§2shareId§w=§3{$shareId}§w] ......");

					$url = 'https://v.kuaishou.com/';
					if(!is_null($_t = array_shift($params)) && (strtolower($_t) === '--mode_pc')) {
						$url = 'https://www.kuaishou.com/f/';
						$this->getLogger()->notice("分享平台解析模式已定向至 [PC]");
					}

					$this->initCurl()->returnHeader(true)->setUrl($url . $shareId)->exec()->getContent($h);
					if(preg_match('/^Location: (.*)$/imU', $h, $match)) {
						$result  = parse_url($match[1]);
						$photoId = $result['path'] ?? null;

						if(!is_null($photoId)) {
							$photoId = @end(explode('/', $result['path']));

							$curl = $this->initCurl()
								->setUrl(Url::ARTICLE_DATA)
								->setPostData(['photoId' => $photoId], true)
								->setCookiesInRaw('did=web_')
								->setContentType('application/json; charset=UTF-8')
								->setReferer($match[1])->exec();

							$json = $curl->decodeWithJson(true);
							if($json->result !== 1) {
								$this->getLogger()->error('无法找到有效的数据, 请稍后重试.');
								return true;
							}

							// 获取作者信息;
							$userId       = $json->photo->userEid;
							$originalId   = $json->photo->userId;
							$kwaiId       = $json->photo->kwaiId ?? '未定义';
							$userName     = $json->photo->userName;

							$fansCount    = $json->counts->fanCount;
							$followCount  = $json->counts->followCount;
							$photoCount   = $json->counts->photoCount;

							// 获取视频信息;
							$likeCount    = $json->photo->likeCount;
							$shareCount   = $json->photo->shareCount ?? 'N/A';
							$commentCount = $json->photo->commentCount;
							$viewCount    = $json->photo->viewCount;
							$caption      = $json->photo->caption;

							if(isset($json->atlas)) {
								$type   = 'Pictures';
								$cdn    = $json->atlas->cdn;
								$domain = $cdn[array_rand($cdn, 1)];
								$list   = $json->atlas->list;
							} else {
								$type = 'Video';
								$url  = array_shift($json->photo->mainMvUrls)->url;
							}


							$this->getLogger()->info("获取到的的作品信息如下 (Type: §3{$type}§w):");
							$this->getLogger()->info('----------------------------------------------------------------');
							$this->getLogger()->info("作者ID: §3{$userId}§r§w | 原始ID: §3{$originalId}§r§w | 快手号: §3{$kwaiId}§r§w | 显示名称: §3{$userName}");
							$this->getLogger()->info("粉丝数: §3{$fansCount}§r§w | 关注数: §3{$followCount}§r§w | 已发布的作品: §3{$photoCount}");
							$this->getLogger()->info("作品ID: §3{$photoId}§r§w | 获赞数: §7{$likeCount}§r§w | 共计观看次数: §7{$viewCount}§r§w | 评论数: §7{$commentCount}§r§w | 分享数: §7{$shareCount}");
							$this->getLogger()->info('文章标题及标签: §5' . str_replace("\n", '  ', $caption));
							$this->getLogger()->info('----------------------------------------------------------------');

							if($autoDownload) {
								$savePath = SAVE_PATH . $userId . DIRECTORY_SEPARATOR;
								if($type === 'Video') {
									$this->download($url, $savePath);
								} else {
									$this->getLogger()->info('共计 ' . count($list) . ' 张图片!');
									foreach($list as $url) {
										$this->download("https://{$domain}{$url}", $savePath . $photoId . DIRECTORY_SEPARATOR);
									}
								}
								system('start ' . $savePath);
							}
						}
					} else {
						$this->getLogger()->error('无法找到有效的数据, 请检查分享ID是否有效.');
					}
				}
			return true;

			case '--login':

				$loginUrl    = Url::QR_CODE_LOGIN . 'start';
				$resultUrl   = Url::QR_CODE_LOGIN . 'scanResult';
				$acceptUrl   = Url::QR_CODE_LOGIN . 'acceptResult';
				$list        = [
					'live' => 'kuaishou.live.web',
					'www'  => 'kuaishou.server.web'
				];

				$platform = array_shift($params) ?? 'www';
				$platform = strtolower($platform);
				if(!isset($list[$platform])) {
					$this->getLogger()->error('不支持的平台!');
					return true;
				}
				$headers[] = "Origin: https://{$platform}.kuaishou.com";
				$sid = $list[$platform];

				$verifyResultCode = function(int $code) {return $code === 1;};

				$this->getLogger()->info('正在登录到平台: §3' . $sid);

				$curl = $this->initCurl()->setUrl($loginUrl)->setHeaders($headers)->setPostData(['sid' => $sid])->exec();
				$_ = $curl->decodeWithJson(false);

				if(is_array($_) && $verifyResultCode($_['result'])) {
					extract($_);

					$tmpPath    = SAVE_PATH . 'login_temp' . DIRECTORY_SEPARATOR;
					$nameFormat = 'login_QRCode_%s.png';
					if(!is_dir($tmpPath)) mkdir($tmpPath, 755, true);

					// 写入二维码到文件;
					$path = $tmpPath . sprintf($nameFormat, 'www');
					if(is_file($path)) unlink($path);
					file_put_contents($path, base64_decode($imageData));

					if(System::getOS() === 'windows') {
						system('start ' . $path);
					} else {
						$this->getLogger()->info("二维码已保存在路径 '{$path}' 中, 请手动打开此文件.");
					}
					$this->getLogger()->info("当前Token: §2qrLoginToken=§6{$qrLoginToken}§w|§2qrLoginSignature=§6{$qrLoginSignature}");

					ask('请打开快手客户端扫码, 不要点击确定登录!!! 完成操作请回车 [ENTER]:', 'OK', true, 'warning');
					if(microtime(true) > round($expireTime / 1000, 3)) {
						$this->getLogger()->error('二维码已失效, 请重试.');
						return true;
					}

					$baseData = [
						'channelType'      => $channelType ?? 'UNKNOWN',
						'encryptHeaders'   => $encryptHeaders ?? ''
					];

					$curl = $this->initCurl()->setUrl($resultUrl)->setHeaders($headers)->setPostData($postData = array_merge($baseData, [
						'qrLoginToken'     => $qrLoginToken ?? 'UNKNOWN',
						'qrLoginSignature' => $qrLoginSignature ?? 'UNKNOWN',
					]))->exec();
					$_ = $curl->decodeWithJson();

					if(is_object($_) && $verifyResultCode($_->result)) {
						$eid     = $_->user->eid;
						$version = KuaiApp::getVersion();
						$this->getLogger()->info("欢迎使用KuaishouParser v{$version}! GitHub: https://github.com/Tommy131/KuaishouParser");
						$this->getLogger()->info("如果本程序有帮到你, 请给此项目一个Star以鼓励我继续开发 :)\n");
						$this->getLogger()->success("登录成功! 欢迎你, §6{$_->user->user_name} §w[§3eid:§7{$eid}§w]§5!\n");

						ask('现在请点击授权登录! 完成操作请回车 [ENTER]:', 'OK', true, 'notice');
						$curl = $this->initCurl()->setUrl($acceptUrl)->setHeaders($headers)->setPostData(array_merge($postData, ['sid' => $sid]))->exec();
						$_    = $curl->decodeWithJson();

						if(is_object($_) && $verifyResultCode($_->result)) {
							$curl = $this->initCurl()->setUrl(Url::QR_CODE_CALLBACK)->setHeaders($headers)->setPostData(array_merge($baseData, ['sid' => $sid, 'qrToken' => $_->qrToken]))->exec();
							$_    = $curl->decodeWithJson(false);

							$web_ph_tag = 'kuaishou.%s.web_ph';
							$web_ph_tag = sprintf($web_ph_tag, ($platform === 'www') ? 'server' : 'live');

							$web_st_tag = 'kuaishou.%s.web_st';
							$web_st_tag = sprintf($web_st_tag, ($platform === 'www') ? 'server' : 'live');

							$web_at_tag = 'kuaishou.%s.web.at';
							$web_at_tag = sprintf($web_at_tag, ($platform === 'www') ? 'server' : 'live');

							$userId     = $_['userId'];
							$web_st     = $_[$web_st_tag];
							$authToken  = $_[$web_at_tag];

							if(is_array($_) && $verifyResultCode($_['result'])) {
								// 获取 web_ph;
								if($platform === 'www') {
									$curl = $this->initCurl()->setUrl(Url::WEB_PH_REQUEST)->setHeaders($headers)->returnHeader(true)->setPostData([
										'authToken' => $authToken,
										'sid' => $sid
									])->exec();
									$cookies = $curl->getCookies();
									$web_ph  = $cookies[$web_ph_tag];
								} else {
									$operation = self::getOperation();
									$operation->setPlatform($platform);
									$object = $this->Graphql()->setOperationName($operation->getName('login'))->setQuery($operation->getQuery('login'));
									$object->setVariables(['userLoginInfo' => ['authToken' => $authToken, 'sid' => $sid]]);
									$object->beforeRequestCallback = function($curl) {
										$curl->returnHeader(true);
									};

									$result = $object->sendQuery(Url::GRAPHQL_LIVE)->getResult();
									$result = $result->webLogin->result ?? false;
									if($result !== 1) {
										$this->getLogger()->error('登录出错啦, 该账号可能已经登录过了, 请在服务器会话过期之前换一个账号试一下吧~');
										return true;
									}
									$cookies = $object->getCurl()->getCookies();
									$web_st  = $cookies[$web_st_tag];
									$web_ph  = $cookies[$web_ph_tag];
								}

								$commonCookies = [
									'clientKey' => '65890b29',
									'userId'    => $userId,
									$web_st_tag => $web_st,
									$web_ph_tag => $web_ph
								];

								// 获取did;
								$curl    = $this->initCurl()->setUrl("https://{$platform}.kuaishou.com/profile/{$eid}")->setHeaders($headers)->returnHeader(true)->setCookies($commonCookies)->exec();
								$cookies = $curl->getCookies();
								if(empty($cookies)) {
									$this->getLogger()->error('Cookies获取异常, 请重试.');
									return true;
								}
								$cookies = array_merge($cookies, $commonCookies);

								$cookiesStr = '';

								foreach($cookies as $k => $v) {
									$cookiesStr .= "{$k}={$v}; ";
								}
								$cookiesStr = trim($cookiesStr, '; ');
								KuaiApp::getConfig()->set("cookies.{$platform}", $cookiesStr);
								KuaiApp::getConfig()->save();
								$this->getLogger()->success("已成功更新本地Cookies! §wPlatform: {$platform}");
							}
						} else {
							$this->getLogger()->error('请授权登录!');
							return true;
						}
					} else {
						$msg = (isset($_->result) && ($_->result === 707)) ? '是不是手速太快已经点了授权登录? 请重试.' : '请扫描二维码!';
						$this->getLogger()->error($msg);
					}
				} else {
					$this->getLogger()->error('请求超时, 请重试.');
					return true;
				}
			return true;

			case '--logout':
				$list = [
					'live' => 'kuaishou.live.web',
					'www'  => 'kuaishou.server.web'
				];

				$platform = array_shift($params) ?? 'www';
				$platform = strtolower($platform);
				if(!isset($list[$platform])) {
					$this->getLogger()->error('不支持的平台!');
					return true;
				}
				$sid  = $list[$platform];
				$curl = $this->initCurl()->setUrl(Url::LOGOUT)->setHeaders($headers)->setPostData(['sid' => $sid])->returnHeader(true)->setCookiesInRaw(KuaiApp::getCookies($platform))->exec();
				$____ = json_decode($curl->getContent());
				if($____->result === 1) {
					KuaiApp::getConfig()->set("cookies.{$platform}", '(cookies: string)');
					KuaiApp::getConfig()->save();
					$this->getLogger()->info('退出成功.');
				}
			return true;
		}
		return false;
	}

	/**
	 * Graphql操作类
	 *
	 * @author HanskiJay
	 * @since  2022-07-24
	 * @return object
	 */
	private static function getOperation() : object
	{
		return new class {
			private $platform = 'www';
			private $names = [
				'authorData' => [
					'www'  => 'visionProfile',
					'live' => 'sensitiveUserInfoQuery'
				],
				'articleData' => [
					'www'  => 'visionVideoDetail',
					'live' => 'privateFeedsQuery'
				],
				'searchEID' => [
					'www' => 'graphqlSearchUser',
					'www2' => 'visionVideoDetail'
				],
				'login' => [
					'www'  => 'userInfoQuery',
					'live' => 'UserLogin'
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
						'www'  => 'visionProfile.graphql',
						'live' => 'sensitiveUserInfoQuery.graphql'
					],
					'articleData' => [
						'www'  => 'getRealVideoUrl.graphql',
						'live' => 'privateFeedsQuery.graphql'
					],
					'searchEID' => [
						'www' => 'graphqlSearchUser.graphql',
						'www2' => 'visionVideoDetail.graphql'
					],
					'login' => [
						'www'  => 'UserLoginServer.graphql',
						'live' => 'UserLoginLive.graphql'
					]
				];
				$query = $query[$type][$this->platform] ?? null;
				if(!is_null($query) && is_file($file = $appPath . $query)) {
					$query = file_get_contents($file);
				}
				return $query;
			}

			public function setPlatform(string $platform = 'www') : void
			{
				$this->platform = $platform;
			}
		};
	}

	/**
	 * 初始化Curl请求
	 *
	 * @param  boolean     $useRadomIp
	 * @param  boolean     $returnBody
	 * @param  boolean     $returnHeader
	 * @author HanskiJay
	 * @since  2022-07-17
	 * @return Curl
	 */
	public function initCurl(bool $useRadomIp = true, bool $returnBody = true, bool $returnHeader = false) : Curl
	{
		$curl = (new Curl())->returnBody($returnBody)->returnHeader($returnHeader)->userAgentInPC();

		if($useRadomIp) {
			$radomIp = Network::getRadomIp();
			$curl->setHeaders([
				'CLIENT-IP: ' . $radomIp,
				'X-FORWARDED-FOR: ' . $radomIp
			]);
		}

		// 检测是否开启了代理;
		if(KuaiApp::isProxyOn()) {
			$array = KuaiApp::useProxyServer('data');
			$curl->useProxy($array[0], $array[1]);
		}
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

		if(!Str::isDomain($pu['host'])) {
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

	/**
	 * 下载文件
	 *
	 * @author HanskiJay
	 * @since  2022-07-24
	 * @param  string $url
	 * @param  string $savePath
	 * @param  string $saveName
	 * @return void
	 */
	private function download(string $url, string $savePath, string $saveName = '') : void
	{
		$this->getLogger()->debug('正在下载: ' . $url);
		if(!$this->saveFile($url, $savePath, $saveName, $status)) {
			$this->getLogger()->error(rtrim('保存失败! ' . ($status ?? '')));
		} else {
			if(!is_null($status)) {
				$this->getLogger()->info($status);
			} else {
				$this->getLogger()->success('保存成功!');
			}
		}
	}

	public function Graphql() : Graphql
	{
		return (new Graphql($this->initCurl()));
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