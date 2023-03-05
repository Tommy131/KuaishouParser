<?php
/*
 *       _____   _          __  _____   _____   _       _____   _____
 *     /  _  \ | |        / / /  _  \ |  _  \ | |     /  _  \ /  ___|
 *     | | | | | |  __   / /  | | | | | |_| | | |     | | | | | |
 *     | | | | | | /  | / /   | | | | |  _  { | |     | | | | | |   _
 *     | |_| | | |/   |/ /    | |_| | | |_| | | |___  | |_| | | |_| |
 *     \_____/ |___/|___/     \_____/ |_____/ |_____| \_____/ \_____/
 *
 * Copyright (c) 2023 by OwOTeam-DGMT (OwOBlog).
 * @Author       : HanskiJay
 * @Date         : 2023-02-21 00:28:00
 * @LastEditors  : HanskiJay
 * @LastEditTime : 2023-03-05 13:11:11
 * @E-Mail       : support@owoblog.com
 * @Telegram     : https://t.me/HanskiJay
 * @GitHub       : https://github.com/Tommy131
 */
declare(strict_types=1);
namespace module\kuai;



use module\kuai\KuaishouParser as Kuai;
use module\kuai\query\AccountQuery;
use module\kuai\query\API;
use module\kuai\query\ShareIdQuery;
use module\kuai\query\UserQuery;
use owoframe\console\CommandBase;

class KuaiCommand extends CommandBase
{

    /**
     * 模块实例
     *
     * @var KuaishouParser
     */
    private $module = null;


    /**
     * 构造方法
     *
     * @param  KuaishouParser $module
     */
    public function __construct(KuaishouParser $module)
    {
        $this->module = $module;
    }

    /**
     * 调用指令时执行方法
     */
    public function execute(array $params): bool
    {
        // ~ 检测代理服务是否开启并提示
        if($this->module->proxy()) {
			$this->getLogger()->notice(Kuai::LOG_PREFIX . '代理服务已开启!');
		}

		// ~ 自动下载作品选项
		$autoDownload = array_search('--autoDownload', $params);
		$autoDownload = ($autoDownload === false) ? array_search('--ad', $params) : false;
		if($autoDownload !== false) {
			unset($params[$autoDownload]);
			$params = array_values($params);
			$autoDownload = true;
			$this->getLogger()->notice(Kuai::LOG_PREFIX . '已开启自动下载作品!');
		} else {
			$autoDownload = false;
		}

        // 检测是否获取最新数据
        $noCache = array_search('--no-cache', $params);
        if($noCache !== false) {
			unset($params[$noCache]);
			$params  = array_values($params);
			$noCache = true;
			$this->getLogger()->notice(Kuai::LOG_PREFIX . '已开启无缓存查询!');
        } else {
            $noCache = false;
        }

        // 解析参数
		if($this->interceptParameters($params, $autoDownload, $noCache)) {
            return true;
        }

		// ~ 需要查询的用户ID
		$userId = array_shift($params);
		if(!$userId || !preg_match('/[0-9a-z_]+/i', $userId)) {
			$this->getLogger()->error(Kuai::LOG_PREFIX . '无效的用户ID! 请检查是否正确输入.');
			return false;
		}

        // ~ 开始作者ID解析
        \owo\color_output(Kuai::LOG_PREFIX . '§w若多次尝试仍然请求失败, 极大几率是Cookie失效, 无法请求API, 请打开网站 ',
        "          '§3https://live.kuaishou.com§w' 和 '§3https://www.kuaishou.com§w'",
        '          登录并且复制Cookie到配置文件.',
        Kuai::LOG_PREFIX . '登录后在浏览器控制台 (§6F12§w) 中输入 `§3document.cookie§w` 即可获取Cookie.',
        Kuai::LOG_PREFIX . "正在查询用户 §3{$userId} §w......", PHP_EOL);

        $query = new UserQuery($this->module, $userId);
        $query->noCache($noCache);
        $query->showSensitiveInfo();
        if($autoDownload) {
            $count = (int) array_shift($params);
            $count = ($count === 0) ? null : $count;
            if(!$query->download($count)) {
                $this->getLogger()->error(Kuai::LOG_PREFIX . '(部分) 下载失败! 请尝试更新Cookies或使用代理服务器下载. 响应代码: ' . $query->result);
            }
        }
        return true;
    }

