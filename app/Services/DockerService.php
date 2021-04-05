<?php


namespace App\Services;

use App\DTO\ImageDto;
use Illuminate\Support\Facades\Http;

class DockerService
{
    public Http $client;
    private array $options;
    private ImageDto $dto;

    private string $directory;

    public function __construct()
    {
        $this->options = [
            'curl' => [
                CURLOPT_UNIX_SOCKET_PATH => config('docker.socket'),
            ]
        ];
    }

    public function setDto(ImageDto $dto): DockerService
    {
        $this->dto = $dto;
        $this->directory = storage_path() . '/fume-' . $this->dto->projectId . '-' . $this->dto->depId;
        return $this;
    }

    public function makeDirectory(): bool
    {
        if (!is_dir($this->directory)) {
            return mkdir($this->directory);
        }
        return false;
    }

    public function download($type): string
    {
        return exec("{$this->dto->sts->toEnv()} aws s3 cp s3://{$this->dto->s3->bucket}/{$this->dto->s3->{$type}} {$this->directory}");
    }

    public function unzip($type): string
    {
        return exec("unzip {$this->directory}/{$$this->dto->s3->{$type}} -d {$this->directory}/");
    }


    public function images()
    {
        return HTTP::withOptions($this->options)->get('http:/v1.41/images/json')->json();
    }

}
