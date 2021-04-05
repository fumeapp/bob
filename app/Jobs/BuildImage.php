<?php

namespace App\Jobs;

use App\DTO\ImageDto;
use App\Services\DockerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
     */
    public function handle()
    {

        $service = (new DockerService())->setDto($this->dto);
        $service->makeDirectory();
        $service->download('layer');
        $service->download('code');

        $service->unzip('layer');
        $service->unzip('code');

        // ray("cp -rp {$resourceDir} ${dir}./fume");
        // exec("cp -rp {$resourceDir} ${dir}./fume");

    }
}
