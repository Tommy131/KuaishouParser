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
 * @Date         : 2023-02-21 19:27:39
 * @LastEditors  : HanskiJay
 * @LastEditTime : 2023-02-24 04:35:53
 * @E-Mail       : support@owoblog.com
 * @Telegram     : https://t.me/HanskiJay
 * @GitHub       : https://github.com/Tommy131
 */
declare(strict_types=1);
namespace module\kuai\query;



use module\kuai\KuaishouParser as Kuai;
use owoframe\object\JSON;

class ShareIdQuery
{
    use QueryTrait;

    public const PREFIX = 'shareFeeds' . DIRECTORY_SEPARATOR;

    /**
     * 手机平台分享
     */
    public const MODE_MOBILE = 0;

    /**
     * 电脑平台分享
     */
    public const MODE_PC = 1;

    /**
     * 分享平台Url
     */
    public const LINK =
    [
        self::MODE_MOBILE => 'https://v.kuaishou.com/',
        self::MODE_PC     => 'https://www.kuaishou.com/f/'
    ];

    /**
     * 分享ID
     *
     * @var string|null
     */
    protected $shareId = null;

    /**
     * 默认分享模式
     *
     * @var integer
     */
    protected $mode = self::MODE_MOBILE;

    /**
     * 作品类型
     *
     * @var string
     */
    protected $itemType;

    /**
     * 作品信息数据
     *
     * @var array
     */
    protected $data;


    /**
     * 构造方法
     *
     * @param  Kuai $module
     * @param  string         $shareId
     * @param  integer        $mode
     */
    public function __construct(Kuai $module, string $shareId, int $mode = self::MODE_MOBILE)
    {
        $this->setModule($module);
        $this->setShareId($shareId)->setMode($mode);
    }

    /**
     * 设置分享ID
     *
     * @param  string    $shareId
     * @return ShareIdQuery
     */
    public function setShareId(string $shareId) : ShareIdQuery
    {
        $this->shareId = $this->fileName = $shareId;
        return $this;
    }


    /**
     * 设置分享模式
     *
     * @param  integer   $mode
     * @return ShareIdQuery
     */
    public function setMode(int $mode) : ShareIdQuery
    {
        $this->mode = isset(self::LINK[$mode]) ? $mode : 1;
        return $this;
    }

    /**
     * 准备请求数据
     *
     * @param  array|null $toMerge
     * @return array
     */
    public static function preparePostData(?array $toMerge = null) : array
    {
        $_ = [
            'fid'               => '',
            'shareToken'        => '',
            'shareObjectId'     => '',
            'shareMethod'       => '',
            'shareId'           => '',
            'shareResourceType' => '',
            'shareChannel'      => 'share_copylink',
            'kpn'               => '',
            'subBiz'            => '',
            'env'               => 'SHARE_VIEWER_ENV_TX_TRICK',
            'h5Domain'          => 'v.m.chenzhongtech.com',
            'photoId'           => '',
            'isLongVideo'       => ''
        ];
        return $toMerge ? array_merge($_, array_intersect_key($toMerge, $_)) : $_;
    }

    /**
     * 发送请求
     *
     * @return ShareIdQuery
     */
    public function query() : ShareIdQuery
    {
        if($this->noCache || !self::cache()->exists($this->shareId))
        {
            $this->curl()->returnHeader(true)->setUrl(self::LINK[$this->mode] . $this->shareId)->exec()->getContent($result);
            if(!$result || !preg_match('/^Location: (.*)$/imU', $result, $result)) {
                return $this;
            }

            $referer = $result[1];
            $result  = parse_url($referer);
            $photoId = \owo\str_split($result['path'] ?? []);
            $photoId = end($photoId);

            parse_str($result['query'] ?? '', $result);
            if(is_null($photoId) || !is_array($result) || empty($result)) {
                return $this;
            }

            $curl = $this->curl()
                ->setUA2Mobile()
                ->setUrl(API::SHARE_ID)
                ->setPostData(self::preparePostData($result), true)
                ->setCookieRaw('did=web_')
                ->setContentType('application/json; charset=UTF-8')
                ->setReferer($referer)
            ->exec();

            // 判断数据的有效性 & 获取并保存数据
            $json = $curl->decodeWithJson(true);
            if(($json->result ?? 0) !== 1) {
                return $this;
            }
            $this->saveData($photoId, $json);
        } else {
            // 设置文件名
            $userId  = self::cache()->get($this->shareId . '.userId');
            $photoId = self::cache()->get($this->shareId . '.photoId');
            $this->setFileName(self::PREFIX . $userId, $photoId);

            if($this->getCache()->length() === 0) {
                return $this->query(true);
            }

            // 取回数据
            $this->data     = $this->getCache()->getAll();
            $this->itemType = $this->data['type'];
        }
        return $this;
    }

