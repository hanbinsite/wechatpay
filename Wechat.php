<?php
namespace app\api\model;

use think\Cache;
use think\Model;

class Wechat extends Model
{
    private static $appid  = "xxxx";  //微信小程序appid
    private static $secret = "xxxxxx";  //微信小程序secret
    private static $mch_id = "xxx";  //商户号
    private static $body   = "龟兔吧";   //支付说明
    private static $fee    = "0.01";   //金额 0.00
    private static $order  = "";   //订单号
    private static $ip     = "ip地址";
    private static $openid = ""; //用户openid
    private static $key    = "";  //支付平台key
    private static $notify_url = "";   //接收微信异步通知地址

    //获得微信openid
    public static function codegetopenid($code="")
    {
        if ($code=="")
        {
            return 0;
        }

        $url    =   "https://api.weixin.qq.com/sns/jscode2session?appid=".self::$appid."&secret=".self::$secret."&js_code=".$code."&grant_type=authorization_code";
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $res    =   curl_exec($ch);
        curl_close($ch);
        $json_obj = json_decode($res,true);
        //$this->ajaxReturn(array("info"=>array("code"=>1, "messages"=>$json_obj)));
        return $json_obj["openid"];
    }
    //获得微信unionid
    public static function codegetunionid($code="")
    {
        if ($code=="")
        {
            return 0;
        }

        $url    =   "https://api.weixin.qq.com/sns/jscode2session?appid=".self::$appid."&secret=".self::$secret."&js_code=".$code."&grant_type=authorization_code";
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $res    =   curl_exec($ch);
        curl_close($ch);
        $json_obj = json_decode($res,true);
        //$this->ajaxReturn(array("info"=>array("code"=>1, "messages"=>$json_obj)));
        return isset($json_obj['unionid'])?$json_obj['unionid']:"";
    }
    //微信支付开始
    public static function getprepay_id($openid="",$ordernum="",$body="",$fee="",$type=1)
    {
        // openid  小程序openid      ordernum 订单号    
        if ($openid==""  || $ordernum=="" || $body=="" || $fee=="")
        {
            return 0;
        }
        $url1 = "https://xxx.xxxx.cn/api/wechat/publicc/getresultpayshop";   //微信异步接收支付地址  下一行等同，选一即可
        $url2 = "https://xxx.xxxx.cn/api/wechat/publicc/getresultpayacti";
        if ($type==1)
        {
            self::$notify_url = $url1;
        }
        else
        {
            self::$notify_url = $url2;
        }
        self::$openid = $openid;
        self::$body   = $body;
        self::$fee    = floatval($fee*100);
//        $order  = "G2018010515012800004368";
        self::$order  = $ordernum;
        $return = self::pay();
        return json_encode($return);
    }
    private static function pay()
    {
        $return  = self::weixinapp();
        return $return;
    }
    private static function weixinapp()
    {
        $unifiedorder = self::unifiedorder();
        $parameters   = array(
            "appId" => self::$appid,
            "timeStamp" => ''.time().'',
            "nonceStr" =>self::createNoncestr(),
            "package"  =>'prepay_id='.$unifiedorder['prepay_id'],
            "signType" =>"MD5"
        );
        $parameters['paySign'] = self::getSign($parameters);
        return $parameters;
    }
    private static function unifiedorder()
    {
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $parameters = array(
            "appid" => self::$appid,
            "mch_id"=> self::$mch_id,
            "nonce_str" =>self::createNoncestr() ,
            "body" =>self::$body,
            "out_trade_no"=>self::$order,
            "total_fee"=>self::$fee,
            "spbill_create_ip"=>self::$ip,
            //"notify_url" => 'http://www.weixin.qq.com/wxpay/pay.php',
            "notify_url" => self::$notify_url,
            "openid"   => self::$openid,
            "trade_type"=>"JSAPI"
        );
        $parameters['sign']  = self::getSign($parameters);
        $xmlData =self::arrayToXml($parameters);
        $return  =self::xmlToArray(self::postXmlCurl($xmlData,$url,60));
        return $return;
    }
    //产生随机字符串，不大于32位
    private static function createNoncestr($length = 32)
    {
        $chars  =  "abcdefghijklmnopqrstuvwxyz0123456789";
        $str    =  "";
        for ($i = 0;$i<$length;$i++)
        {
            $str .= substr($chars,mt_rand(0,strlen($chars)-1),1);
        }
        return $str;
    }
    //生成签名
    private static function getSign($obj)
    {
        foreach ($obj as $key => $val)
        {
            $Parameters[$key] = $val;
        }
        ksort($Parameters);
        $String =self::formatBizQueryParaMap($Parameters,false);
        $String =$String."&key=".self::$key;
        $String =md5($String);
        $result_ = strtoupper($String);
        return $result_;
    }
    //格式化参数
    private static function formatBizQueryParaMap($paraMap,$urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap  as $k => $v)
        {
            if ($urlencode)
            {
                $v = urlencode($v);
            }
            $buff .= $k ."=".$v."&";
        }
        $reqPar = "";
        if (strlen($buff)>0)
        {
            $reqPar = substr($buff,0,strlen($buff)-1);
        }
        return $reqPar;
    }
    //数组转换成xml
    private static function arrayToXml($arr)
    {
        $xml = '<root>';
        foreach ($arr  as $key=>$val)
        {
            if (is_array($val))
            {
                $xml .="<".$key.">".self::arrayToXml($val)."</".$key.">";
            }
            else
            {
                $xml .="<".$key.">".$val."</".$key.">";
            }
        }
        $xml.= "</root>";
        return $xml;
    }
    //xml转换成数组
    private static function xmlToArray($xml)
    {
        libxml_disable_entity_loader(true);
        $xmlstring = simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA);
        $val = json_decode(json_encode($xmlstring),true);
        return $val;
    }
    private static function postXmlCurl($xml,$url,$second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); //严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        set_time_limit(0);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            return "curl出错，错误码:$error";
