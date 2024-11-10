<?php

/**
 * Attobox Framework / JWT authorization
 * JWT 权限验证 (JSON Web Token) 类
 * 
 * 使用方式：
 *      $jwt = new Jwt();
 *      $jwt->requestHeader = "自定义的请求头字段名，默认为 Authorization";
 *      $jwt->secretDir = "自定义的 secret.json 保存路径 [appdir]/accounts";
 * 
 *      用户登录成功后，生成 token
 *          $token = $jwt->generate( [ usrData ], " 可手动指定 audience 允许的访问来源：如：foo.cgy.design " );
 *          将生成的 token 返回前端，由前端在每次请求时，附带到请求头中
 * 
 *      每次请求时，验证 token 并获取 账户信息
 *          $valiResult = $jwt->validate();
 *          返回格式：$valiResult = 
 *              [
 *                  "success" => true | false,  是否验证成功
 *                  "status" => "success | emptyToken | differentAudience | errorToken | expired",  验证状态
 *                  "msg" => "status 的文字信息",
 *                  "payload" => [] | null, 验证成功时，返回 payload 用户信息，否则返回 null
 *              ]
 * 
 * 原理：
 * 前端每次请求时，在请求头添加 Authorization 字段（可在 $jwt->requestHeader 属性中自定义），
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

namespace Atto\Orm;

use Atto\Box\Request;
use Atto\Box\Uac;
use Atto\Box\Db;

class Jwt 
{
    //有效期时长，8 hours
    public static $expireIn = 8*60*60;  
    //默认加密算法 alg
    public static $alg = "HS256";

    //可自定义 headers 字段名，默认 Authorization
    public $requestHeader = "Authorization";

    //自定义 jwt-secret.json 的保存位置，默认 [webroot|app/appname]/accounts
    public $secretDir = "app/db/accounts";

    /**
     * 从 Request Headers 中解析当前请求的来源
     * 可由子类覆盖
     * @return String 解析得到的 audience 字符串
     */
    public function getAudienceFromRequestHeader()
    {
        //通用解析方法，子类可覆盖
        //解析 Request::$current->headers["Referer"]，为空则返回 null
        $req = Request::$current;
        $ref = isset($req->headers["Referer"]) ? $req->headers["Referer"] : null;
        if (!is_notempty_str($ref)) return "public";
        return strtolower(explode("/", explode("://", $ref)[1])[0]);
    }

    /**
     * 根据请求来源 audience 创建密钥 secret  or  编辑此 audience 包含的 account 数据
     * 保存到 [$this->secretDir]/[base64(audience)].json
     * @param String $aud 请求来源，domain  or  public
     * @param Array $extra 要写入 json 的更多数据，!!! 必须包含 name 字段 !!!
     * @return String secret 密钥
     */
    public function createSecretByAud($aud=null, $account=[])
    {
        $aud = is_notempty_str($aud) ? $aud : $this->getAudienceFromRequestHeader();
        $base_aud = base64_encode($aud);
        $secretDir = $this->secretDir;
        $sfn = $secretDir.DS.$base_aud.".json";
        $sf = path_find($sfn);
        if (file_exists($sf)) {
            //已经存在，检查 account 数据是否已经包含在 json 中
            $sn = j2a(file_get_contents($sf));
            //未指定 account 直接退出
            if (empty($account) || !is_notempty_str($account["name"])) return $sn["secret"];
            $acname = $account["name"];
            //account 数据保存在 account 字段下
            $ac = $sn["account"] ?? null;
            if (empty($ac)) $sn["account"] = [];
            if (isset($ac[$acname])) {
                $sn["account"][$acname] = arr_extend($ac[$acname], $account);
            } else {
                $sn["account"][$acname] = $account;
            }
            //写入
            file_put_contents($sf, a2j($sn));
            return $sn["secret"];
        } else {
            //不存在此 audience 的 secret，创建
            $secret = str_nonce()."@".$aud;
            $sfh = @fopen($sfn, "w");
            if (!$sfh) return null;
            $sn = [
                "aud" => $aud,
                "secret" => $secret,
                "account" => []
            ];
            if (!empty($account) && is_notempty_str($account["name"])) {
                $acname = $account["name"];
                $sn["account"][$acname] = $account;
            }
            @fwrite($sfh, a2j($sn));
            @fclose($sfh);
            return $sn["secret"];
        }
    }

    /**
     * 根据请求来源，获取签名使用的密钥 secret
     * @param String $aud 解析得到的 request 来源 domain  or  public
     * @return String | null     secret 密钥
     */
    protected function getSecretByAud($aud=null)
    {
        $aud = is_notempty_str($aud) ? $aud : $this->getAudienceFromRequestHeader();
        $base_aud = base64_encode($aud);
        $secretDir = $this->secretDir;
        $sfn = $secretDir.DS.$base_aud.".json";
        //var_dump($sfn);
        $sf = path_find($sfn);
        if (!empty($sf)) {
            //找到保存的 json
            $sn = j2a(file_get_contents($sf));
            return $sn["secret"];
        }
        //未找到 json 则创建
        return $this->createSecretByAud($aud);
    }

    /**
     * 获取 前端传入的 Token
     * @return String JWT Token  or  null
     */
    public function getToken()
    {
        $req = Request::$current;
        //var_dump($req->headers);
        if (empty($req)) return null;
        $hd = $this->requestHeader;
        if (!isset($req->headers[$hd]) || empty($req->headers[$hd])) {
            return null;
        }
        return $req->headers[$hd];
    }

    /**
     * 创建 JWT Token
     * 在用户登录成功后 创建并返回
     * @param Array $usrdata    要放置在 JWT Payload 字段的用户信息
     * @param String $audManual 如果指定，则使用此 aud 作为访问来源，通常用于访问 本机上其他 webroot 下的 api
     * @return String JWT Token
     */
    public function generate($usrdata = [], $audManual = null)
    {
        $alg = self::$alg;
        $aud = empty($audManual) ? $this->getAudienceFromRequestHeader() : $audManual;
        $secret = $this->getSecretByAud($aud);
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
    public function validate(/*...$apis*/)
    {
        /*$req = Request::$current;
        if (!isset($req->headers["Authorization"]) || empty($req->headers["Authorization"])) {
            return self::returnValidateResult("emptyToken");
        }
        $token = $req->headers["Authorization"];*/
        $token = $this->getToken();
        //var_dump($token);
        if (empty($token)) return $this->returnValidateResult("emptyToken");
        $ta = explode(".", $token);
        $jwt_header = j2a(base64_decode($ta[0]));
        $alg = $jwt_header["alg"];
        $jwt_payload = j2a(base64_decode($ta[1]));
        $usrdata = isset($jwt_payload["usr"]) ? $jwt_payload["usr"] : null;
        $jwt_signature = $ta[2];
        $hp = $ta[0].".".$ta[1];
        $aud = $this->getAudienceFromRequestHeader();
        //var_dump($aud);
        if (!isset($jwt_payload["aud"]) || $jwt_payload["aud"]!=$aud) {
            return $this->returnValidateResult("differentAudience", [
                "token_aud" => $jwt_payload["aud"],
                "real_aud" => $aud
            ]);
        }
        $secret = $this->getSecretByAud($aud);
        //var_dump($secret);
        $signature = self::sign($alg, $hp, $secret);
        if ($jwt_signature !== $signature) {
            return $this->returnValidateResult("errorToken");
        }
        $t = time();
        if ($t > $jwt_payload["exp"]) {
            return $this->returnValidateResult("expired");
        }
        return $this->returnValidateResult("success", $usrdata);
    }

    /**
     * 返回验证结果
     * @param String $status
     * @param Array | Mixed $payload return usrdata if success
     * @return Array
     */
    protected function returnValidateResult($status = "success", $payload = [])
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
}