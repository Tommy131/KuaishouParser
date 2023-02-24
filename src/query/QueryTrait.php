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
 * @Date         : 2023-02-22 19:24:31
 * @LastEditors  : HanskiJay
 * @LastEditTime : 2023-02-24 23:10:25
 * @E-Mail       : support@owoblog.com
 * @Telegram     : https://t.me/HanskiJay
 * @GitHub       : https://github.com/Tommy131
 */
declare(strict_types=1);
namespace module\kuai\query;



use module\kuai\KuaishouParser;
use owoframe\http\Curl;
use owoframe\utils\Logger;
use owoframe\object\JSON;

trait QueryTrait
{
    /**
     * 模块实例
     *
     * @var KuaishouParser
     */
    private $module = null;

    /**
     * 缓存文件路径
     *
     * @var string
     */
    protected $filePath = null;

    /**
     * 缓存文件名
     *
     * @var string
     */
    protected $fileName = null;


    /**
     * 设置主模块实例
     *
     * @param  KuaishouParser $module
     * @return void
     */
    public function setModule(KuaishouParser $module) : void
    {
        $this->module = $module;
    }

    /**
     * 设置新的文件名
     *
     * @param  string $first
     * @param  string $second
     */
    public function setFileName(string $first, string $second) : void
    {
        $this->filePath = $first . DIRECTORY_SEPARATOR;
        $this->fileName = $second;
    }

    /**
     * Curl实例
     *
     * @return Curl
     */
    public function curl() : Curl
    {
        return $this->module->curl()->setContentType('application/json, text/plain, */*');
    }

    /**
     * 验证请求结果是否有效
     *
     * @param  object|null $obj
     * @param  integer $code
     * @return boolean
     */
    public static function verifyResult(?object $obj, int $code = 1) : bool
    {
        return is_object($obj) && ((isset($obj->result) && ($obj->result === $code)) || (isset($obj->data->result) && ($obj->data->result === $code)));
    }

    /**
     * 返回文件路径
     *
     * @return string
     */
    public function getFilePath() : string
    {
        return KuaishouParser::defaultStoragePath($this->filePath . $this->fileName, true);
    }

    /**
     * 返回缓存文件路径
     *
     * @return string
     */
    public function getCacheFile() : string
    {
        return $this->getFilePath() . "{$this->fileName}.json";
    }

    /**
     * 从缓存文件获取数据
     *
     * @param  boolean $update
     * @return JSON
     */
    public function getCache(bool $update = false) : JSON
    {
        static $cache;
        if($update || (!$cache instanceof JSON)) {
            $cache = new JSON($this->getCacheFile(), $this->data ?? [], true);
        }
        return $cache;
    }

    /**
     * 保存请求结果到本地
     *
     * @return JSON
     */
    public function save(array $data) : JSON
    {
        return $this->getCache(true)->setAll($data)->set('isCached', true)->set('cachedTime', date('Y-m-d H:i:s'));
    }

    /**
     * 返回日志实例
     *
     * @return Logger
     */
    public function getLogger() : Logger
    {
        return $this->module->getLogger();
    }
}
?>