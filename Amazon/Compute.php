<?php

namespace CloudDoctor\Amazon;

use Aws\Ec2\Ec2Client;
use Aws\Result;
use CloudDoctor\Cache\Cache;
use CloudDoctor\CloudDoctor;
use CloudDoctor\Common\ComputeGroup;
use CloudDoctor\Common\Request;
use CloudDoctor\Interfaces\ComputeInterface;
use CloudDoctor\Interfaces\RequestInterface;
use phpseclib\Net\SFTP;

class Compute extends \CloudDoctor\Common\Compute
{

    /** @var string[] */
    protected $amis = [];
    /** @var string[] */
    protected $region = [];
    /** @var string[] */
    protected $type = [];
    /** @var string[] */
    protected $vpcs = [];
    /** @var string[] */
    protected $subnets = [];

    /** @var \CloudDoctor\Amazon\Request */
    protected $requester;

    protected $regionToLocationNames = [
        'US West (Oregon)' => 'us-west-2',
        'US West (N. California)' => 'us-west-1',
        'US East (Ohio)' => 'us-east-2',
        'US East (N. Virginia)' => 'us-east-1',
        'Asia Pacific (Mumbai)' => 'ap-south-1',
        'Asia Pacific (Seoul)' => 'ap-northeast-2',
        'Asia Pacific (Singapore)' => 'ap-southeast-1',
        'Asia Pacific (Sydney)' => 'ap-southeast-2',
        'Asia Pacific (Tokyo)' => 'ap-northeast-1',
        'Canada (Central)' => 'ca-central-1',
        'China (Beijing)' => 'cn-north-1',
        'EU (Frankfurt)' => 'eu-central-1',
        'EU (Ireland)' => 'eu-west-1',
        'EU (London)' => 'eu-west-2',
        'EU (Paris)' => 'eu-west-3',
        'South America (São Paulo)' => 'sa-east-1',
        'AWS GovCloud (US)' => 'us-gov-west-1',
    ];

    /**
     * @return string[]
     */
    public function getAmis(): array
    {
        return $this->amis;
    }

    /**
     * @param string[] $amis
     * @return Compute
     */
    public function setAmis(array $amis): Compute
    {
        $this->amis = $amis;
        return $this;
    }

