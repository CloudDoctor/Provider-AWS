<?php

namespace CloudDoctor\Amazon;

use Aws\Ec2\Ec2Client;
use GuzzleHttp\Client as GuzzleClient;
use CloudDoctor\Interfaces\RequestInterface;

class Request extends \CloudDoctor\Common\Request implements RequestInterface
{

    /** @var Ec2Client[] */
    protected $ec2Clients = [];
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function setupForRegion(string $region) : Ec2Client
    {
        if (!isset($this->ec2Clients[$region])) {
            $options = [
                'region' => $region,
                'version' => '2016-11-15',
                'credentials' => [
                    'key'    => $this->config['api-key'],
                    'secret' => $this->config['api-secret'],
                ],
            ];
            $this->ec2Clients[$region] = new Ec2Client($options);
        }

        return $this->ec2Clients[$region];
    }

    public function acrossRegionAction(callable $callable) : array
    {
        $merge = [];
        foreach ($this->ec2Clients as $region => $ec2Client) {
            $merge = array_merge(
                $merge,
                call_user_func($callable, $region, $ec2Client) ?:[]
            );
        }
        return $merge;
    }
}
