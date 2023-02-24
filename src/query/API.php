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
 * @Date         : 2023-02-21 17:44:59
 * @LastEditors  : HanskiJay
 * @LastEditTime : 2023-02-23 04:19:39
 * @E-Mail       : support@owoblog.com
 * @Telegram     : https://t.me/HanskiJay
 * @GitHub       : https://github.com/Tommy131
 */
declare(strict_types=1);
namespace module\kuai\query;



class API
{

    /**
     * https://live.kuaishou.com
     */
    public const TAG_LIVE = 0;

    /**
     * https://wsww.kuaishou.com
     */
    public const TAG_WWW  = 1;

    /**
     * 平台
     */
    public const PLATFORMS =
    [
        self::TAG_LIVE => 'kuaishou.live.web',
        self::TAG_WWW  => 'kuaishou.server.web'
    ];

	/**
	 * 站点 'live.kuaishou.com' 的数据接口
	 *
     * https://live.kuaishou.com/live_graphql # 已失效
	 */
	public const LIVE = 'https://live.kuaishou.com/live_api/';

    /**
     * 站点 'live.kuaishou.com' 的搜索接口
     *
     * @param keyword (已填充)
     * @param page
     * @param lssid
     * @mode  GET
     */
	public const LIVE_SEARCH = self::LIVE . 'search/author/?keyword=';

    /**
     * 获取用户大概信息接口
     *
     * @param principalId (已填充)
     * @mode  GET
     */
	public const LIVE_USER_INFO = self::LIVE . 'baseuser/userinfo/byid/?principalId=';

    /**
     * 获取用户详细信息接口
     *
     * @param principalId (已填充)
     * @mode  GET
     */
	public const LIVE_SENSITIVE_INFO = self::LIVE . 'baseuser/userinfo/sensitive/?principalId=';

    /**
     * 获取作品接口
     *
     * @param principalId (已填充)
     * @param count
     * @param pcursor
     * @param hasMore (已填充)
     * @mode  GET
     */
    public const LIVE_GET_FEEDS = self::LIVE . 'profile/public/?hasMore=true&principalId=';

    /**
     * 获取作品信息接口
     *
     * @param principalId (已填充)
     * @param photoId
     * @mode  GET
     */
    public const LIVE_FEED_INFO = self::LIVE . 'profile/feedbyid/?principalId=';

	/**
	 * 站点 'www.kuaishou.com' 的数据接口
	 *
	 */
	// public const WWW = 'https://www.kuaishou.com/graphql';

	/**
	 * 获取 `web_ph` 值的请求接口 (需要基于登录成功的状态下)
	 *
	 */
	// public const WWW_WEB_PH_REQUEST = 'https://www.kuaishou.com/rest/infra/sts';

	/**
	 * 分享ID的数据请求接口
	 *
     * @mode POST
	 */
	public const SHARE_ID = 'https://v.m.chenzhongtech.com/rest/wd/photo/info';

	/**
	 * 快手身份验证域名
	 */
	public const ID_KUAISHOU = 'https://id.kuaishou.com/';

	/**
	 * 二维码登录接口基本地址
	 *
     * @mode POST
	 */
	public const ACCOUNT_QR_CODE_SCAN = self::ID_KUAISHOU . 'rest/c/infra/ks/qr/';

	/**
	 * 二维码登录回调接口
	 *
	 * @param qrToken
	 * @param sid
     * @mode  POST
	 */
	public const ACCOUNT_QR_CODE_CALLBACK = self::ID_KUAISHOU . 'pass/kuaishou/login/qr/callback';

	/**
	 * 用户登录请求接口
	 *
	 * self::LIVE . 'baseuser/userLogin'
	 * 'https://www.kuaishoupay.com/rest/infra/sts?'
	 *
	 * @param authToken
	 * @param sid
     * @mode  POST
	 */
	public const ACCOUNT_LOGIN = self::LIVE . 'baseuser/userLogin';

	/**
	 * 账号登出请求地址 (需要携带Cookies)
	 *
     * @mode POST
	 */
	public const ACCOUNT_LOGOUT = self::LIVE . 'baseuser/userLogout';

	/**
	 * 账号登出请求地址 (需要携带Cookies)
	 *
     * @mode POST
	 */
	public const ACCOUNT_LOGOUT_ID = self::ID_KUAISHOU . 'pass/kuaishou/login/logout';


    /**
     * 判断是否存在平台
     *
     * @param  integer $platform
     * @return boolean
     */
    public static function hasPlatform(int $platform) : bool
    {
        return isset(self::PLATFORMS[$platform]);
    }

	/**
	 * 通过ID返回平台字符串
	 *
	 * @param  integer $type
	 * @return string
	 */
	public static function platform(int $type = 1) : string
	{
		return self::PLATFORMS[$type] ?? self::TAG_WWW;
	}

	/**
	 * 返回平台名称缩写
	 *
	 * @param  integer     $type
	 * @return string|null
	 */
	public static function shortPlatformName(int $type = 1) : ?string
	{
		return [
			self::TAG_LIVE => 'live',
			self::TAG_WWW  => 'www'
		][$type] ?? null;
	}
}
?>