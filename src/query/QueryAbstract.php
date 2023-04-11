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
 * @Date         : 2023-04-11 17:08:18
 * @LastEditors  : HanskiJay
 * @LastEditTime : 2023-04-12 00:55:24
 * @E-Mail       : support@owoblog.com
 * @Telegram     : https://t.me/HanskiJay
 * @GitHub       : https://github.com/Tommy131
 */
declare(strict_types=1);
namespace module\kuai\query;



use module\kuai\KuaishouParser as Kuai;
use owoframe\object\JSON;

class QueryAbstract
{
    use QueryTrait;

    /**
     * 用户ID
     *
     * @var string
     */
    protected $principalId;

    /**
     * 用户数据
     *
     * @var array
     */
    protected $userData = [];

    /**
     * 用户作品数据
     *
     * @var array
     */
    protected $feedsData = [];

    /**
     * 请求快手API的响应代码
     *
     * @var integer
     */
    public $result = 0;


    /**
     * 构造方法
     *
     * @param  Kuai        $module
     * @param  string|null $principalId
     */
    public function __construct(Kuai $module, ?string $principalId = null)
    {
        $this->setModule($module);
        $this->setPrincipalId($principalId);
    }

    /**
     * 设置用户名称
     *
     * @param  string        $principalId
     * @return QueryAbstract
     */
    public function setPrincipalId(string $principalId) : QueryAbstract
    {
        $this->principalId = $principalId;
        $this->setFileName($principalId, $principalId);
        return $this;
    }

    /**
     * 将用户ID编码 (防止中文乱码)
     *
     * @return string
     */
    public function encodePrincipalId() : string
    {
        return urlencode($this->principalId);
    }

    /**
     * 创建性别转中文的闭包函数
     *
     * @param  string  $gender
     * @param  integer $mode
     * @return string
     */
    public static function getGender(string $gender, int $mode = 0) : string
    {
        return '§' . (($mode === 0) ? (($gender === 'M') ? '7男' : '1女') : (($gender === 'M') ? '7他' : '1她'));
    }

    /**
     * 缓存用户数据到本地
     *
     * @return JSON
     */
    public function cacheUserData() : JSON
    {
        return $this->save($this->userData);
    }
}
?>