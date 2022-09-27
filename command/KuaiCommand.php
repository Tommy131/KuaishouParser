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

use application\kuai\KuaiApp;
use application\kuai\api\{Feeds, Graphql, User};
use owoframe\exception\OwOFrameException;
use owoframe\network\{Curl, Network};
use owoframe\System;
use owoframe\utils\Str;
use owoframe\utils\TextFormat;

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


        $getGender = fn(string $gender, int $mode = 0) => ($mode === 0) ? (($gender === 'M') ? '他' : '她') : (($gender === 'M') ? '7男' : '1女');

		if($this->interceptParameters($params, $autoDownload)) return true;

		// ~需要查询的用户ID;
		$userId = array_shift($params);
		if(!$userId || !preg_match('/[0-9a-z_]+/i', $userId)) {
			$this->getLogger()->error('无效的用户ID! 请检查是否正确输入.');
			return false;
		}

		echo TextFormat::parse('§w[KuaiApp] 若多次尝试仍然请求失败, 极大几率是Cookie失效, 无法请求API, 请打开网站 ' . PHP_EOL .
			 "          '§3https://live.kuaishou.com§w' 和 '§3https://www.kuaishou.com§w'" . PHP_EOL .
			 '          登录并且复制Cookie到配置文件.' . PHP_EOL .
			 '[KuaiApp] 登录后在浏览器控制台 (§6F12§w) 中输入 `§3document.cookie§w` 即可获取Cookie.' . PHP_EOL .
		'[KuaiApp] 正在查询......' . PHP_EOL);

		$result = User::search($userId);
		if(!$result) {
            $this->getLogger()->error('[0x001] 请求失败, 请稍后重试.');
            return true;
		}
		$result = is_array($result) ? array_shift($result) : [];
        $result = !empty($result) ? $result->user_id : null;
        if(is_string($result)) {
            $userId = $result;
        }

		$result = User::queryUserId($userId);
		if(!$result) {
            $this->getLogger()->error('[0x002] 请求失败, 请稍后重试.');
            return true;
        }
		extract($result);
		$feedCount = (int) $feedCount;
		$_gender   = $getGender($gender);

		$this->getLogger()->sendEmpty();
        $this->getLogger()->info("--------------------[UserId:§3{$userId}§w]--------------------");
        $this->getLogger()->info($_gender . "所在的城市: §b§l{$cityName}§r§w | 性别: §{$getGender($gender, 1)}§r§w | 星座: §7{$constellation}");
        $this->getLogger()->info("快手号: §3{$kwaiId}§r§w | 原始ID: §3{$originUserId}§r§w | 显示名称: §b§3{$displayName}§r§w | 粉丝数: §7{$fansCount}§r§w | 作品数量: §7{$feedCount}§r§w | 私有作品: §7{$privateCount}");
        $this->getLogger()->info($_gender . '的个人简介: §r§i' . str_replace("\n", '  ', $description));
        $this->getLogger()->info('----------------------------------------------------------------');

		if($feedCount <= 0) {
			$this->getLogger()->info('§6这个家伙没有任何的作品, 到此为止啦, 再查下去就不礼貌了~');
			return true;
		}

		$result = Feeds::getPureDataSet($userId, $feedCount);
		if(!$result) {
            $this->getLogger()->error('[0x003] 请求失败, 请稍后重试.');
            return true;
        }
		extract($result);

		$this->getLogger()->sendEmpty();
        $this->getLogger()->info('成功获取了' . $_gender . '在快手发布的 §b§1' . count($result) . '§r§w 个作品!');
        $this->getLogger()->sendEmpty();

        $authorPath = $savePath . $userId . DIRECTORY_SEPARATOR;
        foreach($result as $k => $feed) {

            // 跳过错误的时间戳: 可能为直播;
            if($feed->timestamp === null) continue;

            $location      = $feed->location ?? 'N/A';
            $coordinate    = ($location !== 'N/A') ? "{$location->longitude}° {$location->latitude}°" : '§g无';
            $address       = $feed->location->address ?? 'N/A';
            $city          = $feed->location->city ?? 'N/A';
            $location      = ($location !== 'N/A') ? "§2{$address} §w(在 §2{$city}§w 标注了 §4{$feed->location->title}§w)" : '§g无';
            $uploadTime    = date('Y-m-d H:i:s', (int) ($feed->timestamp / 1000));
            $allowComments = $feed->onlyFollowerCanComment ? '§1仅允许关注者评论' : '§5允许';


            $this->getLogger()->info('----------------------------------------------------------------');
            $this->getLogger()->info("[No.{$k}] 上传时间: §3{$uploadTime}§r§w | 定位: {$location}§r§w | 经纬度: {$coordinate}");
            $this->getLogger()->info("作品ID: §3{$feed->id}§r§w | 类型: §3{$feed->workType}§r§w | 公开评论: {$allowComments}§r§w | 评论数: §7{$feed->counts->displayComment}");
            $this->getLogger()->info("获赞数: §7{$feed->counts->displayLike}§r§w | 共计观看次数: §7{$feed->counts->displayView}");
            $this->getLogger()->info('文章标题及标签: §5§i§b' . str_replace("\n", '  ', $feed->caption));

            if(in_array($feed->workType, ['single', 'vertical', 'multiple'])) {
                $imgUrls = $feed->imgUrls;
                $this->getLogger()->info('共计 §1' . count($imgUrls). '§w 张图片!');

                if($autoDownload) {
                    foreach($imgUrls as $url) {
                        $path = explode('.', $url);
                        $path[count($path) - 1] = 'jpg';
                        $url  = implode('.', $path);
                        $this->download($url, $authorPath . $feed->id . DIRECTORY_SEPARATOR, $feed->id);
                    }
                }
            } else {
                if($autoDownload) {
					$videoUrl = Feeds::getPureVideoData($feed->id);
					$videoUrl = $videoUrl->photo->photoUrl ?? null;
					if($videoUrl) {
                    	$this->download($videoUrl, $authorPath, $feed->id);
					} else {
						$this->getLogger()->error('无法获取该作品的视频资源地址.');
					}
                }
            }
        }
        if($autoDownload) {
            if((System::getOS() === 'windows') && is_dir($authorPath)) {
                system('start ' . $authorPath);
                $this->getLogger()->success('已打开保存文件夹.');
            }
        }
        $this->getLogger()->info('----------------------------------------------------------------');
		return false;
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
		$isExecuted = true;
		switch(array_shift($params)) {
			case '-s':
			case '-shareId':
			case 'shareId':
				$shareId = array_shift($params) ?? null;
                if(is_null($shareId)) {
                    $this->getLogger()->error('[0x011] 无效的分享ID! 请检查是否正确输入.');
					return true;
                } else {
                    $this->getLogger()->info("正在解析分享作品ID: §3{$shareId} §w......");
				}

				$dataInfo = Feeds::queryShareId($shareId, isset($params[array_search('--mode_pc', $params)]));
				if(!$dataInfo) {
					$this->getLogger()->error('[0x012] 无法解析该分享ID.');
					return true;
				}
				extract($dataInfo);
				$this->getLogger()->info("获取到的的作品信息如下 (Type=§3{$type}§w):");
                $this->getLogger()->info('----------------------------------------------------------------');
                $this->getLogger()->info("作者ID: §3{$userId}§r§w | 原始ID: §3{$originalId}§r§w | 快手号: §3{$kwaiId}§r§w | 显示名称: §3{$userName}");
                $this->getLogger()->info("粉丝数: §3{$fansCount}§r§w | 关注数: §3{$followCount}§r§w | 已发布的作品: §3{$photoCount}");
                $this->getLogger()->info("作品ID: §3{$photoId}§r§w | 获赞数: §7{$likeCount}§r§w | 共计观看次数: §7{$viewCount}§r§w | 评论数: §7{$commentCount}§r§w | 分享数: §7{$shareCount}");
                $this->getLogger()->info('文章标题及标签: §5§i§b' . str_replace("\n", '  ', $caption));
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
			break;

			case '-l':
			case '-li':
			case '-login':
				if(!User::login(array_shift($params) ?? 'www', $errorMessage)) {
					$this->getLogger()->error($errorMessage);
				}
			break;

			case '-o':
			case '-lo':
			case '-logout':
				$this->getLogger()->info(User::logout(array_shift($params) ?? 'www') ? '账号退出成功.' : '未知错误.');
			break;

			default:
				$isExecuted = false;
			break;
		}
		return $isExecuted;
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
		$_ = $this->initCurl()->setUrl($url)->exec()->getContent();
		file_put_contents($outputPath . $saveName, $_);
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