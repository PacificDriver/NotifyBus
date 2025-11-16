<?php

namespace App\Jobs;

use App\Models\ImportState;
use App\Services\PbOrderItemImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunPbOrderImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array<string,mixed>
     */
    protected array $options;

    /**
     * Create a new job instance.
     *
     * @param  array<string,mixed>  $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(PbOrderItemImporter $importer): void
    {
        $importOptions = [];

        if (!empty($this->options['force_full'])) {
            ImportState::where('key', 'pb_order_item')->delete();
            $importOptions['since_id'] = 0;
        } elseif (isset($this->options['since_id'])) {
            $importOptions['since_id'] = (int) $this->options['since_id'];
        }

        if (!empty($this->options['race_ids'])) {
            $importOptions['race_ids'] = (array) $this->options['race_ids'];
        }

        if (!empty($this->options['chunk'])) {
            $importOptions['chunk'] = (int) $this->options['chunk'];
        }

        Log::info('PbOrderItem import job started', [
            'options' => $this->options,
            'effective_options' => $importOptions,
        ]);

        $stats = $importer->import($importOptions);

        Log::info('PbOrderItem import job completed', [
            'options' => $this->options,
            'effective_options' => $importOptions,
            'stats' => $stats,
        ]);
    }
}


