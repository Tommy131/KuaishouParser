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
 * @Date         : 2022-09-23 16:19:59
 * @LastEditors  : HanskiJay
 * @LastEditTime : 2023-02-24 04:36:21
 * @E-Mail       : support@owoblog.com
 * @Telegram     : https://t.me/HanskiJay
 * @GitHub       : https://github.com/Tommy131
 */
declare(strict_types=1);
namespace module\kuai;



use module\kuai\KuaiCommand;
use module\kuai\query\API;

use owoframe\console\Console;
use owoframe\module\ModuleBase;
use owoframe\http\Curl;
use owoframe\object\JSON;

class KuaishouParser extends ModuleBase
{
    /**
     * 定义版本
     */
    public const VERSION = '1.0.3.1';

    /**
     * 日志展示前缀
     */
    public const LOG_PREFIX = '[KuaiModule] ';

    /**
     * 加载方法
     *
     * @return void
     */
    public function onEnable() : void
    {
        Console::getInstance()->registerCommand(new KuaiCommand($this));
    }

    /**
     * 返回配置文件实例
     *
     * @param  boolean $update
     * @return JSON
     */
    public function config(bool $update = false) : JSON
    {
        static $config;
        if($update || (!$config instanceof JSON)) {
            $config = new JSON($this->getLoadPath() . 'config.json',
            [
                'cookies' =>
                [
                    'live' => '',
                    'www'  => ''
                ],
                'proxy' => [
                    'status'  => false,
                    'address' => '127.0.0.1',
                    'port'    => 10809
                ]
            ]);
        }
        return $config;
    }

    /**
     * 返回单一平台的Cookie
     *
     * @param  integer $platform
     * @return string
     */
    public function cookie(int $platform = 1) : string
    {
        return $this->config()->get('cookies.' . API::shortPlatformName($platform), '');
    }

    /**
     * 设置单一平台的Cookie
     *
     * @param  integer $platform
     * @return string
     */
    public function setCookie(int $platform = 1) : string
    {
        return $this->config()->get('cookies.' . API::shortPlatformName($platform), '');
    }

    /**
     * 返回代理服务器状态/信息
     *
     * @param  string $type
     * @return void
     */
    public function proxy(string $type = 'status')
    {
        switch(strtolower($type)) {
            default:
            case 'status':
            return $this->config()->get('proxy.status', false);

            case 'data':
            return [
                $this->config()->get('proxy.address') ?? '127.0.0.1',
                $this->config()->get('proxy.port') ?? 10809
            ];
        }
    }

    /**
     * 初始化Curl请求
     *
     * @param  boolean $useRadomIp
     * @return Curl
     */
    public function curl(bool $useRadomIp = true) : Curl
    {
        $curl = (new Curl())->returnBody()->setUA2PC();

        if($useRadomIp) {
            $curl->setRandomIp();
        }

        // 检测是否开启了代理
        if($this->proxy()) {
            $array = $this->proxy('data');
            $curl->setProxy($array[0], $array[1]);
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
	 * @return boolean
	 */
	private function saveFile(string $url, string $outputPath, string $saveName = '', ?string &$status = null) : bool
	{
		if(!is_dir($outputPath)) {
			mkdir($outputPath, 777, true);
		}

		$pu = parse_url($url);
		if(isset($pu['path']) && !empty($pu['path'])) {
			$saveName = explode('/', $pu['path']);
			$saveName = end($saveName);
		} else {
			$status = '无法通过URL设置文件名!';
		}

		if(!isset($pu['host']) || !\owo\str_is_domain($pu['host'])) {
			$status = '无效的网址!';
			return false;
		}

		if(is_file($outputPath . $saveName)) {
			$status = '文件已存在, 跳过下载.';
			return true;
		}
		$_ = $this->curl()->setUrl($url)->exec()->getContent();
		file_put_contents($outputPath . $saveName, $_);
		return is_file($outputPath . $saveName);
	}

	/**
	 * 下载文件
	 *
	 * @param  string $url
	 * @param  string $savePath
	 * @param  string $saveName
	 * @return boolean
	 */
	public function download(string $url, string $savePath, string $saveName = '') : bool
	{
		$this->getLogger()->debug('正在下载: ' . $url);
		if(!$this->saveFile($url, $savePath, $saveName, $status)) {
			$this->getLogger()->error(rtrim('保存失败! ' . ($status ?? '')));
            return false;
		} else {
			$this->getLogger()->success($status ?? '保存成功!');
            return true;
		}
	}

    /**
     * 默认文件存储路径
     *
     * @param  string|null $name
     * @param  boolean     $backSlash
     * @return string
     */
    public static function defaultStoragePath(?string $name = null, bool $backSlash = false) : string
    {
        $name = !$name ? '' : $name;
        \owo\str_escape($name);
        $_ = \owo\module_path('KuaishouParser/downloaded/' . $name, $backSlash);
        if(!is_dir(dirname($_))) {
            mkdir(dirname($_), 755, true);
        }
        return $_;
    }
}
?>