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
 * @Date         : 2023-02-22 02:55:56
 * @LastEditors  : HanskiJay
 * @LastEditTime : 2023-02-24 23:24:54
 * @E-Mail       : support@owoblog.com
 * @Telegram     : https://t.me/HanskiJay
 * @GitHub       : https://github.com/Tommy131
 */
declare(strict_types=1);
namespace module\kuai\query;



use module\kuai\KuaishouParser as Kuai;
use owoframe\http\Curl;
use owoframe\object\JSON;

class UserQuery
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
     * @param  string    $principalId
     * @return UserQuery
     */
    public function setPrincipalId(string $principalId) : UserQuery
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
     * Curl请求实例
     *
     * @param  string $url
     * @return Curl
     */
    protected function request(string $url) : Curl
    {
        return $this->curl()->setUrl($url)->setCookieRaw($this->module->cookie(API::TAG_LIVE));
    }

    /**
     * 查询请求
     *
     * @param  string      $url
     * @return object|null
     */
    protected function query(string $url) : ?object
    {
        $curl = $this->request($url)->exec();
        $data = $curl->decodeWithJson();
        $data = $data->data ?? null;
        if($data) {
            $this->result = $data->result;
        }
        return $data;
    }

    /**
     * 模糊搜索用户
     *
     * @param  integer    $page
     * @return array|null
     */
    public function search(int $page = 1) : ?array
    {
        $data = $this->query(API::LIVE_SEARCH . "{$this->encodePrincipalId()}&page={$page}");
        $data = self::verifyResult($data) ? ($data->list ?? null) : null;
        return $data;
    }

    /**
     * 根据ID搜索用户大致信息
     *
     * @return object|null
     */
    public function searchById() : ?object
    {
        $data = $this->query(API::LIVE_USER_INFO . $this->encodePrincipalId());
        $data = self::verifyResult($data) ? ($data->userInfo ?? null) : null;
        return $data;
    }

    /**
     * 获取用户详细信息
     *
     * @return object|null
     */
    public function sensitiveInfo() : ?object
    {
        $data = $this->query(API::LIVE_SENSITIVE_INFO . $this->encodePrincipalId());
        $data = self::verifyResult($data) ? ($data->sensitiveUserInfo ?? null) : null;
        return $data;
    }

    /**
     * 获取用户所有作品信息
     *
     * @param  string     $pcursor
     * @param  integer    $count
     * @return array|null
     */
    public function getFeeds(string $pcursor = '', int $count = 1200) : ?array
    {
        $data = $this->query(API::LIVE_GET_FEEDS . "{$this->encodePrincipalId()}&pcursor={$pcursor}&count={$count}");
        $data = self::verifyResult($data) ? ($data->list ?? null) : null;
        return $data;
    }

    /**
     * 获取用户作品信息
     *
     * @param  string      $photoId
     * @return object|null
     */
    public function feedInfo(string $photoId) : ?object
    {
        $data = $this->query(API::LIVE_FEED_INFO . "{$this->encodePrincipalId()}&photoId={$photoId}");
        $data = self::verifyResult($data) ? ($data->currentWork ?? null) : null;
        return $data;
    }

    /**
     * 返回用户模糊搜索结果
     *
     * @param  integer   $page
     * @return UserQuery
     */
    public function showSearchResult(int $page = 1) : UserQuery
    {
        $list = $this->search($page);
        if(!$list) {
            $this->getLogger()->error(Kuai::LOG_PREFIX . '无搜索结果.');
            return $this;
        }
        $count = count($list);
        $this->getLogger()->info(Kuai::LOG_PREFIX . "当前页面一共找到 §3{$count}§w 条结果, 此处仅显示缩略信息.");
        $this->getLogger()->info(Kuai::LOG_PREFIX . "查询用户详细信息请使用指令: §3owo kuai [userId]" . PHP_EOL);

        foreach($list as $user) {
            $id       = \owo\str_fill_length($user->id, 20);
            $sex      = \owo\str_fill_length(self::getGender($user->sex), 5);
            $name     = \owo\str_fill_length($user->name, 20);
            $cityName = (strlen($user->cityName) === 0) ? '无信息' : $user->cityName;
            \owo\color_output(Kuai::LOG_PREFIX . "用户ID: §2{$id} §w| 性别: {$sex} §w| 显示名称: §2{$name} §w| 所在城市: §2{$cityName}" . PHP_EOL);
        }
        \owo\output(PHP_EOL);
        $this->getLogger()->info(Kuai::LOG_PREFIX . "翻页查找请输入: §3owo kuai -s {$this->principalId} [page]");
        return $this;
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
     * 用户模板输出
     *
     * @return string
     */
    public static function getUserOutputTemplate() : string
    {
        $line = '--------------------------------------------------------------------------------' . PHP_EOL;
        return $line .
        '用户ID: §3{id}  §w| 显示名称: §3{name}    §w| 原始ID: §3{originUserId} §w| 性别: {sex}§w' . PHP_EOL .
        '直播状态: {living} §w| 关注状态: §3{followStatus}  §w| 星座: §3{constellation}    §w| 所在城市: §3{cityName}§w' . PHP_EOL .
        '隐私用户: {privacy} §w| 个人简介: §5{description}§w' . PHP_EOL .
        '粉丝数: §3{fan}  §w| 作品数: §3{photo}      §w| 关注数: §3{follow}  §w| 直播回放: §3{playback}§w' . PHP_EOL .
        '{verifiedStatus}' .
        $line;
    }

    /**
     * 返回完整用户数据
     *
     * @return UserQuery
     */
    public function showSensitiveInfo(bool $update = false) : UserQuery
    {
        if(is_file($this->getCacheFile()) && !$update && ($this->getCache()->length() > 2)) {
            $info = $this->getCache()->setObjectMode(true);
            $info->reload();
            $info   = $info->obj();
            $cached = ($info->isCached ?? false) ? '[已缓存] 时间: ' . $info->cachedTime . PHP_EOL : '';
        } else {
            $info = $this->sensitiveInfo($this->principalId);
            if($info) {
                unset($info->timestamp);
                $this->userData = (array) $info;
                $this->cacheUserData();
            } else {
                $this->getLogger()->error(Kuai::LOG_PREFIX . '无用户数据.');
                return $this;
            }
        }

        $this->getLogger()->success(Kuai::LOG_PREFIX . '解析成功!');
        $strings    = $replace = [];
        $template   = ($cached ?? '') . self::getUserOutputTemplate();
        $fillLength = \owo\str_length($this->principalId);

        // 先替换最后一行
        foreach($info->counts as $k => $v) {
            $strings[] = "{{$k}}";
            $replace[] = \owo\str_fill_length((string) $v, $fillLength);
        }
        $template = str_replace($strings, $replace, $template);
        unset($info->counts, $info->bannedStatus, $info->avatar, $info->isNew);

        // 验证账号认证信息
        $verified = ($info->verifiedStatus->verified) ? ('账号认证信息: §5' . $info->verifiedStatus->description . '§w' . PHP_EOL) : '';
        $template = str_replace('{verifiedStatus}', $verified, $template);
        unset($info->verifiedStatus);

        // 创建识别直播状态的闭包函数
        $isLiving  = fn(bool $_) => '§' . (($_) ? '5在线' : '1离线');
        // 创建识别隐私用户的闭包函数
        $isPrivacy = fn(bool $_) => '§' . (($_) ? '5是' : '1否');

        // 遍历数组
        $strings = $replace = [];
        foreach($info as $k => $v)
        {
            $strings[] = "{{$k}}";
            switch($k) {
                case 'sex':
                case 'gender':
                    $v = self::getGender($v);
                break;

                case 'living':
                    $v = $isLiving($v);
                break;

                case 'privacy':
                    $v = $isPrivacy($v);
                break;

                case 'name':
                case 'constellation':
                case 'cityName':
                case 'description':
                    $v = (strlen($v) === 0) ? '无信息' : $v;
                break;
            }
            $replace[] = \owo\str_fill_length((string) $v, $fillLength);
        }
        $template = str_replace($strings, $replace, $template);
        \owo\color_output($template);
        return $this;
    }

    /**
     * 返回文件路径
     *
     * @return string
     */
    public function getFilePath() : string
    {
        return Kuai::defaultStoragePath($this->filePath, true);
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

    /**
     * 缓存用户作品数据到本地
     *
     * @return JSON
     */
    public function cacheFeedsData() : JSON
    {
        $fileName = $this->fileName;
        $this->setFileName($this->principalId, 'FeedsData');
        $json     = $this->save($this->feedsData);
        $this->setFileName('', $fileName);
        return $json;
    }

    /**
     * 下载用户作品
     *
     * @return boolean
     */
    public function download() : bool
    {
        if(!is_file($this->getCacheFile())) {
            return false;
        }
        $this->setFileName($this->principalId, 'FeedsData');
        $data = $this->getCache(true);
        $list = ($data->length() > 0) ? $data->get('list') : $this->getFeeds();
        if(empty($list)) {
            return false;
        }
        $this->feedsData = ['list' => $list];
        $this->cacheFeedsData();

        $savePath = $this->getFilePath();
        $_ = null;

        foreach($list as $k => $feed) {
            $feed         = (array) $feed;
            $downloadList = [];
            $type         = $feed['workType'];
            switch($type) {
                case 'single':
                case 'multiple':
                case 'vertical':
                    $type = '图片';
                    $downloadList = $feed['imgUrls'];
                break;

                case 'video':
                    $type = '视频';
                    $downloadList = [$feed['playUrl']];
                break;
            }
            ++$k;
            $this->getLogger()->info(Kuai::LOG_PREFIX . "[No. {$k}] 作品类型: {$type}; 即将下载(ID=" . $feed['id'] . ')......');
            $_savePath = $savePath . $this->principalId . DIRECTORY_SEPARATOR . $feed['id'] . DIRECTORY_SEPARATOR;
            foreach($downloadList as $item) {
                $__ = $this->module->download(str_replace('.webp', '.jpg', $item), $_savePath);
                $_  = is_null($_) ? $__ : $_ && $__;
                // 随机暂停进程
                usleep(mt_rand(1000, 5000));
            }
        }
        \owo\open($savePath . $this->principalId);

        $this->getCache()->set('downloadStatus' , ($_ === true));
        return $_ ?? false;
    }
}
?>