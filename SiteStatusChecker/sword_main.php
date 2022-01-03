<?php
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

main();

function main()
{
    $checker = new SiteStatusChecker();
    $checker->doCheckStatus();
}

class SiteStatusChecker
{
    private $checkUrls;

    private $notifier;

    public function loadUrl($targetUrls)
    {
        $this->checkUrls = $targetUrls;
    }

    public function setNotifier($notifier)
    {
        $this->notifier[] = $notifier;
    }

    public function sendNotification()
    {   
        foreach($this->notifier as $key => $obj)
        {
            if(get_class($this->notifier[$key]) == MailSender::class)
            {
                $this->notifier[$key]->sendNotiEmail();
            }
            // webhook 통지 기능 생략
            // if(get_class($this->notifier[$key]) == WebhookSender::class)
            // {
            //     $this->notifier[$key]->sendNotiWebhook();
            // }
        }
    }

    public function doCheckStatus()
    {   
        //점검 대상 url 불러오기
        $loader = new UrlReader();
        $loader->open();
        $urlList = $loader->getUrlList();
        $loader->close();
        $this->loadUrl($urlList); 
        if(empty($this->checkUrls))
        {
            throw new Exception("점검 대상 url이 비어 있습니다.");
        }

        //점검 curl 실행- 대상이 여러 개일 경우
        if(count($this->checkUrls) > 1)
        {
            $mh = curl_multi_init();
            foreach($this->checkUrls as $num => $aUrl)
            {
                $ch[$num] = curl_init();
                curl_setopt_array($ch[$num], array(
                    CURLOPT_URL => $aUrl, 
                    CURLOPT_RETURNTRANSFER => TRUE,
                    CURLOPT_SSL_VERIFYPEER => FALSE));
                curl_multi_add_handle($mh, $ch[$num]);
            }

            do {
                curl_multi_exec($mh, $active);
            } while ($active > 0);

            $flg = true;
            $errMsg = "<br> 오류가 발생했습니다. <br>";
            $succMsg = "<br> 아침 점검 이상 없습니다. <br>";
            foreach($ch as $ahandle)
            {
                $info = curl_getinfo($ahandle);
                $errCode = curl_errno($ahandle);
                if(!($errCode === 0 && 100 < $info['http_code'] && $info['http_code'] < 400))
                {
                    $result[] = $errMsg.
                                "curl => ".curl_strerror($errCode)."<br>".
                                print_r($info, true)."<br>";
                    $flg = false;
                }
                curl_multi_remove_handle($mh, $ch[$num]);
            }
            curl_multi_close($mh);
            if($flg)
            {
                $result[] = $succMsg;
            }
        }
        //점검 curl 실행- 대상이 하나일 경우
        else
        {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $checkUrls[0], 
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_SSL_VERIFYPEER => FALSE));
            curl_exec($ch);
            $errCode = curl_errno($ch);
            if(!($errCode === 0 && 100 < $info['http_code'] && $info['http_code'] < 400))
            {
                $result[] = $errMsg.
                            "curl => ".curl_strerror($errCode)."<br>".
                            print_r($info, true)."<br>";
            }
            else
            {
                $result[] = $succMsg;
            }
            curl_close($ch);
            
        }

        //결과 메일 통지
        $msender = new MailSender();
        $msender->loadNotiEmail();
        if($msender->getNotiEmailCount() == 0)
        {
            $msender->setNotiEmail("ict@ptbwa.com");
        }
        $msender->setEmailBody(implode('<br>', $result));
        $this->setNotifier($msender);

        // webhook 통지 기능 생략
        // $wsender = new WebhookSender();
        // $wsender->loadWebhookUrl();
        // if($wsender->getWebhookCount() == 0)
        // {   
        //     $webhookUrl = "https://ptbwa.webhook.office.com/webhookb2/8aa8b3b4-8d47-42d2-b104-2ca126f437a3@b328136c-b0be-47fc-bacc-33f01a84367b/IncomingWebhook/620318905ba74747be62bf383b250c29/212b7939-3f38-4b3b-a09d-224cca5f7b3d";
        //     $wsender->setWebhookUrl($webhookUrl);  
        // }
        // $wsender->setWebhookBody(array("text" => implode('<br>', $result)));
        // $this->setNotifier($wsender);
        
        $this->sendNotification(); 
    }
    
}


class MailSender
{
    private $notiEmail;
    private $body;

    public function loadNotiEmail()
    {
        $this->notiEmail[] = "ict@ptbwa.com";
    }

    public function setNotiEmail($email)
    {
        $this->notiEmail[] = $email;
    }

    public function removeNotiEmail($email)
    {
        $key = array_search($email, $this->notiEmail);
        if($key !== false)
        {
            unset($this->notiEmail[$key]);
        }
    }

    public function setEmailBody($body)
    {
        $this->body = $body;
    }

