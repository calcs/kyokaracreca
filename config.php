<?php
  if (!defined('APP_PATH')) exit;

  /**
  * 設定ファイル
  */
  
  $config = array(
    'force_ssl' => false,  //非SSLで接続した時に強制的にSSL接続にするオプション。本番時はtrueを強く推奨
    'disable_iframe' => true, //クリックジャッキング対策として、iframeで決済画面が読み込めないようにするオプション。trueを強く推奨
  
    'do_notify' => true,  //メールアドレスに決済の通知をするか否か
    'notify_mail' => 'from@example.com',  //決済時の通知先メールアドレス
    'notify_subject' => '決済が有りました', //通知メールの件名
    'notify_from' => 'test@example.com',
    'page_title' => 'サンプル決済', //ページのタイトル
    'back_url' => 'http://www.example.com/',  //「戻る」をクリックした時の遷移先
    'min_amount' => 50, //最低の決済額（円）。50円だと、決済手数料で赤字になることはない2013年7月10日
    'max_amount' => 0, //最大の決済額。これを超える決済はできない。0で無制限
    'default_amount' => 100,  //GET引数による値段引渡しを使わない場合の標準価格
    'fixed_amount' => false, //固定金額決済モード。default_amountから、GET引数でもPOSTでも変更できなくなる。
    
    'webpay_secret_key' => 'test_secret_eHn4TTgsGguBcW764a2KA8Yd',
  );