<?php


namespace App\DTO;


use JetBrains\PhpStorm\Pure;

class ImageDto
{

    public StsDto $sts;
    public string $token;
    public string $repository;
    public int $identity;
    public int $projectId;
    public int $depId;
    public S3Dto $s3;

    #[Pure] public function __construct($args) {
        $this->sts = new StsDto($args['sts']);
        $this->s3 = new S3Dto($args['s3']);
        $this->token = $args['token'];
        $this->repository = $args['repository'];
        $this->identity = $args['identity'];
        $this->projectId = $args['projectId'];
        $this->depId = $args['depId'];
    }
}
