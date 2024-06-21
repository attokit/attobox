<?php

/**
 * Attobox Framework / JWT authorization
 * JWT 权限验证 (JSON Web Token)
 * 
 * 原理：
 * 前端每次请求时，在请求头添加 Authorization 字段，
 * 后端通过 $_SERVER["HTTP_AUTHORIZATION"] 获取内容，
 * 根据字段内容判断 token 的效期、权限 等信息，返回对应结果
 * 
 * Apache 无法直接获取 $_SERVER["HTTP_AUTHORIZATION"]
 * 在网站根路径 .htaccess 文件中有解决方法
 * 
 * JWT 实现细节：
 *   1  Authorization 字段内容格式
 *      包含3段内容，按顺序用 “.” 连接成为一个完整字符串，分别为：
 *      1.1 Header      
 *          JWT头，使用 base64 编码的 json 字符串，其中指定的 signature 签名所使用的算法
 *          { 'typ': 'JWT', 'alg': 'HS256' }
 *      1.2 Payload
 *          载荷，使用 base64 编码的 json 字符串
 *          {
 *              'iss': 'cgy.design',  //预定义字段，issure，签发人
 *              'aud': 'request 来源 host 如： wx.cgy.design',  //预定义字段，audience，接收对象
 *              'iat': 0,   //预定义字段，签发时间
 *              'nbf': 0,   //预定义字段，生效时间
 *              'exp': 0,   //预定义字段，expiration time，过期时间
 *              'uid': '此处为前端用户登录完成后取得的 uid',  //自定义字段，用户 uid
 *              ...         //可以有更多自定义字段
 *          }
 *      1.3 Signature
 *          使用 Header.alg 指定的方法，对 base64(Header).base64(Payload) 字符串进行签名
 *          密钥由 JWT 管理，保存在 [webroot]/library/jwt/secret/[aud name].json 中
 * 
 */

namespace Atto\Box;

use Atto\Box\Request;
use Atto\Box\Uac;
use Atto\Box\Db;

class Jwt 
{

    public static $expireIn = 8*60*60;  //有效期时长，8 hours

    /**
     * 从 Request Headers 中解析当前请求的来源
     * 如果有特殊解析方法，可在 [webroot]/library/jwt 或 [app/appname]/library/jwt 路径创建一个 JwtAudParser 类，并实现 parse 方法，返回解析得到的 audience 字符串
     * @return String
     */
    public static function getAudienceFromRequestHeader()
    {
        $psn = Uac::prefix("jwt/JwtAudParser", "class");
        $parser = cls($psn);
        if (!empty($parser)) return $parser::parse();

        //通用解析方法
        //解析 Request::$current->headers["Referer"]，为空则返回 null
        $req = Request::$current;
        $ref = isset($req->headers["Referer"]) ? $req->headers["Referer"] : null;
        if (!is_notempty_str($ref)) return "public";
        return strtolower(explode("/", explode("://", $ref)[1])[0]);
    }

    /**
     * 根据请求来源，获取签名使用的密钥 secret
     * 如果没有，则创建并保存到 
     *      [webroot]/library/jwt/secret/[base64(audience)].json  or 
     *      [app/appname]/library/jwt/secert/[base64(audience)].json
     * @param String $aud 解析得到的 request 来源 domain  or  public
     * @param String $secretDir 在此目录查找 secret 文件，必须是正确的文件夹路径，默认不提供
     * @return Array | null     ["aud"=>aud, "secret"=>"secret"]
     */
    protected static function getSecretByAud($aud = null, $secretDir = null)
    {
        $aud = is_null($aud) ? "public" : $aud;
        $base_aud = base64_encode($aud);
        if (!empty($secretDir)) {
            $sfn = $secretDir.DS.$base_aud.".json";
        } else {
            $sfn = Uac::prefix("library/jwt/secret/$base_aud.json", "path");
            //var_dump(Uac::getUacConfig("db"));
            //var_dump(session_get("uac_db"));
            //var_dump(APP_PMS_UAC_DB);
        }
        //var_dump($sfn);
        $sf = path_find($sfn);
        if (empty($sf)) {
            $secret = str_nonce()."@".$aud;
            $sfd = path_find(Uac::prefix("library/jwt/secret", "path"),["inDir"=>"","checkDir"=>true]);
            $sfh = @fopen($sfd.DS.$base_aud.".json", "w");
            if (!$sfh) return null;
            $secrets = [
                "aud" => $aud,
                "secret" => $secret
            ];
            @fwrite($sfh, a2j($secrets));
            @fclose($sfh);
        } else {
            $secrets = j2a(file_get_contents($sf));
        }
        return $secrets["secret"];
    }

