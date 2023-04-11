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
 * @Date         : 2023-04-11 16:48:48
 * @LastEditors  : HanskiJay
 * @LastEditTime : 2023-04-12 01:19:54
 * @E-Mail       : support@owoblog.com
 * @Telegram     : https://t.me/HanskiJay
 * @GitHub       : https://github.com/Tommy131
 */
declare(strict_types=1);
namespace module\kuai\query;



use Error;
use module\kuai\KuaishouParser as Kuai;
use owoframe\http\Graphql;
use owoframe\object\JSON;

class WwwQuery extends QueryAbstract
{
    use QueryTrait;

    /**
     * 查询请求
     *
     * @param  string  $template
     * @return Graphql
     */
    protected function request(string $template) : Graphql
    {
        $template = $this->module->getLoadPath() . 'graphql' . DIRECTORY_SEPARATOR . "{$template}.graphql";
        if(!file_exists($template)) {
            throw new Error("File '{$template}' was not found!");
        }
        $graphql  = new Graphql;
        $graphql->setCurl($this->module->curl()->setCookieRaw($this->module->cookie(API::TAG_WWW)))
                ->setQuery(file_get_contents($template))
                ->setRequestUrl(API::WWW);
        return $graphql;
    }

    /**
     * 模糊搜索用户
     *
     * @param  integer $page
     * @return ?array
     */
    public function search(int $page = 1) : ?array
    {
        $query = $this->request('UserSearch')->setOperationName('graphqlSearchUser')->setVariables(['keyword' => $this->principalId, 'pcursor' => $page])->query();
        $query = $query->getResult()->visionSearchUser ?? null;
        $data  = self::verifyResult($query) ? ($query->users ?? null) : null;
        if(is_array($data)) {
            $data['pcursor'] = $query->pcursor;
        }
        return $data;
    }

    /**
     * 获取用户详细信息
     *
     * @return object|null
     */
    public function sensitiveInfo() : ?object
    {
        $query = $this->request('UserProfile')->setOperationName('visionProfile')->setVariables(['userId' => $this->principalId])->query();
        $data  = $query->getResult()->visionProfile ?? null;
        $data  = self::verifyResult($data) ? $data->userProfile : null;
        return $data;
    }

    /**
     * 获取用户所有作品信息
     *
     * @param  string|null $pcursor
     * @return ?array
     */
    public function getFeeds(?string &$pcursor = null) : ?array
    {
        $query   = $this->request('UserFeeds')->setOperationName('visionProfilePhotoList')->setVariables(['userId' => $this->principalId, 'pcursor' => $pcursor, 'page' => 'profile'])->query();
        $query   = $query->getResult()->visionProfilePhotoList ?? null;
        $data    = self::verifyResult($query) ? ($query->feeds ?? null) : null;
        $pcursor = $query->pcursor ?? null;
        if(($pcursor !== null) && ($pcursor !== 'no_more') && ($pcursor !== '')) {
            $this->getLogger()->info(Kuai::LOG_PREFIX . '正在获取进阶数据: ' . $pcursor);
            $data = array_merge($data, $this->getFeeds($pcursor) ?? []);
            // 随机暂停进程
            usleep(mt_rand(5000, 10000));
        }
        return $data;
    }

    /**
     * 返回用户模糊搜索结果
     *
     * @param  integer   $page
     * @return WwwQuery
     */
    public function showSearchResult(int $page = 1) : WwwQuery
    {
        $list = $this->search($page);
        if(!$list) {
            $this->getLogger()->error(Kuai::LOG_PREFIX . '无搜索结果.');
            return $this;
        }
        $count = count($list);
        $this->getLogger()->info(Kuai::LOG_PREFIX . "当前页面一共找到 §3{$count}§w 条结果, 此处仅显示缩略信息.");
        $this->getLogger()->info(Kuai::LOG_PREFIX . "查询用户详细信息请使用指令: §3owo kuai [userId]" . PHP_EOL);

        // 获取游标
        $pcursor = $list['pcursor'];
        unset($list['pcursor']);

        foreach($list as $user) {
            $id       = \owo\str_fill_length($user->user_id, 20);
            $name     = \owo\str_fill_length($user->user_name, 20);
            $isVerified = $user->verified ? '5是' : '1否';
            \owo\color_output(Kuai::LOG_PREFIX . "用户ID: §2{$id} §w| 显示名称: §2{$name} §w| 认证状态: §{$isVerified}" . PHP_EOL);
        }
        \owo\output(PHP_EOL);

        if($pcursor !== 'no_more') {
            $this->getLogger()->info(Kuai::LOG_PREFIX . "翻页查找请输入: §3owo kuai -s {$this->principalId} [page] (共 {$pcursor} 页)");
        }
        return $this;
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
        '用户ID: §3{user_id}  §w| 显示名称: §3{user_name}  §w| 性别: {gender} §w| 关注状态: §{isFollowing}§w' . PHP_EOL .
        '粉丝数: §3{fan}  §w| 作品数: §3{photo_public}   §w| 关注数: §3{follow}§w' . PHP_EOL .
        '个人简介: §r{user_text}§w' . PHP_EOL .
        $line;
    }

