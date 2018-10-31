<?php

namespace CloudDoctor\Amazon;

use Aws\Route53\Exception\Route53Exception;
use Aws\Route53\Route53Client;
use CloudDoctor\CloudDoctor;
use CloudDoctor\Interfaces\DNSControllerInterface;
use Monolog\Logger;

class DNSController
    implements DNSControllerInterface
{
    private $ttl = 300;
    /** @var array */
    private $config;
    /** @var Route53Client */
    private $route53Client;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->route53Client = new Route53Client([
            'region' => 'us-east-1',
            'version' => 'latest',
            'credentials' => [
                'key'    => $this->config['api-key'],
                'secret' => $this->config['api-secret'],
            ],
        ]);
    }

    private function getHostedZone(string $domain) : ?array
    {
        $domain = explode(".", $domain);
        $allZones = $this->route53Client->listHostedZones()->get("HostedZones");
        foreach($allZones as $zone){
            foreach($domain as $i => $domainElement){
                $domainFragment = implode(".",array_slice($domain, $i)) . ".";
                if($domainFragment == $zone['Name']){
                    return $zone;
                }
            }
        }
        return null;
    }
    private function array_sort($array){
        sort($array);
        return $array;
    }

    public function verifyRecordCorrect(string $domain, array $values): bool
    {
        $hostedZone = $this->getHostedZone($domain);

        $testDns = $this->route53Client->testDNSAnswer([
            'HostedZoneId' => $hostedZone['Id'],
            'RecordName' => $domain,
            'RecordType' => filter_var($values[0], FILTER_VALIDATE_IP) ? 'A' : 'CNAME',
        ]);

        return ($this->array_sort($values) == $this->array_sort($testDns->get('RecordData')));
    }

    public function removeRecord(string $type, string $domain): int
    {
        $hostedZone = $this->getHostedZone($domain);

        $testDns = $this->route53Client->testDNSAnswer([
            'HostedZoneId' => $hostedZone['Id'],
            'RecordName' => $domain,
            'RecordType' => strtoupper($type),
        ]);

        if(empty($testDns->get('RecordData'))){
            return 0;
        }

        $resourceRecords = [];
        foreach($testDns->get('RecordData') as $value){
            $resourceRecords[] = [
                'Value' => $value,
            ];
        }

        $deleteRecordRequest = [
            'HostedZoneId' => $hostedZone['Id'],
            'ChangeBatch' => array(
                'Changes' => array(
                    array(
                        'Action' => 'DELETE',
                        'ResourceRecordSet' => array(
                            'Name' => $domain,
                            'Type' => strtoupper($type),
                            'TTL' => $this->ttl,
                            'ResourceRecords' => $resourceRecords,
                        ),
                    ),
                ),
            ),
        ];
        $createRecordResponse = $this->route53Client->changeResourceRecordSets($deleteRecordRequest);

        return $createRecordResponse->get('ChangeInfo')['Status'] == 'Pending' ? 1 : 0;
    }

    public function createRecord(string $type, string $domain, string $value): bool
    {
       return $this->createRecords($type, $domain, [$value]);
    }

    public function createRecords(string $type, string $domain, array $values): bool
    {
        $hostedZone = $this->getHostedZone($domain);

        $resourceRecords = [];
        foreach($values as $value){
            $resourceRecords[] = [
                'Value' => $value,
            ];
        }

        $createRecordRequest = [
            'HostedZoneId' => $hostedZone['Id'],
            'ChangeBatch' => array(
                'Changes' => array(
                    array(
                        'Action' => 'UPSERT',
                        'ResourceRecordSet' => array(
                            'Name' => $domain,
                            'Type' => strtoupper($type),
                            'TTL' => $this->ttl,
                            'ResourceRecords' => $resourceRecords,
                        ),
                    ),
                ),
            ),
        ];
        $createRecordResponse = $this->route53Client->changeResourceRecordSets($createRecordRequest);

        return $createRecordResponse->get('ChangeInfo')['Status'] == 'Pending';
    }

}