    /**
     * @param string $ami
     * @return Compute
     */
    public function addAmi(array $ami): Compute
    {
        $this->amis[] = $ami;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getVpcs(): array
    {
        return $this->vpcs;
    }

    /**
     * @param string[] $vpcs
     * @return Compute
     */
    public function setVpcs(array $vpcs): ComputeInterface
    {
        $this->vpcs = $vpcs;
        return $this;
    }

    /**
     * @param string $vpc
     * @return Compute
     */
    public function addVpc(string $vpc): ComputeInterface
    {
        $this->vpcs[] = $vpc;
        return $this;
    }

    /**
     * @param string $regions
     * @return Compute
     */
    public function addRegion(string $region) : ComputeInterface
    {
        $this->regions[] = $region;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getSubnets(): array
    {
        return $this->subnets;
    }

    /**
     * @param string[] $subnets
     * @return Compute
     */
    public function setSubnets(array $subnets): ComputeInterface
    {
        $this->subnets = $subnets;
        return $this;
    }

    /**
     * @param string $subnet
     * @return Compute
     */
    public function addSubnet(string $subnet): ComputeInterface
    {
        $this->subnets[] = $subnet;
        return $this;
    }

    public function __construct(ComputeGroup $computeGroup, $config = null)
    {
        parent::__construct($computeGroup, $config);
        if ($config) {
            $this->setAmis($config['ami']);
            if($config['vpc']) {
                $this->setVpcs($config['vpc']);
            }
            if($config['vpc']) {
                $this->setSubnets($config['subnet']);
            }
        }
        \Kint::dump($this);
    }

    public function setRequester(RequestInterface $requester): ComputeInterface
    {
        parent::setRequester($requester);

        foreach ($this->config['region'] as $region) {
            $this->requester->setupForRegion($region);
        }

        return $this;
    }

    public function deploy()
    {
        CloudDoctor::Monolog()->addNotice("        ││└ Spinning up on AWS: {$this->getName()}...");
        if (!$this->isValid()) {
            CloudDoctor::Monolog()->addNotice("    Cannot be provisioned because:");
            foreach ($this->validityReasons as $reason) {
                CloudDoctor::Monolog()->addNotice("     - {$reason}");
            }
        } else {
            $region = $this->region[array_rand($this->region)];
            \Kint::dump($this->getEc2InstanceConfig($region));
            $response = $this->requester->getRegionEc2Client($region)
                ->runInstances($this->getEc2InstanceConfig($region));
            \Kint::dump($response);
            exit;
        }
    }

    protected function testValidity(): void
    {
        parent::testValidity();
        // TODO: Write more AWS-specific validation.
    }

    public function exists(): bool
    {
        return $this->getInstanceByName($this->getName()) !== null;
    }

    protected function getEc2InstanceConfig(string $region) : array
    {
        $matchingImageIds = $this->getEc2RegionMatchingAMIs($region);
        $subnetIds = $this->getEc2RegionMatchingSubnets($region);
        $supportedInstanceTypes = $this->getEc2RegionSupportedInstanceTypes($region);
        CloudDoctor::Monolog()->addDebug("Found " . count($supportedInstanceTypes) . " supported instance types, looking for " . implode(",", $this->getType()));
        foreach($this->getType() as $type){
            \Kint::dump($region, $supportedInstanceTypes, $type);
            if(in_array($type, $supportedInstanceTypes)){
                $typeToDeploy = $type;
                CloudDoctor::Monolog()->addDebug("Can use type: {$type}");
                break;
            }else{
                CloudDoctor::Monolog()->addDebug("Cannot use type: {$type}");
            }
        }

        return array_filter([
            'ImageId' => $matchingImageIds ? $matchingImageIds[array_rand($matchingImageIds)]['ImageId'] : null,
            'MinCount' => 1,
            'MaxCount' => 1,
            'InstanceType' => $typeToDeploy,
            'SubnetId' => $subnetIds ? $subnetIds[array_rand($subnetIds)]['SubnetId'] : null,
            'TagSpecifications' => [
                [
                    'ResourceType' => 'instance',
                    'Tags' => $this->getEc2InstanceTags(),
                ],
            ]
        ]);
    }

    private function getEc2InstanceTags() : array
    {
        $tags = [];
        foreach(array_merge($this->getTags(), ['Name' => $this->getName()]) as $key => $value){
            $tags[] = [
                'Key' => $key,
                'Value' => $value,
            ];
        }
        return $tags;
    }

    protected function region2Location(string $region) : ?string
    {
        foreach($this->regionToLocationNames as $location => $region){
            if($region == $region){
                return $location;
            }
        }
        return null;
    }

    protected function getEc2RegionSupportedInstanceTypes(string $region) : array
    {
        if(!Cache::Read()){
            CloudDoctor::Monolog()->addDebug("Getting list of Supported Instance Types for {$region} region...");
            $availableInstanceTypes = [];
            $nextToken = true;
            while($nextToken) {
                /** @var Result $response */
                $response = $this->requester->getPricingClient()->getProducts([
                    'ServiceCode' => 'AmazonEC2',
                    'NextToken' => is_string($nextToken) ? $nextToken : null,
                    'Filters' => [
                        [
                            'Field' => 'ServiceCode',
                            'Type' => 'TERM_MATCH',
                            'Value' => 'AmazonEC2'
                        ],[
                            'Field' => 'locationType',
                            'Type' => 'TERM_MATCH',
                            'Value' => 'AWS Region'
                        ],[
                            'Field' => 'location',
                            'Type' => 'TERM_MATCH',
                            'Value' => $this->Region2Location($region),
                        ],[
                            'Field' => 'operatingSystem',
                            'Type' => 'TERM_MATCH',
                            'Value' => 'Linux'
                        ],[
                            'Field' => 'preInstalledSw',
                            'Type' => 'TERM_MATCH',
                            'Value' => 'NA',
                        ]
                    ],
                    'FormatVersion' => 'aws_v1',

                ]);
                foreach ($response->get('PriceList') as $element) {
                    $element = json_decode($element, true);
                    if(isset($element['product']['attributes']['instanceType'])) {
                        $availableInstanceTypes[$element['product']['attributes']['instanceType']] = $element['product']['attributes']['instanceType'];
                    }
                }
                $nextToken = $response->get('NextToken');
            }

            $availableInstanceTypes = array_values($availableInstanceTypes);
            sort($availableInstanceTypes);
            CloudDoctor::Monolog()->addDebug("Found " . count($availableInstanceTypes) . " matching Supported Instance Types...");

            Cache::Write($availableInstanceTypes);
        }

        return Cache::Read();
    }

    protected function getEc2RegionMatchingSubnets(string $region) : array
    {
        if(!Cache::Read()){
            CloudDoctor::Monolog()->addDebug("Getting list of Subnets for {$region} region...");
            $matchingSubnets = [];
            /** @var Result $response */
            $response = $this->requester->getRegionEc2Client($region)->describeSubnets();
            CloudDoctor::Monolog()->addDebug("Found " . count($response->get('Images')) . " total Subnets...");
            foreach($response->get('Subnets') as $subnet){
                if(in_array($subnet['SubnetId'], $this->getSubnets())){
                    $matchingSubnets[$subnet['SubnetId']] = $subnet;
                }
            }
            CloudDoctor::Monolog()->addDebug("Found " . count($matchingSubnets) . " matching Subnets...");

            Cache::Write($matchingSubnets);
        }

        return Cache::Read();
    }

    protected function getEc2RegionMatchingAMIs(string $region) : array
    {
        if(!Cache::Read()){
            CloudDoctor::Monolog()->addDebug("Getting list of Public AMIs for {$region} region...");
            $matchingAmis = [];
            /** @var Result $response */
            $response = $this->requester->getRegionEc2Client($region)->describeImages();
            CloudDoctor::Monolog()->addDebug("Found " . count($response->get('Images')) . " total AMIs...");
            foreach($response->get('Images') as $ami){
                if(in_array($ami['ImageId'], $this->getAmis())){
                    $matchingAmis[$ami['ImageId']] = $ami;
                }
            }
            CloudDoctor::Monolog()->addDebug("Found " . count($matchingAmis) . " matching AMIs...");
            Cache::Write($matchingAmis);
        }

        return Cache::Read();
    }

    protected function getInstanceByName(string $name) : ?array
    {
        $allInstances = $this->requester->acrossRegionAction(
            function (string $region, Ec2Client $ec2Client) {
                $instances = [];
                foreach ($ec2Client->describeInstances()->get('Reservations') as $instance) {
                    $id = $instance['Instances'][0]['InstanceId'];
                    $regionField = ["Region" => $region];
                    $instance['Instances'][0] = $regionField + $instance['Instances'][0];
                    $instances[$id] = $instance['Instances'][0];
                }
                return $instances;
            }
        );

        foreach ($allInstances as $instance) {
            foreach ($instance['Tags'] as $tag) {
                if ($tag['Key'] == 'Name' && $tag['Value'] == $name) {
                    return $instance;
                }
            }
        }

        return null;
    }

    public function getPublicIp(): ?string
    {
        $instance = $this->getInstanceByName($this->getName());
        if($instance){
            \Kint::dump($instance);
            die("Go write getPublicIP function");
        }
        return null;
    }

    public function destroy(): bool
    {
       die("TODO: Implement destroy() method.\n");
    }

    public function isTransitioning(): bool
    {
       die("TODO: Implement isTransitioning() method.\n");
    }

    public function isRunning(): bool
    {
       die("TODO: Implement isRunning() method.\n");
    }

    public function isStopped(): bool
    {
       die("TODO: Implement isStopped() method.\n");
    }

    public function updateMetaData(): void
    {
       die("TODO: Implement updateMetaData() method.\n");
    }
}
