<?php
namespace App\Services;

use App\DTO\ImageDto;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
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

    public function download($type): array
    {
        if ($type === 'server') {
            exec("{$this->dto->sts->toEnv()} aws s3 cp s3://{$this->dto->s3->bucket}/server {$this->directory}/server --recursive", $output, $result);
        } else {
            exec("{$this->dto->sts->toEnv()} aws s3 cp s3://{$this->dto->s3->bucket}/{$this->dto->s3->{$type}} {$this->directory}", $output, $result);
        }

        if ($result === 1) {
            $this->rm();
            $this->fail("Error copying {$type} as s3://{$this->dto->s3->bucket}/{$this->dto->s3->{$type}}", $output);
        }

        return [
            'output' => $output,
            'result_code' => $result,
        ];
    }

    public function unzip($type): string
    {
       $result = exec("unzip {$this->directory}/{$this->dto->s3->{$type}} -d {$this->directory}/");
       return $result;
    }

    public function copyAssets(): string
    {
        if ($this->dto->nitro) {
            $assets = resource_path('nitro.js');
            return exec("cp -r $assets {$this->directory}/fume.js");
        }
        if ($this->dto->framework === 'NestJS') {
            $assets = resource_path('nest');
            exec("unzip -o $assets/node_modules.zip -d {$this->directory}");
            return exec("cp -r $assets/fume.js {$this->directory}/dist/fume.js");
        }
        $assets = resource_path('nuxt');
        return exec("cp -r $assets {$this->directory}/.fume");
    }

    public function images()
    {
        return Http::withOptions($this->options)->get($this->uri . '/images/json')->json();
    }

    /**
     * @throws Exception
     */
    #[ArrayShape(['output' => "", 'result_code' => ""])] public function build(): array
    {
        $binary = config('docker.binary');
        if ($this->dto->nitro) {
            $dockerfile = resource_path('nuxt-nitro.Dockerfile');
        } elseif ($this->dto->framework === 'NestJS') {
            $dockerfile = resource_path('nest.Dockerfile');
        } elseif ($this->dto->framework === 'Tonic') {
            $dockerfile = resource_path('tonic.Dockerfile');
        } else {
            $dockerfile = resource_path('nuxt.Dockerfile');
        }
        if (!$this->dto->nitro) {
            $config = yaml_parse_file($this->directory . '/fume.yml');
            if (isset($config[ 'nuxt' ]) && isset($config[ 'nuxt' ][ 'srcDir' ])) {
                $dockerFileContents = explode("\n", file_get_contents($dockerfile));

                $srcDir = $config[ 'nuxt' ][ 'srcDir' ];
                if (!str_ends_with($srcDir, '/')) {
                    $srcDir .= '/';
                }

                foreach ($dockerFileContents as $key => $value) {
                    if ($value === 'COPY static /var/task/static') {
                        $dockerFileContents[ $key ] = "COPY {$srcDir}static /var/task/static";
                    }
                }

                $dockerfile = "{$this->directory}/Dockerfile";
                file_put_contents($dockerfile, implode("\n", $dockerFileContents));
            }
        }

        $tag = $this->dto->tag();
        exec("$binary build --platform linux/amd64 --progress plain -t $tag -f $dockerfile $this->directory/ 2>&1", $output, $result);

        if ($result === 1) {
            $this->rm();
            $this->fail('Error building image', $output);
        }
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

        if (!isset($matches[1])) {
            $this->fail('Error pushing image', $result->body());
        }

        return $matches[1];
    }

    public function update(string $digest): PromiseInterface|Response
    {
        return Http
            ::withToken($this->dto->token)
            ->put($this->dto->apiUrl . "/project/{$this->dto->projectId}/dep/{$this->dto->depId}", [
            'status' => 'IMAGE_COMPLETE',
            'digest' => $digest,
        ]);
    }

    /**
     * @throws Exception
     */
    private function fail(string $reason, mixed $payload): void
    {
        $this->rm();
        Http
            ::withToken($this->dto->token)
            ->put($this->dto->apiUrl . $this->dto->depUrl(), [
                'status' => 'FAILURE',
                'failure' => [
                    'message' => $reason,
                    'detail' => $payload,
                ],
            ]);
        throw new Exception($reason);
    }

    private function rm(): string
    {
        return exec("rm -rf {$this->directory}");
    }

    private function removeImage(): string
    {
        return Http
            ::withOptions($this->options)
            ->delete($this->uri . "/images/{$this->dto->tag()}")
            ->body();
    }

    public function cleanup(): string
    {
        $this->rm();
        return $this->removeImage();
    }
}
