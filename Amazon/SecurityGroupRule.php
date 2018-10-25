<?php

namespace CloudDoctor\Amazon;

class SecurityGroupRule
{
    const PROTOCOL_TCP = 'tcp';
    const PROTOCOL_UDP = 'udp';
    const PROTOCOL_ALL = 'all';

    const SOURCE_ANY = -1;
    const SOURCE_SELF = -2;

    /** @var int */
    private $port;
    /** @var string */
    private $protocol;
    /** @var string */
    private $source;

    public static function Factory(array $config) : SecurityGroupRule
    {
        $sgr = new SecurityGroupRule();
        if(isset($config['port'])) {
            if($config['port'] == 'any') {
                $sgr->setPort(self::SOURCE_ANY);
            }else if($config['port'] == 'self'){
                $sgr->setPort(self::SOURCE_SELF);
            }else {
                $sgr->setPort($config['port']);
            }
        }
        if(isset($config['protocol'])) {
            $sgr->setProtocol($config['protocol']);
        }
        if(isset($config['source'])) {
            $sgr->setSource($config['source']);
        }

        return $sgr;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @param int $port
     * @return SecurityGroupRule
     */
    public function setPort(int $port): SecurityGroupRule
    {
        $this->port = $port;
        return $this;
    }

    /**
     * @return string
     */
    public function getProtocol(): string
    {
        return $this->protocol;
    }

    /**
     * @param string $protocol
     * @return SecurityGroupRule
     */
    public function setProtocol(string $protocol): SecurityGroupRule
    {
        $this->protocol = $protocol;
        return $this;
    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @param string $source
     * @return SecurityGroupRule
     */
    public function setSource(string $source): SecurityGroupRule
    {
        $this->source = $source;
        return $this;
    }
}