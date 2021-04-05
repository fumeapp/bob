<?php
namespace App\DTO;

class StsDto
{
    public string $accessKeyId;
    public string $secretAccessKey;
    public string $sessionToken;

    /**
     * StsDto constructor.
     * @param array $args
     */
    public function __construct(array $args) {
        $this->accessKeyId = $args['AccessKeyId'];
        $this->secretAccessKey = $args['SecretAccessKey'];
        $this->sessionToken = $args['SessionToken'];
    }

    public function toEnv(): string
    {
        return "AWS_ACCESS_KEY_ID={$this->accessKeyId} AWS_SECRET_ACCESS_KEY={$this->secretAccessKey} AWS_SESSION_TOKEN={$this->sessionToken}";
    }
}
