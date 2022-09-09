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

use owoframe\utils\Curl;

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

    /* 处理结果 */
    public $headers = '';
    public $encoded = '';
    public $result  = null;

    public function __construct(Curl $curl)
    {
        $this->curl = $curl;
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
        $this->curl->setUrl($url)->setCookiesInRaw($cookie)->setTimeOut($timeout)->setContentType('application/json; charset=UTF-8');

        if(is_callable($this->beforeRequestCallback)) {
            call_user_func_array($this->beforeRequestCallback, [$this->curl]);
        }

        $content = $this->curl->setPostDataRaw($this->encode())->exec()->getContent($this->headers);

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
     * @return Graphql|null
     */
    public function getResult() : ?Graphql
    {
        if(isset($this->result->data)) {
            $this->result = $this->result->data;
        }
        return $this->result;
    }

    /**
     * 获取Curl对象
     *
	 * @author HanskiJay
	 * @since  2022-07-17
     * @return Curl
     */
    public function getCurl() : Curl
    {
        return $this->curl;
    }
}