    /**
     * 获取 前端传入的 Token
     * @return String JWT Token  or  null
     */
    public static function getToken()
    {
        $req = Request::$current;
        //var_dump($req->headers);
        if (!isset($req->headers["Authorization"]) || empty($req->headers["Authorization"])) {
            return null;
        }
        return $req->headers["Authorization"];
    }

    /**
     * 创建 JWT Token
     * 在用户登录成功后 创建并返回
     * @param Array $usrdata    要放置在 JWT Payload 字段的用户信息
     * @param String $audManual 如果指定，则使用此 aud 作为访问来源，通常用于访问 本机上其他 webroot 下的 api
     * @return String JWT Token
     */
    public static function generate($usrdata = [], $audManual = null)
    {
        $alg = "HS256";
        $aud = empty($audManual) ? self::getAudienceFromRequestHeader() : $audManual;
        $secret = self::getSecretByAud($aud);
        $req = Request::$current;
        $hds = $req->headers;
        $t = time();
        $expt = $t + self::$expireIn;

        $jwt_header = [
            "typ" => "JWT",
            "alg" => $alg
        ];

        $jwt_payload = [
            "iss" => $hds["Host"],
            "aud" => $aud,
            "iat" => $t,
            "nbf" => $t,
            "exp" => $expt,
            "usr" => $usrdata
        ];

        $hp = base64_encode(a2j($jwt_header)).".".base64_encode(a2j($jwt_payload));
        $jwt_signature = self::sign($alg, $hp, $secret);

        $token = $hp.".".$jwt_signature;
        return [
            "header" => $jwt_header,
            "payload" => $jwt_payload,
            "token" => $token
        ];
        //return $hp.".".$jwt_signature;
    }

    /**
     * 验证前端传回的 JWT Token
     * @param String[] $apis 要验证权限的 api(s)  !!! 不需要
     * @return Array [ "success" => true, "payload" => usrdata ] or [ "success" => false, "msg" => "error msg", "payload" => null ]
     *              or Bool
     */
    public static function validate(/*...$apis*/)
    {
        /*$req = Request::$current;
        if (!isset($req->headers["Authorization"]) || empty($req->headers["Authorization"])) {
            return self::returnValidateResult("emptyToken");
        }
        $token = $req->headers["Authorization"];*/
        $token = self::getToken();
        //var_dump($token);
        if (empty($token)) return self::returnValidateResult("emptyToken");
        $ta = explode(".", $token);
        $jwt_header = j2a(base64_decode($ta[0]));
        $alg = $jwt_header["alg"];
        $jwt_payload = j2a(base64_decode($ta[1]));
        $usrdata = isset($jwt_payload["usr"]) ? $jwt_payload["usr"] : null;
        $jwt_signature = $ta[2];
        $hp = $ta[0].".".$ta[1];
        $aud = self::getAudienceFromRequestHeader();
        //var_dump($aud);
        if (!isset($jwt_payload["aud"]) || $jwt_payload["aud"]!=$aud) {
            return self::returnValidateResult("differentAudience", [
                "token_aud" => $jwt_payload["aud"],
                "real_aud" => $aud
            ]);
        }
        $secret = self::getSecretByAud($aud);
        //var_dump($secret);
        $signature = self::sign($alg, $hp, $secret);
        if ($jwt_signature !== $signature) {
            return self::returnValidateResult("errorToken");
        }
        $t = time();
        if ($t > $jwt_payload["exp"]) {
            return self::returnValidateResult("expired");
        }
        return self::returnValidateResult("success", $usrdata);
        //if (empty($apis)) return self::returnValidateResult("success", $usrdata);

        /**
         * !!! 通过 Uac 统一控制权限，不需要此步骤
        //开始验证 是否拥有 api(s) 权限
        $auth = false;
        $usrapis = self::getUsrApis($usrdata);
        $noauthapis = array_diff($apis, $usrapis);
        if (empty($noauthapis)) return true;
        return self::returnValidateResult("noauth", $noauthapis);
        */
    }

