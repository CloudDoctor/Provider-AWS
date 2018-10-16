<?php

namespace CloudDoctor\Amazon;

use Aws\Ec2\Ec2Client;
use CloudDoctor\Common\ComputeGroup;
use CloudDoctor\Common\Request;
use CloudDoctor\Interfaces\ComputeInterface;
use CloudDoctor\Interfaces\RequestInterface;
use phpseclib\Net\SFTP;

class Compute extends \CloudDoctor\Common\Compute
{
    /** @var \CloudDoctor\Amazon\Request */
    protected $requester;

    public function __construct(ComputeGroup $computeGroup, $config = null)
    {
        parent::__construct($computeGroup, $config);
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
        // TODO: Implement deploy() method.
    }

    public function exists(): bool
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
                if ($tag['Key'] == 'Name' && $tag['Value'] == $this->getName()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function destroy(): bool
    {
        // TODO: Implement destroy() method.
    }

    public function getSshConnection(): ?SFTP
    {
        // TODO: Implement getSshConnection() method.
    }

    public function isTransitioning(): bool
    {
        // TODO: Implement isTransitioning() method.
    }

    public function isRunning(): bool
    {
        // TODO: Implement isRunning() method.
    }

    public function isStopped(): bool
    {
        // TODO: Implement isStopped() method.
    }

    public function getPublicIp(): string
    {
        // TODO: Implement getPublicIp() method.
    }

    public function updateMetaData(): void
    {
        // TODO: Implement updateMetaData() method.
    }
}