    /**
     * 返回完整用户数据
     *
     * @return WwwQuery
     */
    public function showSensitiveInfo() : WwwQuery
    {
        if(is_file($this->getCacheFile()) && !$this->noCache && ($this->getCache()->length() > 2)) {
            $info = $this->getCache()->setObjectMode(true);
            $info->reload();
            $info   = $info->obj();
            $cached = ($info->isCached ?? false) ? ('[已缓存] 时间: ' . $info->cachedTime . PHP_EOL) : '';
        } else {
            $info = $this->sensitiveInfo();
            if($info) {
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

        // 替换用户粉丝数据
        $eS = '登录后可查看';
        foreach($info->ownerCount ?? ['fan' => $eS, 'photo_public' => $eS, 'follow' => $eS] as $k => $v) {
            $strings[] = "{{$k}}";
            $replace[] = \owo\str_fill_length((string) $v, $fillLength);
        }
        $template = str_replace($strings, $replace, $template);
        unset($info->ownerCount);

        // 替换用户基本信息
        foreach($info->profile as $k => $v)
        {
            $strings[] = "{{$k}}";
            switch($k) {
                case 'sex':
                case 'gender':
                    $v = self::getGender($v);
                    $l = 1;
                break;

                case 'user_name':
                case 'user_text':
                    $v = (strlen($v) === 0) ? '无信息' : $v;
                break;
            }
            $replace[] = \owo\str_fill_length((string) $v, $l ?? $fillLength);
        }
        $strings[] = '{isFollowing}';
        $replace[] = ($info->isFollowing ? '5正在' : '1未') . '关注';
        $template = str_replace($strings, $replace, $template);
        \owo\color_output($template);
        return $this;
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
        $this->setFileName($this->principalId, 'FeedsData_www');
        $data = $this->getCache(true);
        $list = !$this->noCache ? (($data->length() > 0) ? $data->get('www') : []) : $this->getFeeds();
        if(empty($list)) {
            return false;
        }
        $this->feedsData['www'] = $list;
        $this->cacheFeedsData();

        $savePath = $this->getFilePath() . $this->principalId . DIRECTORY_SEPARATOR;
        $_ = null;
        $line = '--------------------------------------------------------------------------------';

        foreach($list as $k => $feed) {
            $feed = (object) $feed;
            $feed->photo = (object) $feed->photo;
            // 作品描述
            $caption = $feed->photo->originCaption;
            // 解析作品标签
            if(is_object($feed->tags)) {
                $tags = [];
                foreach($feed->tags as $tag) {
                    $tags[] = "#{$tag->name}";
                }
                $tags            = implode(' ', $tags);
                $feeds['tags']   = $tags;
                $caption         = str_replace($tags, '', $caption);
            } else {
                $tags = '无';
            }
            $feed = $feed->photo;

            \owo\output(PHP_EOL, PHP_EOL);
            $this->getLogger()->info(Kuai::LOG_PREFIX . '[No. ' . ($k + 1) . '] ' . "即将下载(ID={$feed->id})......");
            $duration   = $feed->duration / 1000;
            $topSticked = $feed->profileUserTopPhoto ? '5是' : '1否';
            $liked      = $feed->liked ? '5是' : '1否';
            \owo\color_output($line . PHP_EOL .
            "作品ID: §3{$feed->id} §w| 时长: §3{$duration}秒 §w| 获赞数: §3{$feed->likeCount} §w| 观看数: §3{$feed->viewCount}§w" . PHP_EOL .
            "是否置顶: §{$topSticked}            §w| 已点赞: §{$liked}§w" . PHP_EOL .
            "作品描述: §r{$caption}§w" . PHP_EOL .
            "标签: §r{$tags}§w" . PHP_EOL .
            $line . PHP_EOL);
            $_savePath = $savePath . $feed->id . DIRECTORY_SEPARATOR;
            $__ = $this->module->download($feed->photoUrl, $_savePath);
            $_  = is_null($_) ? $__ : $_ && $__;
        }
        \owo\open($savePath . $this->principalId);

        $this->getCache()->set('downloadStatus' , ($_ === true));
        return $_ ?? false;
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
     * 缓存用户作品数据到本地
     *
     * @return JSON
     */
    public function cacheFeedsData() : JSON
    {
        $fileName = $this->fileName;
        $this->setFileName($this->principalId, 'FeedsData_www');
        $json     = $this->save($this->feedsData);
        $this->setFileName('', $fileName);
        return $json;
    }
}
?>