    /**
     * 缓存分享数据
     *
     * @param  string       $photoId
     * @param  object       $json
     * @return ShareIdQuery
     */
    protected function saveData(string $photoId, object $json) : ShareIdQuery
    {
        // 解析作品类型 Picture || Video
        if(isset($json->atlas) || ($this->itemType === 'Picture')) {
            $this->itemType           = 'Picture';
            $this->data['cdn']        = $json->atlas->cdn;
            $this->data['list']       = $json->atlas->list;
        } else {
            $this->itemType           = 'Video';
            $this->data['list']       = array_shift($json->photo->mainMvUrls)->url;
        }

        $this->data['type']           = $this->itemType;
        $this->data['photoId']        = $photoId;
        $userId                       = $json->photo->userEid;
        $this->data['userId']         = $userId;
        $this->data['originalId']     = $json->photo->userId;
        $this->data['kwaiId']         = $json->photo->kwaiId ?? '未定义';
        $this->data['userName']       = $json->photo->userName;

        // 作者信息
        $this->data['fansCount']      = $json->counts->fanCount;
        $this->data['followCount']    = $json->counts->followCount;
        $this->data['photoCount']     = $json->counts->photoCount;

        // 获取作品信息
        $this->data['likeCount']      = $json->photo->likeCount;
        $this->data['shareCount']     = $json->photo->shareCount ?? 'N/A';
        $this->data['commentCount']   = $json->photo->commentCount;
        $this->data['viewCount']      = $json->photo->viewCount;
        $this->data['caption']        = $json->photo->caption;

        $this->setFileName(self::PREFIX . $userId, $photoId);
        $this->createCache();

        self::cache()->set($this->shareId, [
            'userId'         => $userId,
            'photoId'        => $photoId,
            'downloadStatus' => false,
            'cachedTime'     => date('Y-m-d H:i:s')
        ]);
        return $this;
    }

    /**
     * 返回已请求的数据
     *
     * @return array
     */
    public function getData() : array
    {
        return $this->data ?? [];
    }

    /**
     * 返回输出信息模板
     *
     * @return string
     */
    public static function getOutputTemplate() : string
    {
        $line = '--------------------------------------------------------------------------------' . PHP_EOL;
        return
        '获取到的的作品信息如下 (作品类型: §3{type}§w):' . PHP_EOL .
        $line .
        '作者ID: §3{userId}§r§w | 原始ID: §3{originalId}§r§w | 快手号      : §3{kwaiId}§r§w' . PHP_EOL .
        '粉丝数: §3{fansCount}§r§w | 关注数: §3{followCount}§r§w | 已发布的作品: §3{photoCount}§r§w' . PHP_EOL .
        '作品ID: §3{photoId}§r§w | 获赞数: §7{likeCount}§r§w | 共计观看次数: §7{viewCount}§r§w' . PHP_EOL .
        '评论数: §7{commentCount}§r§w | 分享数: §7{shareCount}§r§w | 显示名称    : §3{userName}§r§w' . PHP_EOL .
        '文章标题及标签: §5§i§b{caption}§r§w' . PHP_EOL .
        '下载状态: §{downloadStatus}§r§w' . PHP_EOL .
        $line;
    }

    /**
     * 彩色输出信息
     *
     * @return ShareIdQuery
     */
    public function showInformation() : ShareIdQuery
    {
        if(!empty($this->data)) {
            $output  = ($this->data['isCached'] ?? false) ? '[已缓存] 时间: ' . $this->data['cachedTime'] . PHP_EOL : '';
            $output .= str_replace('{type}', $this->itemType, self::getOutputTemplate());
            foreach($this->data as $k => $v) {
                if(($k === 'list') || ($k === 'cdn')) {
                    continue;
                }
                $output = str_replace("{{$k}}", \owo\str_fill_length((string) $v, 15), $output);
            }
            $output = str_replace('{downloadStatus}', (self::cache()->get($this->shareId . '.downloadStatus', false) ? '5已' : '1未') . '下载', $output);
            \owo\color_output($output);
        }
        return $this;
    }

    /**
     * 缓存文件到本地
     *
     * @return JSON
     */
    public function createCache() : JSON
    {
        return $this->save($this->data);
    }

    /**
     * 下载请求资源
     *
     * @return boolean
     */
    public function download() : bool
    {
        $userId  = self::cache()->get($this->shareId . '.userId');
        $photoId = self::cache()->get($this->shareId . '.photoId');
        $this->setFileName(self::PREFIX . $userId, $photoId);
        $savePath = $this->getFilePath();

        if(self::cache()->get($this->shareId . '.downloadStatus', false)) {
            \owo\open($savePath);
            return true;
        }

        $list = $this->data['list'] ?? null;
        if(!isset($list)) {
            return false;
        }

        if(is_string($list)) {
            $_ = $this->module->download($list, $savePath);
        }
        elseif(is_array($list)) {
            $_ = null;
            foreach($list as $url) {
                $c = $this->data['cdn'];
                $c = $c[array_rand($c, 1)];
                $d = $this->module->download("https://{$c}" . $url, $savePath);
                $_ = is_null($_) ? $d : ($_ && $d);
            }
        }
        \owo\open($savePath);
        self::cache()->set($this->shareId . '.downloadStatus' , ($_ === true));
        return $_ ?? false;
    }

    /**
     * 返回缓存区配置文件
     *
     * @return JSON
     */
    public static function cache() : JSON
    {
        return new JSON(Kuai::defaultStoragePath(self::PREFIX . 'shareIds.json'), [], true);
    }
}
?>