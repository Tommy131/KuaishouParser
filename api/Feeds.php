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

use application\kuai\KuaiApp as Kuai;
use application\kuai\api\Graphql;

class Feeds
{
    /**
     * 返回作者所有作品的原始请求结果
     *
     * @param  string  $principalId 作者ID
     * @param  integer $count       获取的数量
     * @return array|null
     */
    public static function getPureDataSet(string $principalId, int $count) : ?array
    {
        $graphql   = new Graphql;
        $operation = $graphql->getOperation()->setPlatform('live');
        $object    = $graphql->setOperationName($operation->getName('feedData'))->setVariables(['principalId' => $principalId, 'count' => $count])->setQuery($operation->getQuery('feedData'));
        $result    = $object->sendQuery(Urls::GRAPHQL_LIVE, Kuai::getCookies('live'))->getResult();
        $result    = $result->publicFeeds->list ?? null;
        return (is_null($result) || (count($result) == 0)) ? null : $result;
    }

    /**
     * 获取视频作品的信息
     *
     * @param  string      $videoId
     * @return object|null
     */
    public static function getPureVideoData(string $videoId) : ?object
    {
        $graphql   = new Graphql;
        $operation = $graphql->getOperation();
        $object    = $graphql->setOperationName($operation->getName('feedData'))->setVariables(['videoId' => $videoId])->setQuery($operation->getQuery('feedData'));
        $result    = $object->sendQuery(Urls::GRAPHQL_LIVE, Kuai::getCookies('live'))->getResult();
        return $result->visionVideoDetail ?? null;
    }

    /**
     * 获取作品视频地址
     * ~Attention: 有的时候下载不起作用, 不建议使用此方法!
     *
     * @param  string      $principalId
     * @param  string      $feedId
     * @return string|null
     */
    public static function getPureVideoUrl(string $principalId, string $feedId) : ?string
    {
        $graphql   = new Graphql;
        $operation = $graphql->getOperation()->setPlatform('live');
        $object    = $graphql->setOperationName($operation->getName('searchEID'))->setVariables(['principalId' => $principalId, 'feedId' => $feedId])->setQuery($operation->getQuery('searchEID'));
        $result    = $object->sendQuery(Urls::GRAPHQL_LIVE, Kuai::getCookies('live'))->getResult();
        return $result->feedById->currentWork->playUrl ?? null;
    }

    /**
     * 返回分享ID作品数据
     *
     * @param  string     $shareId
     * @param  boolean    $isPC
     * @return array|null
     */
    public static function queryShareId(string $shareId, bool $isPC = false) : ?array
    {
        $url = 'https://v.kuaishou.com/';
        if($isPC) {
            $url = 'https://www.kuaishou.com/f/';
        }

        Kuai::initCurl()->returnHeader(true)->setUrl($url . $shareId)->exec()->getContent($h);
        if(!$h) return null;
        if(!preg_match('/^Location: (.*)$/imU', $h, $match)) {
            return null;
        }
        $result  = parse_url($match[1]);
        $photoId = $result['path'] ?? null;
        if(is_null($photoId)) {
            return null;
        }
        $photoId = @end(explode('/', $result['path']));

        parse_str($result['query'] ?? '', $result);
        if(!is_array($result) || empty($result)) {
            return null;
        }
        $prepared = [
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
        $prepared = array_merge($prepared, array_intersect_key($result, $prepared));

        $curl = Kuai::initCurl()
            ->userAgentInMobile()
            ->setUrl(Urls::RESOURCE_DATA)
            ->setPostData($prepared, true)
            ->setCookiesInRaw('did=web_')
            ->setContentType('application/json; charset=UTF-8')
            ->setReferer($match[1])
        ->exec();
        $json = $curl->decodeWithJson(true);
        if($json->result !== 1) {
            return null;
        }
        $_ = [];
        // 获取作者信息;
        $_['photoId']      = $photoId;
        $_['userId']       = $json->photo->userEid;
        $_['originalId']   = $json->photo->userId;
        $_['kwaiId']       = $json->photo->kwaiId ?? '未定义';
        $_['userName']     = $json->photo->userName;

        $_['fansCount']    = $json->counts->fanCount;
        $_['followCount']  = $json->counts->followCount;
        $_['photoCount']   = $json->counts->photoCount;

        // 获取视频信息;
        $_['likeCount']    = $json->photo->likeCount;
        $_['shareCount']   = $json->photo->shareCount ?? 'N/A';
        $_['commentCount'] = $json->photo->commentCount;
        $_['viewCount']    = $json->photo->viewCount;
        $_['caption']      = $json->photo->caption;

        if(isset($json->atlas)) {
            $_['type']       = 'Pictures';
            $_['cdn'] = $cdn = $json->atlas->cdn;
            $_['domain']     = $cdn[array_rand($cdn, 1)];
            $_['list']       = $json->atlas->list;
        } else {
            $_['type'] = 'Video';
            $_['url']  = array_shift($json->photo->mainMvUrls)->url;
        }
        return $_;
    }
}