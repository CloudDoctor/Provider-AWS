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
    /** @var string */
    private $description;

    public static function Factory(array $config) : SecurityGroupRule
    {
        $sgr = new SecurityGroupRule();
        if (isset($config['port'])) {
            if ($config['port'] == 'any') {
                $sgr->setPort(self::SOURCE_ANY);
            } elseif ($config['port'] == 'self') {
                $sgr->setPort(self::SOURCE_SELF);
            } else {
                $sgr->setPort($config['port']);
            }
        }
        if (isset($config['protocol'])) {
            $sgr->setProtocol($config['protocol']);
        }
        if (isset($config['source'])) {
            $sgr->setSource($config['source']);
        }
        if (isset($config['description'])) {
            $sgr->setDescription($config['description']);
        }

        return $sgr;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     *
     * @return SecurityGroupRule
     */
    public function setDescription(string $description): SecurityGroupRule
    {
        $this->description = $description;
        return $this;
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
     *
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
     *
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
     *
     * @return SecurityGroupRule
     */
    public function setSource(string $source): SecurityGroupRule
    {
        $this->source = $source;
        return $this;
    }
}
