<?php


namespace App\DTO;


use JetBrains\PhpStorm\Pure;

class ImageDto
{

    public StsDto $sts;
    public string $token;
    /**
     * @var string ECR region
     */
    public string $region;
    /**
     * @var string ECR Repository name
     */
    public string $repository;
    public string $identity;
    public int $projectId;
    public int $depId;
    public S3Dto $s3;

    /**
     * @var string Fume API URL
     */
    public string $apiUrl;

    #[Pure] public function __construct($args) {
        $this->sts = new StsDto($args['sts']);
        $this->s3 = new S3Dto($args['s3']);
        $this->token = $args['token'];
        $this->region = $args['region'];
        $this->repository = $args['repository'];
        $this->identity = $args['identity'];
        $this->projectId = $args['projectId'];
        $this->depId = $args['depId'];
        $this->apiUrl = $args['apiUrl'];
    }
    public function domain(): string
    {
        return "{$this->identity}.dkr.ecr.{$this->region}.amazonaws.com";
    }
    public function depUrl(): string
    {
        return "/project/{$this->projectId}/dep/{$this->depId}";
    }

    #[Pure] public function tag(): string
    {
        return "{$this->domain()}/{$this->repository}:dep-{$this->depId}";
    }
}
