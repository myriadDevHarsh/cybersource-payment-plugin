<?php

/*
* Purpose : passing Authentication config object to the configuration
*/

namespace CyberSource;

//require_once __DIR__. DIRECTORY_SEPARATOR .'../vendor/autoload.php';

class ExternalConfiguration
{
    private $merchantConfig;
    private $intermediateMerchantConfig;

    private $authType;
    private $merchantID;
    private $apiKeyID;
    private $secretKey;
    private $useMetaKey;
    private $portfolioID;
    private $keyAlias;
    private $keyPass;
    private $keyFilename;
    private $keyDirectory;
    private $runEnv;
    private $IntermediateHost;
    private $jwePEMFileDirectory;
    private $enableClientCert;
    private $clientCertDirectory;
    private $clientCertFile;
    private $clientCertPassword;
    private $clientId;
    private $clientSecret;
    private $enableLogging;
    private $debugLogFile;
    private $errorLogFile;
    private $logDateFormat;
    private $logFormat;
    private $logMaxFiles;
    private $logLevel;
    private $enableMasking;
    private $defaultDeveloperId;

    //initialize variable on constructor
    public function __construct($merchantID, $apiKeyID, $secretKey, $host)
    {
        $this->authType = "http_signature";//http_signature/jwt
        $this->merchantID = $merchantID;
        $this->apiKeyID = $apiKeyID;
        $this->secretKey = $secretKey;

        // MetaKey configuration [Start]
        $this->useMetaKey = false;
        $this->portfolioID = "";
        // MetaKey configuration [End]

        $this->keyAlias = "testrest";
        $this->keyPass = "testrest";
        $this->keyFilename = "testrest";
        $this->keyDirectory = "cert/";
        $this->runEnv = "apitest.cybersource.com";

        // new property has been added for user to configure the base path so that request can route the API calls via Azure Management URL.
        // Example: If intermediate url is https://manage.windowsazure.com then in property input can be same url or manage.windowsazure.com.
        $this->IntermediateHost = "https://manage.windowsazure.com";

        //PEM Key file path for decoding JWE Response Enter the folder path where the .pem file is located.
        // It is optional property, require adding only during JWE decryption.
        $this -> jwePEMFileDirectory = "cert/NetworkTokenCert.pem";

        //Add the property if required to override the cybs default developerId in all request body
        $this->defaultDeveloperId = "";


        //OAuth related config
        $this->enableClientCert = false;
        $this->clientCertDirectory = "cert/";
        $this->clientCertFile = "";
        $this->clientCertPassword = "";
        $this->clientId = "";
        $this->clientSecret = "";

        // New Logging
        $this->enableLogging = true;
        $this->debugLogFile = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "Log" . DIRECTORY_SEPARATOR . "debugTest.log";
        $this->errorLogFile = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "Log" . DIRECTORY_SEPARATOR . "errorTest.log";
        $this->logDateFormat = "Y-m-d\TH:i:s";
        $this->logFormat = "[%datetime%] [%level_name%] [%channel%] : %message%\n";
        $this->logMaxFiles = 3;
        $this->logLevel = "debug";
        $this->enableMasking = true;

        $this->merchantConfigObject();
        // $this->merchantConfigObjectForIntermediateHost();
        // $this->jwePEMFileDirectory = "Resources".DIRECTORY_SEPARATOR."NetworkTokenCert.pem";
    }

    //creating merchant config object
    public function merchantConfigObject()
    {
        if (!isset($this->merchantConfig)) {
            $config = new \CyberSource\Authentication\Core\MerchantConfiguration();
            $config->setauthenticationType(strtoupper(trim($this->authType)));
            $config->setMerchantID(trim($this->merchantID));
            $config->setApiKeyID($this->apiKeyID);
            $config->setSecretKey($this->secretKey);
            $config->setKeyFileName(trim($this->keyFilename));
            $config->setKeyAlias($this->keyAlias);
            $config->setKeyPassword($this->keyPass);
            $config->setUseMetaKey($this->useMetaKey);
            $config->setPortfolioID($this->portfolioID);
            $config->setKeysDirectory(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . $this->keyDirectory);
            $config->setJwePEMFileDirectory($this -> jwePEMFileDirectory);
            $config->setRunEnvironment($this->runEnv);

            //Add the property if required to override the cybs default developerId in all request body
            $config->setDefaultDeveloperId($this->defaultDeveloperId);

            // New Logging
            $logConfiguration = new \CyberSource\Logging\LogConfiguration();
            $logConfiguration->enableLogging($this->enableLogging);
            $logConfiguration->setDebugLogFile($this->debugLogFile);
            $logConfiguration->setErrorLogFile($this->errorLogFile);
            $logConfiguration->setLogDateFormat($this->logDateFormat);
            $logConfiguration->setLogFormat($this->logFormat);
            $logConfiguration->setLogMaxFiles($this->logMaxFiles);
            $logConfiguration->setLogLevel($this->logLevel);
            $logConfiguration->enableMasking($this->enableMasking);
            $config->setLogConfiguration($logConfiguration);

            $config->validateMerchantData();
            $this->merchantConfig = $config;
        } else {
            return $this->merchantConfig;
        }
    }

    public function ConnectionHost()
    {
        $merchantConf = $this->merchantConfigObject();
        $config = new Configuration();
        $config->setHost($merchantConf->getHost());
        $config->setLogConfiguration($merchantConf->getLogConfiguration());
        return $config;
    }
}
