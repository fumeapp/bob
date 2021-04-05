<?php
namespace App\DTO;

class S3Dto
{
    /* @var string|mixed bucket URL */
    public string $bucket;
    // Code ZIP
    public string $code;
    // Layer ZIP
    public string $layer;
    // Headless ZIP (mode: headless)
    public string $headless;

    /**
     * StsDto constructor.
     * @param array $args
     */
    public function __construct(array $args) {
        $this->bucket = $args['bucket'];
        $this->code = $args['code'];
        $this->layer = $args['layer'];
        $this->headless = $args['headless'];
    }
}