    public function sendNotiEmail()
    {
        $mail = new PHPMailer(true);

        try 
        {
            $mail->isSMTP();                                            
            $mail->Host       = 'smtp.office365.com';                   
            $mail->SMTPAuth   = true;                                   
            $mail->Username   = 'dev@ptbwa.com';            
            $mail->Password   = 'Ptbwa0724!!owns';          
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
            $mail->SMTPDebug  = SMTP::DEBUG_SERVER;                     
            $mail->CharSet    = 'UTF-8';
            $mail->Port       = 587;                                    
            

            
            $mail->setFrom('dev@ptbwa.com');
            foreach($this->notiEmail as $aEmail)
            {
                $mail->addAddress($aEmail);
            }

            $mail->isHTML(true);                                  
            $mail->Subject = sprintf("%d년 %d월 %d일 아침 점검 보고", date("Y"), date("m"), date("d"));
            $mail->Body    = $this->body;
            
            $mail->send();
            echo 'Message has been sent';
        } 
        catch (Exception $e) 
        {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }

    public function getNotiEmailCount()
    {
        if(empty($this->notiEmail)) return 0;
        else return count($this->notiEmail);
    }
}


class WebhookSender
{

    private $webhookUrls;
    private $webhookBody;

    public function loadWebhookUrl()
    {   
        $this->webhookUrls[] = "webhook_url";
    }

    public function setWebhookUrl($aWebhookUrl)
    {
        $this->webhookUrls[] = $aWebhookUrl;
    }

    public function removeWebhookUrl($aWebhookUrl)
    {
        $key = array_search($aWebhookUrl, $this->webhookUrls);
        if($key !== false)
        {
            unset($this->webhookUrls[$key]);
        }
    }
    public function getWebhookCount()
    {
        if(empty($this->webhookUrls)) return 0;
        else return count($this->webhookUrls);
    }

    public function setWebhookBody($body)
    {
        $this->webhookBody = $body;
    }

    public function sendNotiWebhook()
    {
        if(empty($this->webhookUrls))
        {
            throw new Exception("URL을 설정해 주세요.");
        }
        else if(count($this->webhookUrls) > 1)
        {
            $mh = curl_multi_init();
            foreach($this->webhookUrls as $key => $value)
            {
                $ch[$key] = curl_init();
                curl_setopt_array($ch[$key], array(
                    CURLOPT_URL => $value,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POSTFIELDS     => json_encode($this->webhookBody)
                ));
                curl_multi_add_handle($mh, $ch[$key]);
            }
            do {
                curl_multi_exec($mh, $active);
            } while ($active > 0);

            foreach($ch as $ahandle)
            {
                $info = curl_getinfo($ahandle);
                $errCode = curl_errno($ahandle);
                if(curl_errno($ahandle))
                {
                    throw new Exception("could not be sent. Webhook Error:(".curl_strerror($errCode).$info.")");
                    $flg = false;
                }
                curl_multi_remove_handle($mh, $ch[$key]);
            }
            curl_multi_close($mh);
        }
        else 
        {
            $webhook = $this->webhookUrls[0];
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $webhook,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS     => json_encode($this->webhookBody)
            ));    
            curl_exec($ch);
            $info = curl_getinfo($ch);
            $errCode = curl_errno($ch);
            if($errCode)
            {
                throw new Exception("could not be sent. Webhook Error:(".curl_strerror($errCode).$info.")");
            }
            curl_close($ch);
        }
    }
}



class UrlReader
{
    private $link;
    private $sql;

    function open()
    {
        $ip = "10.110.49.152";
        $port = "3306";
        $username  = "mis.notification";
        $password = "Ptbw@1234";
        $db = "mis_mngt_servicedb";
        $this->link = mysqli_connect($ip, $username, $password, $db, $port);

    }    

    function loadGetUrlListSql()
    {
        $sql = "SELECT 
                    CONCAT(a.BaseUrl, substring(b.pagePath,2)) as pageurl
                FROM 
                    mis_mngt_servicedb.gcloudlandinggroup as a, mis_mngt_servicedb.gcloudlanding as b
                WHERE 
                    a.GcloudLandingGroupIdx = b.GcloudLandingGroupIdx AND 
                    b.UseYN = 'Y' AND 
                    current_timestamp() <= ifnull(b.SuspendDate, current_timestamp()) AND 
                    (b.StartDate <= current_timestamp() AND current_timestamp() <= ifnull(b.EndDate, current_timestamp())) AND
                    a.baseURL not LIKE '%welcomeloan.co.kr%';";
        $this->sql = $sql;
    }

    function getUrlList()
    {
        $this->loadGetUrlListSql();
        if(empty($this->link)) 
        {
            die('Connect Error: ' . mysqli_connect_error());
        } 
        else{
            $rows = mysqli_query($this->link, $this->sql);
            if(!empty($rows)) 
            {
                while($row = $rows->fetch_array()) 
                {
                    $urlList[] = $row['pageurl'];
                }
            }
        }
        return $urlList;
    }

    function close()
    {
        $this->link -> close();
    }

}

?>