    /**
     * 返回验证结果
     * @param String $status
     * @param Array | Mixed $payload return usrdata if success
     * @return Array
     */
    protected static function returnValidateResult($status = "success", $payload = [])
    {
        $res = [
            "success" => "Token 验证成功",
            "emptyToken" => "Token 不存在",
            "differentAudience" => "请求来源与 Token 不一致",
            "errorToken" => "Token 验证失败",
            "expired" => "Token 已过期",
            "noauth" => "尝试访问无权限的 api"
        ];
        if ($status=="noauth") {
            if (!empty($payload)) {
                $res["noauth"] .= " [".implode(",", $payload)."]";
            }
        }
        return [
            "success" => $status=="success",
            "status" => $status,
            "msg" => $res[$status],
            "payload" => $status=="success" ? $payload : ($status=="emptyToken" ? "emptyToken" : (!empty($payload) ? $payload : null))
        ];
    }

    /**
     * Token 验证 encode 算法
     * 由 JWT.Header.alg 指定
     */
    protected static function sign($alg = "HS256", $str = "", $secret = "")
    {
        $rst = "";
        switch ($alg) {
            case "HS256" :
                $rst = hash_hmac("sha256", $str, $secret);
                break;
        }
        return $rst;
    }

    /**
     * !!! 通过 Uac 统一控制权限，不需要此方法
     * 获取用户 usr 拥有权限的全部 api 数组
     * 如果存在不同的获取用户 apis 方法，可在 [webroot]/library/jwt 或 [app/appname]/library/jwt 路径创建一个 JwtApisGetter 类，并实现 get 方法，返回获取到的用户 apis 数组
     * @param Array $usr data 必须包含用户 uid 数据
     * @return Array 拥有权限的 api 数组
     */
    protected static function getUsrApis($usr = [])
    {
        $jag = Uac::prefix("jwt/JwtApisGetter");
        $JwtApisGetter = cls($jag);
        if (!empty($JwtApisGetter)) return $JwtApisGetter::get($usr);

        if (!isset($usr["uid"])) return [];
        $dsn = isset($usr["dsn"]) ? $usr["dsn"] : "sqlite:usr";
        $table = isset($usr["table"]) ? $usr["table"] : "usr";
        $uid = $usr["uid"];
        $db = Db::load($dsn);
        $tb = $db->table($table);
        $ur = $tb->where([
            "id" => $uid
        ])->order("id DESC")->single();
        if (!$ur instanceof Record) return [];
        return $ur->getAuthApis();
    }

    //eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJxeS5jZ3kuZGVzaWduIiwiYXVkIjoid3gwNmYxMDUzYzRkNTU0ODEyIiwiaWF0IjoxNjgzNTM2ODY4LCJuYmYiOjE2ODM1MzY4NjgsImV4cCI6MTY4MzU0NDA2OCwidXNyIjp7InVpZCI6IkUwMDAyIn19.40eebfe7c4ff27e129eb70e38f842419245f56fe5e1396870055be3b6a3326e4
}