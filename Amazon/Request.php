<?php

namespace CloudDoctor\Amazon;

use Aws\Ec2\Ec2Client;
use Aws\Pricing\PricingClient;
use Aws\Result as AwsResult;
use GuzzleHttp\Client as GuzzleClient;
use CloudDoctor\Interfaces\RequestInterface;

class Request implements RequestInterface
{

    /** @var Ec2Client[] */
    protected $ec2Clients = [];
    /** @var PricingClient */
    protected $pricingClient;
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;

        $this->pricingClient = new PricingClient([
            'region' => 'us-east-1',
            'version' => 'latest',
            'credentials' => [
                'key'    => $this->config['api-key'],
                'secret' => $this->config['api-secret'],
            ],
        ]);
    }

    public function getPricingClient() : PricingClient
    {
        return $this->pricingClient;
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

    public function randomRegionAction(callable $callable) : AwsResult 
    {
        $ec2Client = $this->ec2Clients[array_rand($this->ec2Clients)];
        return call_user_func($callable, $region, $ec2Client);
    }

    public function namedRegionAction(string $region, callable $callable) : AwsResult
    {
        $ec2Client = $this->ec2Clients[$region];
        return call_user_func($callable, $ec2Client);
    }

    public function getRegionEc2Client(string $region) : Ec2Client
    {
        return $this->ec2Clients[$region];
    }
}
