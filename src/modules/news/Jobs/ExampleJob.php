<?php

namespace NukeViet\Module\news\Jobs;

class ExampleJob
{
    public static function handle(array $data): bool
    {
        echo "ExampleJob processing: " . json_encode($data) . "\n";
        return true;
    }
}
