<?php

namespace App\Jobs;

use App\DTO\ImageDto;
use App\Services\DockerService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BuildImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job cna run before timing out.
     * @var int
     */
    public int $timeout = 400;

    /**
     * @var ImageDto
     */
    private ImageDto $dto;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(ImageDto $dto)
    {
        $this->dto = $dto;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {

        $service = (new DockerService())->setDto($this->dto);
        $service->makeDirectory();
        $service->download('layer');
        $service->download('code');
        $service->unzip('layer');
        $service->unzip('code');
        $service->copyAssets();
        $service->build();
        $digest = $service->push();
        $service->update($digest);
        $service->cleanup();

    }
}
