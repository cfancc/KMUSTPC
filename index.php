<?php
/*
 * 昆工电脑维修平台微信服务
*/

define("TOKEN", "comfix");

$serveObj = new kmustpcServe();
if (!isset($_GET['echostr'])) {
    $serveObj->responseMsg();
} else {
    $serveObj->valid();
}

class kmustpcServe
{
    //验证签名
    public function valid()
    {
        $echoStr = $_GET["echostr"];
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if ($tmpStr == $signature) {
            echo $echoStr;
            exit;
        }
    }

    //响应消息
    public function responseMsg()
    {
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];//读取http请求的正文内容
        if (!empty($postStr)) {//如果请求不为空(不为False)
            $this->logger("接收数据: " . $postStr);
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);//将读取到的字符串转换为xml对象
            $RX_TYPE = trim($postObj->MsgType);//提取xml对象的MsgType属性,并去除两边的干扰符号

            //消息类型分离
            switch ($RX_TYPE) {//通过比对MsgType来转入不同类型消息的处理函数
                case "event":
                    $result = $this->receiveEvent($postObj);
                    break;
                case "text":
                    $result = $this->receiveText($postObj);
                    break;
                case "image":
                    $result = $this->receiveImage($postObj);
                    break;
                case "location":
                    $result = $this->receiveLocation($postObj);
                    break;
                case "voice":
                    $result = $this->receiveVoice($postObj);
                    break;
                case "video":
                    $result = $this->receiveVideo($postObj);
                    break;
                case "link":
                    $result = $this->receiveLink($postObj);
                    break;
                default:
                    $result = "unknown msg type: " . $RX_TYPE;
                    break;
            }
            $this->logger("发送数据: " . $result);
            echo $result;
        } else {
            echo "";
            exit;
        }
    }

    //生成主菜单
    public function generateMenu()
    {
        $mainMenu = array();
        $mainMenu[] = array("Title" => "欢迎关注昆工电脑维修平台", "Description" => "", "PicUrl" => "", "Url" => "");
        $mainMenu[] = array("Title" => "【1】平台简介\n" .
            "【2】业务范围\n" .
            "【3】收费标准\n" .
            "【4】在线预约和咨询\n" .
            "【5】预约查询\n" .
            "【0】返回本菜单\n" .
            "联系电话：18213918830\nQQ:527311916",
            "Description" => "", "PicUrl" => "http://mmbiz.qpic.cn/mmbiz/SqhQrf083qBe6zr89Q4pa6VVKwW8VzfiabHzuHgSSEWpiaBILuTNgCFomrqg7nDjvic3Pdibbial8dZr09YuyHVfvyg/640", "Url" => "");
        $mainMenu[] = array("Title" => "回复【】内对应数字使用相应功能\n更多精彩，即将亮相，敬请期待！", "Description" => "", "PicUrl" => "", "Url" => "");
        return $mainMenu;
    }

    //接收event事件消息
    private function receiveEvent($object)
    {
        $content = "";
        switch ($object->Event) {
            case "subscribe"://关注时自动推送菜单
                $content = $this->generateMenu();
//              $content .= (!empty($object->EventKey))?("\n来自二维码场景 ".str_replace("qrscene_","",$object->EventKey)):"";
                break;
            case "unsubscribe":
                $content = "取消关注";
                break;
            case "SCAN":
                $content = "扫描场景 " . $object->EventKey;
                break;
            case "CLICK":
                switch ($object->EventKey) {
                    case "COMPANY":
                        $content = array();
                        $content[] = array("Title" => "多图文1标题", "Description" => "", "PicUrl" => "http://discuz.comli.com/weixin/weather/icon/cartoon.jpg", "Url" => "http://m.cnblogs.com/?u=txw1958");
                        break;
                    default:
                        $content = "点击菜单：" . $object->EventKey;
                        break;
                }
                break;
            case "LOCATION":
                $content = "上报的位置为：纬度 " . $object->Latitude . ";经度 " . $object->Longitude;
                break;
            case "VIEW":
                $content = "跳转链接 " . $object->EventKey;
                break;
            case "MASSSENDJOBFINISH":
                $content = "消息ID：" . $object->MsgID . "，结果：" . $object->Status . "，粉丝数：" . $object->TotalCount . "，过滤：" . $object->FilterCount . "，发送成功：" . $object->SentCount . "，发送失败：" . $object->ErrorCount;
                break;
            default:
                $content = "receive a new event: " . $object->Event;
                break;
        }
        //判断返回内容类型
        if (is_array($content)) {
            if (isset($content[0]['PicUrl'])) {//如果是图文消息
                $result = $this->transmitNews($object, $content);
            } else if (isset($content['MusicUrl'])) {//如果是音乐链接
                $result = $this->transmitMusic($object, $content);
            }
        } else {//其它类型当作文字消息封装
            $result = $this->transmitText($object, $content);
        }

        return $result;
    }
    //接收文本消息
    private function receiveText($object)
    {
        $keyword = trim($object->Content);
        //自动回复模式
        if ($keyword == "0") {//功能菜单
            $content = $this->generateMenu();
        } else if ($keyword == "1") {//平台简介
            $content = array();
            $content[] = array("Title" => "昆工电脑维修平台简介",
                "Description" => "电脑维修哪家强？",
                "PicUrl" => "https://mmbiz.qlogo.cn/mmbiz/SqhQrf083qBe6zr89Q4pa6VVKwW8VzfiabHzuHgSSEWpiaBILuTNgCFomrqg7nDjvic3Pdibbial8dZr09YuyHVfvyg/0",
                "Url" => "http://mp.weixin.qq.com/s?__biz=MjM5NDc3NzQ3OA==&mid=202253484&idx=1&sn=b28f3c45cb0972da9c56e696609ea8a1#rd");
        } else if ($keyword== "2") {//业务范围
            $content = array();
            $content[] = array("Title" => "业务范围", "Description" => "业务范围及维修保障", "PicUrl" => "http://mmbiz.qpic.cn/mmbiz/SqhQrf083qBe6zr89Q4pa6VVKwW8VzfiajSyCaDOwkyzSqRwia3onxZiadI7ASjg7NXrw9DP3ZwqeCMu08UqtfWWA/0", "Url" => "http://mp.weixin.qq.com/s?__biz=MjM5NDc3NzQ3OA==&mid=202260806&idx=1&sn=26620a4c7d442a2b166b98787d6e2075#rd");
        }else if ($keyword== "3") {//收费标准
            $content = array();
            $content[] = array("Title" => "收费标准", "Description" => "详细收费标准", "PicUrl" => "https://mmbiz.qlogo.cn/mmbiz/SqhQrf083qBOQbvbGictsO8uyhmbnM9iaiaV9azmIAqFUbV6Qhv3icBquYqLQVjqFFib3uxAdVxMkF9bBUE2IS6Kibcw/0", "Url" => "http://mp.weixin.qq.com/s?__biz=MjM5NDc3NzQ3OA==&mid=202261086&idx=1&sn=3287f404b5fa2bc8ab4443240ecad09b#rd");
        }else if ($keyword== "4") {//在线预约
            $content = array();
            $content[] = array("Title" => "在线预约和咨询", "Description" => "预约我们的服务或反馈您的问题，我们会及时予以答复", "PicUrl" => "https://mmbiz.qlogo.cn/mmbiz/SqhQrf083qBOQbvbGictsO8uyhmbnM9iaiad133XZBxH37xCs9D1vnyWD8gS6rK9yjYhhdCib5d8epIicBLoL0wBy0Q/0", "Url" => "http://comfix.vipsinaapp.com/order.html#one");
        } else if ($keyword== "5") {//预约查询
            $content = array();
            $content[] = array("Title" => "预约查询", "Description" => "我们针对您的预约或问题给出了答复，通过此入口查看相关信息", "PicUrl" => "https://mmbiz.qlogo.cn/mmbiz/SqhQrf083qBOQbvbGictsO8uyhmbnM9iaiaaRDcsNEg5WIqnFCyMdWSmsDuhNwz0pkpyuFvlq6uZC4AoicU7k6E3xQ/0", "Url" => "http://comfix.vipsinaapp.com/order.html#two");
        }
//        else if($keyword== "6"){//在线咨询
//            $content = $this->transmitService($object);
//            $tips = $this->transmitText($object,"在线咨询接口异常，未及时回复请添加微信号:cfancc或QQ：527311916进行咨询");
//            echo $tips;
//            return $content;
//        }
//        else if($keyword== "时间"||$keyword=="t"||$keyword=="T"){
//            $content = "现在时间是：\n".date("Y-m-d H:i:s") . "\n"  . "\n技术支持By CC";
//        }
        else{
            return;
        }
        if (is_array($content)) {
            if (isset($content[0]['PicUrl'])) {
                $result = $this->transmitNews($object, $content);
            } else if (isset($content['MusicUrl'])) {
                $result = $this->transmitMusic($object, $content);
            }
        } else {
            $result = $this->transmitText($object, $content);
        }
        return $result;
    }

    //接收图片消息
    private function receiveImage($object)
    {
        $content = array("MediaId" => $object->MediaId);
        $result = $this->transmitImage($object, $content);
        return $result;
    }

    //接收位置消息
    private function receiveLocation($object)
    {
        $content = "你发送的是位置，纬度为：" . $object->Location_X . "；经度为：" . $object->Location_Y . "；缩放级别为：" . $object->Scale . "；位置为：" . $object->Label;
        $result = $this->transmitText($object, $content);
        return $result;
    }

    //接收语音消息
    private function receiveVoice($object)
    {
        if (isset($object->Recognition) && !empty($object->Recognition)) {
            $content = "你刚才说的是：" . $object->Recognition;
            $result = $this->transmitText($object, $content);
        } else {
            $content = array("MediaId" => $object->MediaId);
            $result = $this->transmitVoice($object, $content);
        }

        return $result;
    }

    //接收视频消息
    private function receiveVideo($object)
    {
        $content = array("MediaId" => $object->MediaId, "ThumbMediaId" => $object->ThumbMediaId, "Title" => "", "Description" => "");
        $result = $this->transmitVideo($object, $content);
        return $result;
    }

    //接收链接消息
    private function receiveLink($object)
    {
        $content = "你发送的是链接，标题为：" . $object->Title . "；内容为：" . $object->Description . "；链接地址为：" . $object->Url;
        $result = $this->transmitText($object, $content);
        return $result;
    }

    //回复文本消息
    private function transmitText($object, $content)
    {
        $xmlTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[text]]></MsgType>
