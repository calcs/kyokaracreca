<?php
  define('APP_PATH', __DIR__ . '/');
  include(APP_PATH . 'config.php');
  
  if ($config['force_ssl'] === true && empty($_SERVER['HTTPS'])) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '?' . $_SERVER['QUERY_STRING']);
    exit;
  }
  
  if ($config['disable_iframe'] === true) {
    header('X-FRAME-OPTIONS: DENY');
  }
  
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validate();
  }
  
  if ($_SERVER['REQUEST_METHOD'] === 'GET' || count($errors) > 0 || isset($_POST['back'])) {
    include 'tmpl/index.html';
  } else {
    if (isset($_POST['confirm'])) {
      include 'tmpl/confirm.html';
    } else {
      require_once("./lib/Stripe.php");
      Stripe::setApiKey($config['webpay_secret_key']);
      Stripe::$apiBase = "https://api.webpay.jp";
      
      try{
        $result = Stripe_Charge::create(array(
           "amount" => $_POST['amount'],
           "currency" => "jpy",
           "card" => 
            array("number" => $_POST['card_num'],
             "exp_month" => $_POST['exp_month'],
             "exp_year" => $_POST['exp_year'],
             "cvc" => $_POST['cvc'],
             "name" => $_POST['card_name']),
             "description" => isset($_GET['m'])?$_GET['m']:null
        ));
      } catch (Stripe_CardError $e) {
        $body = $e->getJsonBody();
        $err  = $body['error']['code'];
        $errors = array();
        
        if ($err == "incorrect_number") {
          $errors['card_num'] = "カード番号が違います。";
        } elseif ($err == "invalid_expiry_month") {
          $errors['exp'] = "カードの有効期限月が不正です。";
        } elseif ($err == "invalid_expiry_year") {
          $errors['exp'] = "カードの有効期限年が不正です。";
        } elseif($err == "invalid_cvc") {
          $errors['cvc'] = "CVCセキュリティコードが不正です。";
        } elseif($err == "expired_card") {
          $errors['exp'] = "既に失効したカードです。";
        } elseif ($err == "incorrect_cvc") {
          $errors['cvc'] = "CVCセキュリティーコードが違います。";
        } elseif ($err == "card_declined") {
          $errors['amount'] = "カードが決済に失敗しました。";
        } elseif ($err == "missing") {
          $errors['amount'] = "請求を行った顧客にカードが紐付いていません。"; //将来的措置
        } elseif ($err == "processing_error") {
          $errors['amount'] = "処理中にエラーが発生しました。";
        }
        include 'tmpl/index.html';
        exit;
      }
      
      if ($config['do_notify'] === true) {
        $body = "{$config['page_title']}にて決済が有りました。\n日時: " . date('Y-m-d H:i:s') . "\n金額: {$_POST['amount']}\n\n詳しくはWebpayの管理画面で確認ください。";
      
        mb_language('uni');
        mb_internal_encoding('UTF-8');
        mb_send_mail($config['notify_mail'], $config['notify_subject'], $body, 'From: ' . $config['notify_from']);
      }
      
      include 'tmpl/complete.html';
    }
  }
  
  function validate() {
    $errors = array();
  
    $_POST['card_num'] = trim(str_replace(array('-', '－', 'ー', ' ', '　'), '', mb_convert_kana($_POST['card_num'], 'sa')));
    $_POST['exp_month'] = (int)trim($_POST['exp_month']);
    $_POST['exp_year'] = (int)trim($_POST['exp_year']);
    $_POST['cvc'] = trim(mb_convert_kana($_POST['cvc'], 'sa'));
    $_POST['card_name'] = trim(mb_convert_kana($_POST['card_name'], 'sa'));
    $_POST['amount'] = trim(str_replace(array(',', '，'), '', mb_convert_kana($_POST['amount'], 'sa')));
    
    if (strlen($_POST['card_num']) < 13 || strlen($_POST['card_num']) > 16) {
      $errors['card_num'] = 'カード番号の桁数が不正です';
    } elseif (!ctype_digit($_POST['card_num'])) {
      $errors['card_num'] = 'カード番号は数字で入力してください';
    } elseif (!check_by_luhn($_POST['card_num'])) {
      $errors['card_num'] = 'カード番号が不正です';
    }
    
    if (strlen($_POST['cvc']) !== 3 && strlen($_POST['cvc']) !== 4) {
      $errors['cvc'] = 'CVCの桁数が不正です';
    } elseif (!ctype_digit($_POST['cvc'])) {
      $errors['cvc'] = 'CVCは数字で入力してください';
    }
    
    if (count(explode(' ', $_POST['card_name'])) !== 2) {
      $errors['card_name'] = 'カード名義人名はスペースで区切られている必要があります';
    }
    
    if ($_POST['exp_month'] < 1 || $_POST['exp_month'] > 12) {
      $errors['exp'] = '有効期限が不正です';
    } elseif ($_POST['exp_year'] < date('Y') || $_POST['exp_year'] > (date('Y') + 10)) {  //この10は11年以上先まで有効期限を持つカードはないだろうという見込みで書かれている。Magig Number
      $errors['exp'] = '有効期限が不正です';
    } elseif (date('Ym') > (int)($_POST['exp_year'] . sprintf('%02d', $_POST['exp_month']))) {
      $errors['exp'] = '有効期限が切れています';
    }
    
    global $config;
    if ($config['fixed_amount'] === true) {
      $_POST['amount'] = $config['default_amount'];
    }
    
    if (!ctype_digit($_POST['amount'])) {
      $errors['amount'] = '決済金額は整数の数字で入力してください。';
    } elseif ($_POST['amount'] < $config['min_amount']) {
      $errors['amount'] = '決済金額が最低金額(' . $config['min_amount'] . '円)を下回っています。';
    } elseif ($config['max_amount'] !== 0 && $_POST['amount'] > $config['max_amount']) {
      $errors['amount'] = '決済金額が最大金額(' . $config['max_amount'] . '円)を上回っています。';
    }
    
    return $errors;
  }
  
  function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
  
  /**
  * Luhn Algorithm(Mod 10)による、カード番号検査
  */
  function check_by_luhn($number)
  {
    //桁数によるparityの判定
    $parity = strlen($number) % 2;

    $total = 0;
    for ($i = 0; $i < strlen($number); $i++) {
      $digit = $number[$i];
      
      if ($i % 2 == $parity) {
        $digit *= 2;
        if ($digit > 9) {
          $digit -= 9;
        }
      }
      $total += $digit;
    }

    return (($total % 10) === 0) ? true : false;
  }
  
  function trunc_credit_card_num($str) {
    $buf = $str;
  
    for($i = 0; $i < strlen($buf)-4; $i++) {
      $buf[$i] = '*';
    }
    return str_replace(array('-', '－'), '', $buf);
  }