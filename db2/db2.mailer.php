<?php
/**
 * (c) 2025 Martin Smidek <martin@smidek.eu> - rozšíření PHPMailer pro projekt Answer
 * 
 * umožňuje používat smtp.google.com pomocí OAuth2
 * řešení zamezuje opakované autorizaci 
 * třídy se vytváří až při new Ezer_PHPMailer
 */

spl_autoload_register(function ($class) {
    $phpmailer_path = $_SERVER['DOCUMENT_ROOT'] . "/ezer3.2/server/licensed/phpmailer";

    $map = [
        'PHPMailer' => "$phpmailer_path/class.phpmailer.php",
        'SMTP'      => "$phpmailer_path/class.smtp.php",
        'Ezer_PHPMailer' => __DIR__ . '/db2.mailer.php', // Tento soubor obsahuje definici třídy Ezer_PHPMailer
    ];

    if (isset($map[$class])) {
        require_once $map[$class];
    }
});


class Ezer_PHPMailer extends PHPMailer {
  protected $serverConfig;
  protected $oauthClient;
  // Cache pro autorizace jednotlivých serverů
  protected static $oauthClientsCache= [];
  // konstruktor
  public function __construct($serverConfig) {
    parent::__construct(true); // true = umožní výjimky
    $this->serverConfig= $serverConfig;
    // Nastavení serveru
    $this->isSMTP();
    $this->Host= $serverConfig->Host;
    $this->Port= $serverConfig->Port;
    $this->SMTPAuth= $serverConfig->SMTPAuth;
    // Řešení pro gmail
    if ($this->Host === 'smtp.gmail.com') {
      // Klíč pro cache - může být třeba hostname serveru
      $cacheKey= $this->Host;
      if (!isset(self::$oauthClientsCache[$cacheKey])) {
        try {
          self::$oauthClientsCache[$cacheKey]= $this->createOAuthClient($serverConfig);
        } 
        catch (Exception $e) {
          throw new Exception('Selhání při vytváření OAuth2 klienta: ' . $e->getMessage());
        }
      }      
      // Použít existujícího klienta
      $this->oauthClient= self::$oauthClientsCache[$cacheKey];
      // Nastavit OAuth2 přihlašování do PHPMaileru
      $this->AuthType = 'XOAUTH2';
      $this->setOAuth(
          new \PHPMailer\PHPMailer\OAuth([
              'provider' => new \League\OAuth2\Client\Provider\Google([
                  'clientId' => $serverConfig->clientId,
                  'clientSecret' => $serverConfig->clientSecret,
              ]),
              'clientId' => $serverConfig->clientId,
              'clientSecret' => $serverConfig->clientSecret,
              'refreshToken' => $serverConfig->refreshToken,
              'userName' => $serverConfig->email,
          ])
      );
    } 
    else {
      // Klasické SMTP přihlašování
      
      
      $credentials_path= __DIR__.'/../../files/setkani4/credential.json';
      if (!is_file($credentials_path) || !is_readable($credentials_path)) {
        throw new Exception("CHYBA při odesílání mailu došlo k chybě: nepřístupný creditals");
      }
      display(file_get_contents($credentials_path));
      
      
      $this->Username= $serverConfig->Username;
      $this->Password= $serverConfig->Password;
      $this->From= $serverConfig->Username;
      $this->IsHTML(true);  
      $this->Mailer= "smtp";
      foreach ($serverConfig as $part=>$value) {
        if ($part=="SMTPOptions" && $value=="-")
          $this->SMTPOptions= array('ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true));
        else
          $this->$part= $value;
      }
    }
  }
  // Vytvoří a nastaví nový Google_Client pro OAuth2.
  protected function createOAuthClient($serverConfig) {
    $gmail_api_library= $_SERVER['DOCUMENT_ROOT'].'/ezer3.2/server/licensed/google_api/vendor/autoload.php';
    require_once $gmail_api_library;
    $client = new Google_Client();
    // získání údajů pro autentizaci
    $credentials_path= __DIR__.'/../../files/setkani4/credential.json';
    $tokenPath= __DIR__.'/../../files/setkani4/token_$serverConfig->Username.json';
//    $credentials_path= $_SERVER['DOCUMENT_ROOT'].'/../files/setkani4/credential.json';
//    $tokenPath= $_SERVER['DOCUMENT_ROOT']."/../files/setkani4/token_$serverConfig->Username.json";
    if (!is_file($tokenPath) || !is_readable($tokenPath)) {
      throw new Exception("CHYBA při odesílání mailu došlo k chybě: nepřístupný token");
    }
    $serverConfig->refreshToken= json_decode(file_get_contents($tokenPath), true);
    if (!is_file($credentials_path) || !is_readable($credentials_path)) {
      throw new Exception("CHYBA při odesílání mailu došlo k chybě: nepřístupný creditals");
    }
    $creditals= json_decode(file_get_contents($credentials_path), true);
    $serverConfig->clientId= $creditals['web']['client_id'];
    $serverConfig->clientSecret= $creditals['web']['client_secret'];
    //
    $client->setClientId($serverConfig->clientId);
    $client->setClientSecret($serverConfig->clientSecret);
    $client->setAccessType('offline');
    $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');

    if (empty($serverConfig->refreshToken)) {
        throw new Exception('Refresh token není zadán.');
    }

    try {
        $client->refreshToken($serverConfig->refreshToken);
    } catch (Google_Service_Exception $e) {
        throw new Exception('Chyba při obnovování tokenu: ' . $e->getMessage());
    } catch (Exception $e) {
        throw new Exception('Obecná chyba při obnovování tokenu: ' . $e->getMessage());
    }

    // Je client validní?
    if (!$client->getAccessToken() || $client->isAccessTokenExpired()) {
        throw new Exception('Neplatný nebo expirovaný access token.');
    }

    return $client;
  }

}
