<?php
namespace App\Services;

use App\DTO\ImageDto;
use Illuminate\Support\Facades\Http;
use JetBrains\PhpStorm\ArrayShape;

class DockerService
{
    public Http $client;
    private array $options;
    private ImageDto $dto;
    private string $uri = 'http:/v1.41';

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
        if (is_dir($this->directory)) {
            exec("rm -rf {$this->directory}");

        }
        return mkdir($this->directory);
    }

    public function download($type): string
    {
        return exec("{$this->dto->sts->toEnv()} aws s3 cp s3://{$this->dto->s3->bucket}/{$this->dto->s3->{$type}} {$this->directory}");
    }

    public function unzip($type): string
    {
        return exec("unzip {$this->directory}/{$this->dto->s3->{$type}} -d {$this->directory}/");
    }

    public function copyAssets(): string
    {
        $assets = resource_path('nuxt');
        return exec("cp -r $assets {$this->directory}/.fume");
    }

    public function images()
    {
        return Http::withOptions($this->options)->get($this->uri . '/images/json')->json();
    }

    #[ArrayShape(['output' => "", 'result_code' => ""])] public function build(): array
    {
        $binary = config('docker.binary');
        $dockerfile = resource_path('nuxt.Dockerfile');
        $tag = $this->dto->tag();
        exec("$binary build --progress plain -t $tag -f $dockerfile {$this->directory}/ 2>&1", $output, $result);
        return [
            'output' => $output,
            'result_code' => $result,
        ];
    }

    public function getPassword(): string
    {
        $env = $this->dto->sts->toEnv();
        exec("{$env} aws ecr get-login-password --region {$this->dto->region}", $output, $result);
        return $output[0];
    }

    public function push(): string
    {
        $password = $this->getPassword();
        $result = Http
            ::withOptions($this->options)
            ->withHeaders([
                'X-Registry-Auth' => base64_encode(json_encode([
                    'username' => 'AWS',
                    'password' => $password,
                    'serveraddress' => "https://{$this->dto->domain()}",
                ])),
            ])
            ->post($this->uri . "/images/{$this->dto->tag()}/push", [
                'name' => $this->dto->repository,
                'tag' => $this->dto->tag(),
            ]);
        preg_match('/digest: sha256:([0-9a-f]{64})/', $result->body(), $matches);
        return $matches[1];
    }

    public function update(string $digest)
    {
        return Http
            ::withToken($this->dto->token)
            ->put($this->dto->apiUrl . "/project/{$this->dto->projectId}/dep/{$this->dto->depId}", [
            'status' => 'IMAGE_COMPLETE',
            'digest' => $digest,
        ]);
    }

    public function cleanup(): string
    {
        exec("rm -rf {$this->directory}");
        return Http
            ::withOptions($this->options)
            ->delete($this->uri . "/images/{$this->dto->tag()}")
            ->body();
    }
}