<Content><![CDATA[%s]]></Content>
</xml>";
        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time(), $content);
        return $result;
    }

    //回复图片消息
    private function transmitImage($object, $imageArray)
    {
        $itemTpl = "<Image>
    <MediaId><![CDATA[%s]]></MediaId>
</Image>";

        $item_str = sprintf($itemTpl, $imageArray['MediaId']);

        $xmlTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[image]]></MsgType>
$item_str
</xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }

    //回复语音消息
    private function transmitVoice($object, $voiceArray)
    {
        $itemTpl = "<Voice>
    <MediaId><![CDATA[%s]]></MediaId>
</Voice>";

        $item_str = sprintf($itemTpl, $voiceArray['MediaId']);

        $xmlTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[voice]]></MsgType>
$item_str
</xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }

    //回复视频消息
    private function transmitVideo($object, $videoArray)
    {
        $itemTpl = "<Video>
    <MediaId><![CDATA[%s]]></MediaId>
    <ThumbMediaId><![CDATA[%s]]></ThumbMediaId>
    <Title><![CDATA[%s]]></Title>
    <Description><![CDATA[%s]]></Description>
</Video>";

        $item_str = sprintf($itemTpl, $videoArray['MediaId'], $videoArray['ThumbMediaId'], $videoArray['Title'], $videoArray['Description']);

        $xmlTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[video]]></MsgType>
