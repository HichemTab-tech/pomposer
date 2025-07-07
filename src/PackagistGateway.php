<?php

namespace HichemTabTech\Pomposer;

use RuntimeException;

class PackagistGateway
{
    /**
     * @var array<string, array>
     */
    protected array $cache = [];
    protected $streamContext;

    public function __construct()
    {
        $options = [
            'http' => [
                'header' => "User-Agent: HichemTabTech/Pomposer/1.0\r\n"
            ]
        ];
        $this->streamContext = stream_context_create($options);
    }

    /**
     * @param string $name The full package name (e.g., "vendor/package").
     * @return array The package versions metadata.
     */
    public function getPackageMetadata(string $name): array
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        echo "    ➡️  Fetching metadata for $name from packagist.org\n";

        $url = "https://repo.packagist.org/p2/$name.json";
        $json = @file_get_contents($url, false, $this->streamContext);

        if ($json === false) {
            throw new RuntimeException("Failed to fetch metadata for $name from $url");
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Failed to decode JSON metadata for $name. Error: " . json_last_error_msg());
        }

        $this->cache[$name] = $data['packages'][$name] ?? [];

        return $this->cache[$name];
    }
}