//            throw new WxPayException("curl出错，错误码:$error");
        }
    }
    //微信支付结束
//    //发送微信公众号模板消息
    public static function sendtemplatemessage($arr = array(),$data=array())
    {
        //$order = $data['order'];
        $first = $data['first'];
        $keyword1 = $data['keyword1'];
        $keyword2 = $data['keyword2'];
        $keyword3 = $data['keyword3'];
        $remark   = $data['remark'];
        $openid   = $arr['openid'];
        $pagepath = $arr['pagepath'];
        $template_id = $arr['template_id'];
        $miniprogram = $arr['miniprogram'];



        $appid = self::$appid;//跳转小程序的appid
        $url    =   "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".self::getWxAccessToken();
        $datas   =   array("first"=>array("value"=>$first), "keyword1"=>array("value"=>$keyword1), "keyword2"=>array("value"=>$keyword2), "keyword3"=>array("value"=>$keyword3), "remark"=>array("value"=>$remark));
        //print_r($datas);
        $post_data = array("touser"=>$openid, "miniprogram"=>array("appid"=>$appid, "pagepath"=>$pagepath), "template_id"=>$template_id, "data"=>$datas);

        $ch =   curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        $output =   curl_exec($ch);
        curl_close($ch);

        print_r($output);
    }
    //获得微信accesstoken
    private static function getWxAccessToken(){

        $get_token_url    =   'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.self::$appid.'&secret='.self::$secret;
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$get_token_url);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $res    =   curl_exec($ch);
        curl_close($ch);
        $json_obj 					= 	json_decode($res, true);
        $access_token				=	$json_obj["access_token"];
        return $access_token;
    }
    //生成小程序码
    public static function setqrcode($id,$type=1)
    {
        $data 	= 	array();
        //$data['scene']			=	"id=".$id;
        //$data['scene']			=	$id;
        //$data['path'] 			=	"pages/goods-detail/goods-detail?id=".$id;
        switch ($type)
        {
            case 1:
                break;
            case 2:
                break;
            case 3:
                break;
            case 4:
                $data['page'] = "pages/shop-detail/shop-detail";
                $scene = array();
                $scene['type'] = "good";
                $scene['id']   = $id;
                $data['scene'] = json_encode($scene);
                break;
            case 5:
                $data['page'] = "pages/shop-detail/shop-detail";
                $scene = array();
                $scene['type'] = "point";
                $scene['id']   = $id;
                $data['scene'] = json_encode($scene);
                break;
        }
        //$data['page'] 			=	"pages/goods-detail/goods-detail";
        $data['width'] 			=	430;
        $data['auto_color'] 	=	false;
        $data 					= 	json_encode($data);
        $url					=	"https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=".self::getWxAccessToken();

        $ch	=	curl_init();
        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_POST, 1);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 15 * 600);
        curl_setopt ($ch, CURLOPT_HEADER, false);
        $output	=	curl_exec($ch);
        curl_close($ch);
        $qrpath = "uploads/qractivity/".md5(time().rand(10,99)).".jpg";

        file_put_contents($qrpath, $output);

        return "https://guituba.xiangchengnetwork.cn/".$qrpath;
    }



}