$item_str
</xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }

    //回复图文消息
    private function transmitNews($object, $newsArray)
    {
        if (!is_array($newsArray)) {
            return;
        }
        $itemTpl = "    <item>
        <Title><![CDATA[%s]]></Title>
        <Description><![CDATA[%s]]></Description>
        <PicUrl><![CDATA[%s]]></PicUrl>
        <Url><![CDATA[%s]]></Url>
    </item>
";
        $item_str = "";
        foreach ($newsArray as $item) {
            $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);
        }
        $xmlTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[news]]></MsgType>
<ArticleCount>%s</ArticleCount>
<Articles>
$item_str</Articles>
</xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time(), count($newsArray));
        return $result;
    }

    //回复音乐消息
    private function transmitMusic($object, $musicArray)
    {
        $itemTpl = "<Music>
    <Title><![CDATA[%s]]></Title>
    <Description><![CDATA[%s]]></Description>
    <MusicUrl><![CDATA[%s]]></MusicUrl>
    <HQMusicUrl><![CDATA[%s]]></HQMusicUrl>
</Music>";

        $item_str = sprintf($itemTpl, $musicArray['Title'], $musicArray['Description'], $musicArray['MusicUrl'], $musicArray['HQMusicUrl']);

        $xmlTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[music]]></MsgType>
$item_str
</xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }

    //回复多客服消息
    private function transmitService($object)
    {
        $xmlTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[transfer_customer_service]]></MsgType>
<Content></Content>
</xml>";
        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }

    //日志记录
    private function logger($log_content)
    {
        if (isset($_SERVER['HTTP_APPNAME'])) {  //HTTP_APPNAME 标志该请求属于哪个应用
            sae_set_display_errors(false);
            sae_debug($log_content);//sae_debug(error_msg)  输出信息到应用的debug日志
            sae_set_display_errors(true);
        }
        /*(SAE下禁止写入,无效)
         * else if ($_SERVER['REMOTE_ADDR'] != "127.0.0.1") { //REMOTE_ADDR 正在浏览当前页面用户的 IP 地址。
            $max_size = 10000;
            $log_filename = "log.xml";
            if (file_exists($log_filename) and (abs(filesize($log_filename)) > $max_size)) {//如果文件存在且文件大小超出max_size
                unlink($log_filename);//删除
            }
            file_put_contents($log_filename, date('H:i:s') . " " . $log_content . "\r\n", FILE_APPEND);
            //file_put_contents — 将一个字符串写入文件 如果不存在,则创建
            //FILE_APPEND	如果文件 filename 已经存在，追加数据而不是覆盖。
        }
         */
    }
}