    /**
     * 拦截参数方法
     *
     * @param  array   $params
     * @param  boolean $autoDownload
     * @param  boolean $noCache
     * @return boolean
     */
    private function interceptParameters(array $params, bool $autoDownload = false, bool $noCache = false) : bool
    {
		$isExecuted = true;

		switch(array_shift($params))
        {
			default:
				$isExecuted = false;
			break;

            case '-o':
            case 'open':
		        $name = array_shift($params);
                $path = Kuai::defaultStoragePath($name);
                if(!file_exists($path)) {
                    $this->getLogger()->error(Kuai::LOG_PREFIX . "文件/文件夹 '{$path}' 未找到!");
                }
                \owo\open($path);
                $this->getLogger()->info(Kuai::LOG_PREFIX . "已打开文件/文件夹 '{$path}'.");
            break;

			case '-s':
            case '-search':
		        $userId = array_shift($params);
                $page   = array_shift($params) ?? 1;
                if(!is_string($userId)) {
                    $this->getLogger()->error(Kuai::LOG_PREFIX . '无效的用户ID! 请检查是否正确输入.');
                    return true;
                }

                $this->getLogger()->info("正在搜索用户关键词: §3{$userId} §w| 页面: §3{$page}");
                $query  = new UserQuery($this->module, $userId);
                $query->noCache($noCache);
                $query->showSearchResult((int) $page);
            break;

            case '-d':
            case '-delete':
                $first = array_shift($params) ?? null;
                $next  = array_shift($params) ?? null;
                switch($first) {
                    case 's':
                    case 'sid':
                    case 'shareId':
                    case 'shareIds':
                        $first = 'shareIds';
                    break;

                    case null:
                        $this->getLogger()->error(Kuai::LOG_PREFIX . "错误的用法! 请给出指定文件名!");
                    return true;
                }
                $path = Kuai::defaultStoragePath("{$first}/{$next}");

                if(!file_exists($path)) {
                    $this->getLogger()->error(Kuai::LOG_PREFIX . "文件 '{$path}' 未找到!");
                } else {
                    if(\owo\remove_dir($path)) {
                        $this->getLogger()->info(Kuai::LOG_PREFIX . "已删除 '{$path}' !");
                    } else {
                        $this->getLogger()->error(Kuai::LOG_PREFIX . '删除失败!');
                    }
                }
            break;

			case '-sid':
			case '-shareId':
				$shareId = array_shift($params) ?? null;
                if(is_null($shareId)) {
                    $this->getLogger()->error(Kuai::LOG_PREFIX . '[0x010] 无效的分享ID! 请检查是否正确输入.');
                } else {
                    $this->getLogger()->info(Kuai::LOG_PREFIX . "正在解析分享作品ID: §3{$shareId} §w......");

                    // 解析分享ID识别平台
                    $shareMode = array_search('--mode-pc', $params);
                    $shareMode = ($shareMode !== false) ? ShareIdQuery::MODE_PC : array_search('--mode-mobile', $params);
                    $shareMode = ($shareMode !== false) ? $shareMode : ShareIdQuery::MODE_MOBILE;

                    if($shareMode === ShareIdQuery::MODE_PC) {
                        $this->getLogger()->error(Kuai::LOG_PREFIX . '[0x011] 暂时停止PC端分享解析.');
                        return true;
                    }

                    // 创建分享ID实例
                    $shareIdQuery = new ShareIdQuery($this->module, $shareId, $shareMode);
                    $shareIdQuery->noCache($noCache);
                    $shareIdQuery->query($noCache);

                    if(!empty($shareIdQuery->getData())) {
                        $this->getLogger()->success(Kuai::LOG_PREFIX . '解析成功!' . PHP_EOL);
                        $shareIdQuery->showInformation();
                        if($autoDownload) {
                            $shareIdQuery->download();
                        }
                    } else {
                        $this->getLogger()->error(Kuai::LOG_PREFIX . '[0x012] 无法解析该分享ID.');
                    }
				}
            break;

            case 'login':
                // 识别登录平台
                $platform = array_shift($params);
                switch($platform) {
                    default:
                    case '0':
                    case 'live':
                    case '-live':
                    case '-l':
                        $platform = 0;
                    break;

                    case '1':
                    case 'www':
                    case '-www':
                    case '-w':
                        $platform = 1;
                    break;
                }

                $platformStr = API::shortPlatformName($platform);
                $this->getLogger()->info(Kuai::LOG_PREFIX . "准备登录请求 [Platform: {$platformStr} ({$platform})]......");
                $query = new AccountQuery($this->module, $platform);
                if(!$query->login($errorMessage)) {
                    $this->getLogger()->error($errorMessage);
                }
            break;

            case 'logout':
                $this->getLogger()->info(Kuai::LOG_PREFIX . '准备登出请求......');
                $query = new AccountQuery($this->module);
                $query->logout();
            break;
        }
		return $isExecuted;
    }

    /**
     * 指令别名
     *
     * @return array
     */
	public static function getAliases() : array
	{
		return ['k', '-k'];
	}

    /**
     * 指令
     *
     * @return string
     */
	public static function getName() : string
	{
		return 'kuai';
	}

    /**
     * 介绍
     *
     * @return string
     */
	public static function getDescription() : string
	{
		return '快手作品解析指令';
	}

    /**
     * 返回使用方法
     *
     * @return string
     */
    public static function getUsage(): string
    {
        $space = \owo\str_fill_length('', 34) . ' -> ';
        $fillLength = 40;
        return parent::getUsage() . implode(PHP_EOL, [
            '§3 [...parameters]§w',
            $space . \owo\str_fill_length('§3[userId]', $fillLength) .  '§w精确查询用户',
            $space . \owo\str_fill_length('-search | -s §3[userId]', $fillLength) .  '§w模糊查询用户',
            $space . \owo\str_fill_length('-d | -delete | §3[name] §r(nextName)', $fillLength + 3) .  '§w删除下载目录下的指定文件/文件夹, 可以是组合形式 (e.g. shareIds id)',
            $space . \owo\str_fill_length('-sid | -shareId | §3[shareId]', $fillLength) .  '§w作品分享ID',
            $space . \owo\str_fill_length('§5--autoDownload §w| §5--ad', $fillLength + 6) . '§w(全局) 自动下载作品',
            $space . \owo\str_fill_length('§5--no-cache', $fillLength) . '§w(全局) 无缓存请求',
            $space . '§3黄色§w为指定末端强制填写参数 | §5绿色§w为指令全局可选参数 (默认缺省).'
        ]);
    }
}
?>