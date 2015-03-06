<?php
/**
 * Created by PhpStorm.
 * User: 杜珂珂
 * Date: 2014/12/3
 * Time: 19:45
 */
$from=$_POST["from"];
if(isset($from)){
    session_start();
    $om = new orderManager();
    if($from=="submit"){
        $om->addOrder();
    }else if($from=="search"){
        $om->searchOrder(array("name"=>$_POST['name']));
    }else if($from=="login"){
        $response=$om->login($_POST["account"],$_POST["password"]);
        //返回信息
        print_r(json_encode($response));
    }else if($from=="logout"){//注销
        $om->logout();
    }else if($from=="start"){//第一次加载页面判断是否已登录
        if($response=$om->isLogin()){
            print_r(json_encode($response));
        }else{
            print_r(json_encode(array("msg"=>"未登录！")));
        }
    }else if($from=="undone"){
        $om->searchOrder(array("workerid"=>$_SESSION['workerid'],"state"=>"未答复"));
    }else if($from=="done"){
        $om->searchOrder(array("workerid"=>$_SESSION['workerid'],"state"=>"已答复"));
    }else if($from=="dealorder"){
        $om->dealOrder();
    }
}
class orderManager{
    private $name;
    private $tel;
    private $qq;
    private $pc;
    private $detail;
    private $service;
    private $yuan;
    private $dong;
    private $hao;
    private $gender;
    private $default_state="未答复";
    //接收预约数据
    private function receiveData(){
        $this->name=$_POST['name'];
        $this->tel=$_POST['tel'];
        $this->qq=$_POST['qq'];
        $this->pc=$_POST['pc'];
        $this->detail=$_POST['detail'];
        $this->service=$_POST['service'];
        $this->yuan=$_POST['yuan'];
        $this->dong=$_POST['dong'];
        $this->hao=$_POST['hao'];
        $this->gender=$_POST['gender'];
    }
    //添加预约
    public function  addOrder(){
        $currenttime = date('Y-m-d H:i:s');
        $this->receiveData();
        // 连主库
        $link=mysql_connect(SAE_MYSQL_HOST_M.':'.SAE_MYSQL_PORT,SAE_MYSQL_USER,SAE_MYSQL_PASS);

        if($link)
        {
            mysql_select_db(SAE_MYSQL_DB,$link);
            $sql="insert into `order` (createtime,name,tel,qq,pc,detail,service,yuan,dong,hao,gender,state) VALUES ".
                 "('$currenttime','$this->name','$this->tel','$this->qq','$this->pc','$this->detail','$this->service','$this->yuan','$this->dong','$this->hao','$this->gender','$this->default_state')";
            $result=mysql_query($sql);
            if (!$result) {
                die('fail: ' . mysql_error());
            }
            echo "success";
            mysql_close($link);
        }
    }
    //查询预约
    public function searchOrder($options){
        /*
         * options=[
         *      "name"=>"",
         *      "workerid"=>"",
         *      "state"=>"未答复|已答复"
         * ]
         * */
        // 连主库
        $link=mysql_connect(SAE_MYSQL_HOST_M.':'.SAE_MYSQL_PORT,SAE_MYSQL_USER,SAE_MYSQL_PASS);

        if($link)
        {
            mysql_select_db(SAE_MYSQL_DB,$link);
            if(isset($options['name'])){
                $sql="SELECT orderid,name,tel,DATE_FORMAT(createtime,'%Y-%c-%e') AS createtime,pc,detail,service,yuan,dong,hao,gender,state,advice FROM `order` ".sprintf("WHERE name='%s'",mysql_real_escape_string($options['name']))." ORDER BY createtime DESC";
            }
            if(isset($options['workerid'])&&isset($options['state'])){
                //如果是已答复的，根据工号和预约状态查询
                $sql="SELECT orderid,name,tel,DATE_FORMAT(createtime,'%Y-%c-%e') AS createtime,pc,detail,service,yuan,dong,hao,gender,state,advice FROM `order` ".sprintf("WHERE workerid='%s'AND state='%s'",mysql_real_escape_string($options['workerid']),mysql_real_escape_string($options['state']))." ORDER BY createtime DESC";
                if($options['state']=="未答复"){//如果是未答复的，查询出所有未答复的预约
                    $sql="SELECT orderid,name,tel,DATE_FORMAT(createtime,'%Y-%c-%e') AS createtime,pc,detail,service,yuan,dong,hao,gender,state,advice FROM `order` ".sprintf("WHERE  state='%s'",mysql_real_escape_string($options['state']))." ORDER BY createtime DESC ";
                }
            }
            $result=mysql_query($sql);
            if (!$result) {
                die('数据库错误：' . mysql_error());
            }
            $response = array();
            if(mysql_num_rows($result)>0){//如果查询结果大于0
                $dataArray=array();
                while($rs=mysql_fetch_array($result,MYSQL_ASSOC)){
                    $dataArray[]=$rs;
                }
                $response['msg']="查询成功！";
                $response['records']=$dataArray;
            }else{
                $response['msg']="没有查询到结果！";
            }
            //将查询结果转换成json格式发给前台
            print_r(json_encode($response));
            mysql_close($link);
        }
    }
    //判断是否登陆
    public function isLogin(){
        //定义返回数据数组
        $response = array();
        //对比接收到的Session中的字段，如果存在，那么表示已成功登录，返回相应数据
        if(isset($_SESSION['workerid'])){//如果$_SESSION['workerid']存在，返回一个数据，让前端动态更改登录状态
            $response["msg"]="已登录！";//添加提示信息
            $response["account"]=$_SESSION['account'];//添加账户名
            $response["name"]=$_SESSION['name'];//添加姓名
            $response["schoolnum"]=$_SESSION['schoolnum'];//添加学号
            return $response;
        }else{
            return false;
        }
    }
    //登录
    public function login($account,$password){
            // 连主库
            $link=mysql_connect(SAE_MYSQL_HOST_M.':'.SAE_MYSQL_PORT,SAE_MYSQL_USER,SAE_MYSQL_PASS);
            if($link)
            {
                mysql_select_db(SAE_MYSQL_DB,$link);
                //根据account查到密码,姓名,学号 前端页面需要用到
                $sql=sprintf("SELECT workerid,password,name,schoolnum FROM `worker` WHERE account='%s'",mysql_real_escape_string($account));
                $result=mysql_query($sql);

                if (!$result) {//如果返回FALSE,说明查询出错
                    die('数据库错误:' . mysql_error());//停止程序运行，输出内容   exit是停止程序运行，不输出内容  return是返回值
                }
                //如果不是返回的FALSE,则代表语句成功执行了
                if(mysql_num_rows($result)==1){//若查到的记录正好为1条 （也可以通过判断mysql_fetch_array()后数组长度决定，php中空数组转换为bool类型是是"FALSE"）
                    $rs=mysql_fetch_array($result,MYSQL_ASSOC);
                    //定义返回数据数组
                    $response = array();
                    //比对密码
                    if($rs['password']==$password) {//如果密码相等,返回一系列信息,前端需要展示
                        $response["msg"]="登陆成功！";//添加提示信息
                        $response["account"]=$account;//添加账户名
                        $response["name"]=$rs['name'];//添加姓名
                        $response["schoolnum"]=$rs['schoolnum'];//添加学号
                        //为session中添加字段
                        $_SESSION['workerid']=$rs['workerid'];
                        $_SESSION["account"]=$account;
                        $_SESSION['name']=$rs['name'];
                        $_SESSION['schoolnum']=$rs['schoolnum'];
                    }else{
                        $response["msg"]="密码错误！";//添加提示信息
                    }
                    //若没查到记录
                }else{
                    $response["msg"]="不存在此用户！";//添加提示信息
                }
                //关闭mysql连接
                mysql_close($link);
            }
        return $response;
    }
    //注销
    public function logout(){
        //如果已经登录，通过workerid检测
        if(isset($_SESSION["workerid"])){
            //用空数组替代
            $_SESSION = array();
            //如果存在会话Cookie，将到期事件设置为之前1小时从而将其删除
            if(isset($_COOKIE[session_name()])){
                setcookie(session_name(),'',time()-3600);
            }
            //销毁session
            session_destroy();
        }
        echo "注销成功！";
    }
    //答复预约
    public function dealOrder(){
        if(isset($_POST['orderid'])&&isset($_POST['advice'])){
            $link = mysql_connect(SAE_MYSQL_HOST_M.':'.SAE_MYSQL_PORT,SAE_MYSQL_USER,SAE_MYSQL_PASS);
            if($link){
                mysql_select_db(SAE_MYSQL_DB,$link);
                $sql=sprintf("UPDATE `order` SET advice='%s',state='已答复',workerid='%s' WHERE orderid='%s'",mysql_real_escape_string($_POST['advice']),mysql_real_escape_string($_SESSION['workerid']),mysql_real_escape_string($_POST['orderid']));
                $result=mysql_query($sql);
                if(!$result){
                    die('数据库错误：' . mysql_error());
                }
                echo "预约答复成功！";
            }
            mysql_close($link);
        }else{
            echo "操作失败！请重试！";
        }
    }
}