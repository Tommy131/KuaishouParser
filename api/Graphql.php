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
namespace application\kuai\api;

use application\kuai\KuaiApp;
use owoframe\network\Curl;

class Graphql
{
    private $curl;

    /* 原始数据 */
    public $operationName, $query;
    public $variables = [];

    /**
     * 可会调参数
     *
     * @var callable
     */
    public $beforeRequestCallback;

    /**
     * 上次调用名称
     *
     * @var string
     */
    public $lastCallName;

    /* 处理结果 */
    public $headers = '';
    public $encoded = '';
    public $result  = null;

    /**
	 * Graphql操作类
	 *
	 * @author HanskiJay
	 * @since  2022-07-24
	 * @return object
	 */
	public function getOperation() : object
	{
		return new class {
			private $platform = 'www';
			private $names = [
				'principalData' => [
					'www'  => 'visionProfile',
					'live' => 'sensitiveUserInfoQuery'
				],
				'feedData' => [
					'www'  => 'visionVideoDetail',
					'live' => 'publicFeedsQuery'
				],
				'searchEID' => [
					'www'  => 'graphqlSearchUser',
                    'live' => 'SharePageQuery'
				],
				'login' => [
					'www'  => 'userInfoQuery',
					'live' => 'UserLogin'
				]
			];

			public function getName(string $type) : ?string
			{
				return $this->lastCallName = $this->names[$type][$this->platform] ?? null;
			}

			public function getQuery(string $type) : ?string
			{
				$appPath = KuaiApp::getAppPath() . 'graphql' . DIRECTORY_SEPARATOR;
				$query = [
					'principalData' => [
						'www'  => 'visionProfile.graphql',
						'live' => 'sensitiveUserInfoQuery.graphql'
					],
					'feedData' => [
						'www'  => 'visionVideoDetail.graphql',
						'live' => 'publicFeedsQuery.graphql'
					],
					'searchEID' => [
						'www' => 'graphqlSearchUser.graphql',
                        'live' => 'SharePageQuery.graphql'
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

			public function setPlatform(string $platform = 'www') : object
			{
				$this->platform = $platform;
                return $this;
			}

            public function getLastCall() : ?string
            {
                return $this->lastCallName;
            }
		};
	}

    /**
     * 设置操作名称
     *
	 * @author HanskiJay
	 * @since  2022-07-17
     * @param  string $name
     * @return Graphql
     */
    public function setOperationName(string $name) : Graphql
    {
        $this->operationName = $name;
        return $this;
    }

    /**
     * 设置变量名称
     *
	 * @author HanskiJay
	 * @since  2022-07-17
     * @param  array  $variables
     * @return Graphql
     */
    public function setVariables(array $variables) : Graphql
    {
        $this->variables = array_merge($variables, $this->variables);
        return $this;
    }

    /**
     * 设置请求语句
     *
	 * @author HanskiJay
	 * @since  2022-07-17
     * @param  string  $query
     * @return Graphql
     */
    public function setQuery(string $query) : Graphql
    {
        $this->query = $query;
        return $this;
    }

    /**
     * 使用JSON编码请求包
     *
	 * @author HanskiJay
	 * @since  2022-07-17
     * @param  boolean  $returnThis
     * @return string|Graphql
     */
    public function encode(bool $returnThis = false)
    {
        $this->encoded = json_encode([
            'operationName' => $this->operationName,
            'variables'     => $this->variables ?? [],
            'query'         => $this->query
        ]);
        return $returnThis ? $this : $this->encoded;
    }

    /**
     * 发送请求
     *
	 * @author HanskiJay
	 * @since  2022-07-17
     * @param  string  $url
     * @param  string  $cookie
     * @param  integer $timeout
     * @return Graphql
     */
    public function sendQuery(string $url, string $cookie = '', int $timeout = 60) : Graphql
    {
        $this->getCurl()->setUrl($url)->setCookiesInRaw($cookie)->setTimeOut($timeout)->setContentType('application/json; charset=UTF-8');

        if(is_callable($this->beforeRequestCallback)) {
            call_user_func_array($this->beforeRequestCallback, [$this->getCurl()]);
        }

        $content = $this->getCurl()->setPostDataRaw($this->encode())->exec()->getContent($this->headers);

        if(strlen($content) > 0) {
            $content = json_decode($content);
            if(is_object($content)) {
                $this->result = $content;
            }
        }
        return $this;
    }

    /**
     * 获取请求结果
     *
	 * @author HanskiJay
	 * @since  2022-07-17
     * @return mixed
     */
    public function getResult()
    {
        if(isset($this->result->data)) {
            $this->result = $this->result->data;
        }
        return $this->result;
    }

    /**
     * 获取Curl对象
     *
     * @param  boolean  $reset
	 * @author HanskiJay
	 * @since  2022-07-17
     * @return Curl
     */
    public function getCurl(bool $reset = false) : Curl
    {
        if($reset || (!$this->curl instanceof Curl)) {
            $this->curl = KuaiApp::initCurl();
        }
        return $this->curl;
    }
}